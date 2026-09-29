<form class="docs-tenant-form" method="GET" action="{{ route('docs.api') }}">
    <label for="tenant">Tenant slug</label>
    <div class="docs-tenant-form-row">
        <input id="tenant" name="tenant" value="{{ $tenantSlug ?? '' }}" placeholder="acmeio" required pattern="[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?">
        <button type="submit">Generate examples</button>
    </div>
</form>
