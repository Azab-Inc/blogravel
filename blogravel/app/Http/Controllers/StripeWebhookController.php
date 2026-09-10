<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! config('billing.enabled')) {
            return response()->json(['message' => 'Billing is not enabled.'], 400);
        }

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (! $sigHeader) {
            abort(400, 'Missing Stripe-Signature header.');
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('billing.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed: '.$e->getMessage());

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        match ($event->type) {
            'customer.subscription.created',
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => Log::info('Unhandled Stripe event: '.$event->type),
        };

        return response()->json(['received' => true]);
    }

    private function handleSubscriptionUpdated($subscription): void
    {
        $tenant = Tenant::where('stripe_id', $subscription->customer)->first();

        if (! $tenant) {
            Log::warning('Stripe webhook: tenant not found for customer '.$subscription->customer);

            return;
        }

        $plan = match ($subscription->items->data[0]->price->id ?? null) {
            config('services.stripe.pro_price_id') => Plan::Pro,
            config('services.stripe.business_price_id') => Plan::Business,
            default => Plan::Free,
        };

        Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'stripe_id' => $subscription->id,
                'stripe_status' => $subscription->status,
                'stripe_plan' => $plan->value,
                'trial_ends_at' => $subscription->trial_end
                    ? Carbon::createFromTimestamp($subscription->trial_end)
                    : null,
                'ends_at' => $subscription->ended_at
                    ? Carbon::createFromTimestamp($subscription->ended_at)
                    : null,
            ],
        );

        $tenant->update(['plan' => $plan]);
    }

    private function handleSubscriptionDeleted($subscription): void
    {
        $tenant = Tenant::where('stripe_id', $subscription->customer)->first();

        if (! $tenant) {
            return;
        }

        Subscription::where('tenant_id', $tenant->id)
            ->where('stripe_id', $subscription->id)
            ->update([
                'stripe_status' => 'canceled',
                'ends_at' => now(),
            ]);

        $tenant->update(['plan' => Plan::Free]);
    }
}
