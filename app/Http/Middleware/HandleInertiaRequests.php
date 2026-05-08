<?php
namespace App\Http\Middleware;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user'        => $request->user(),
                'roles'       => $request->user() ? $request->user()->getRoleNames()->toArray() : [],
                'permissions' => $request->user() ? $request->user()->getAllPermissions()->pluck('name')->toArray() : [],
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'csrf_token' => csrf_token(),

            // ── Tenant shared with every page ──────────────────────────
            'tenant' => fn () => app()->has('tenant') ? [
                'id'              => tenant()->id,
                'name'            => tenant()->name,
                'slug'            => tenant()->slug,
                'logo'            => tenant()->logo,
                'primary_color'   => tenant()->primary_color,
                'secondary_color' => tenant()->secondary_color,
                'timezone'        => tenant()->timezone,
                'plan'            => tenant()->plan,
                'settings'        => tenant()->settings,
            ] : null,

            // ── Impersonation banner ───────────────────────────────────
            'impersonating' => fn () => session()->has('impersonating_tenant_id'),

            // ── Plan feature flags ─────────────────────────────────────
            'plan_features' => fn () => app()->has('tenant')
                ? \App\Http\Middleware\CheckPlanFeature::planLimits()[tenant()->plan ?? 'free'] ?? []
                : [],
        ]);
    }
}
