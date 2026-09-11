<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Subscriber;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class ThemeController extends Controller
{
    public function home(Request $request): Response
    {
        $tenant = $this->resolveTenant($request);

        $posts = Post::with(['author', 'categories'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->paginate(10);

        $categories = Category::where('tenant_id', $tenant->id)
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        return response()->view('theme.home', compact('tenant', 'posts', 'categories'));
    }

    public function post(Request $request, string $slug): Response
    {
        $tenant = $this->resolveTenant($request);

        $post = Post::with(['author', 'categories', 'tags'])
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->view('theme.post', compact('tenant', 'post'));
    }

    public function category(Request $request, string $slug): Response
    {
        $tenant = $this->resolveTenant($request);

        $category = Category::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();

        $posts = Post::with(['author', 'categories'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->whereHas('categories', fn ($q) => $q->where('id', $category->id))
            ->orderByDesc('published_at')
            ->paginate(10);

        return response()->view('theme.category', compact('tenant', 'category', 'posts'));
    }

    public function subscribeForm(Request $request): Response
    {
        $tenant = $this->resolveTenant($request);

        return response()->view('theme.subscribe', compact('tenant'));
    }

    public function subscribe(Request $request, Tenant $tenant): Response
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $subscriber = Subscriber::updateOrCreate(
            ['email' => $request->email, 'tenant_id' => $tenant->id],
            ['status' => 'subscribed'],
        );

        return response()->view('theme.subscribe-success', compact('tenant'));
    }

    public function contactForm(Request $request): Response
    {
        $tenant = $this->resolveTenant($request);

        return response()->view('theme.contact', compact('tenant'));
    }

    public function contact(Request $request, Tenant $tenant): Response
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $contactEmail = $tenant->settings
            ->where('key', 'contact_email')
            ->first()?->value ?? config('mail.from.address');

        Mail::raw($request->message, function ($mail) use ($request, $contactEmail) {
            $mail->to($contactEmail)
                ->subject('Contact from '.$request->name)
                ->replyTo($request->email);
        });

        return response()->view('theme.contact-success', compact('tenant'));
    }

    private function resolveTenant(Request $request): Tenant
    {
        $host = strtolower($request->getHost());
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        $tenant = Tenant::where('domain', $host)->first();
        if ($tenant) {
            return $tenant;
        }

        $param = $request->input('tenant');
        if ($param) {
            $tenant = Tenant::where('domain', $param)->orWhere('id', $param)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        abort(404, 'Tenant not found.');
    }
}
