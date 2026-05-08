<?php
namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        // Skip for public and super admin routes
        if ($request->is('onboarding*') || $request->is('api/*') || $request->is('super-admin*')) {
            if ($request->is('super-admin*')) {
                config(['app.is_super_admin' => true]);
            }
            return $next($request);
        }

        $tenant = null;

        // 1. Check impersonation first
        if (session()->has('impersonating_tenant_id')) {
            $tenant = Tenant::find(session('impersonating_tenant_id'));
        }

        // 2. Try exact domain match
        if (!$tenant) {
            $host = $request->getHost(); // e.g. localhost or strathmore.yoursaas.com
            $tenant = Tenant::where('domain', $host)
                ->where('is_active', true)
                ->first();
        }

        // 3. Try subdomain slug match (for strathmore.yoursaas.com → slug=strathmore)
        if (!$tenant) {
            $subdomain = explode('.', $request->getHost())[0];
            // Only match slug if it looks like a subdomain (not localhost/127)
            if (!in_array($subdomain, ['localhost', '127', '0'])) {
                $tenant = Tenant::where('slug', $subdomain)
                    ->where('is_active', true)
                    ->first();
            }
        }

        // 4. Local development fallback — use first active tenant
        if (!$tenant && app()->isLocal()) {
            $tenant = Tenant::where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        if (!$tenant) {
            abort(404, 'University not found.');
        }

        App::instance('tenant', $tenant);
        config(['app.tenant_id' => $tenant->id]);

        return $next($request);
    }
}
