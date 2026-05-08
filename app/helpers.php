<?php

if (!function_exists('tenant')) {
    function tenant(): ?\App\Models\Tenant
    {
        return app()->has('tenant') ? app('tenant') : null;
    }
}

if (!function_exists('tenantCan')) {
    function tenantCan(string $feature): bool
    {
        if (!app()->has('tenant')) return true;
        $plan   = tenant()->plan ?? 'free';
        $limits = \App\Http\Middleware\CheckPlanFeature::planLimits();
        return $limits[$plan][$feature] ?? false;
    }
}

if (!function_exists('tenantLimit')) {
    function tenantLimit(string $key): int
    {
        if (!app()->has('tenant')) return -1;
        $plan   = tenant()->plan ?? 'free';
        $limits = \App\Http\Middleware\CheckPlanFeature::planLimits();
        return $limits[$plan][$key] ?? 0;
    }
}
