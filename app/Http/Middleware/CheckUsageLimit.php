<?php
namespace App\Http\Middleware;

use App\Http\Middleware\CheckPlanFeature;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class CheckUsageLimit
{
    public function handle(Request $request, Closure $next, string $resource)
    {
        if (!app()->has('tenant')) return $next($request);

        $tenant = tenant();
        $plan   = $tenant->plan ?? 'free';
        $limits = CheckPlanFeature::planLimits()[$plan] ?? [];

        switch ($resource) {
            case 'users':
                $limit   = $limits['max_users'] ?? 50;
                $current = User::count();
                if ($limit !== -1 && $current >= $limit) {
                    $msg = "User limit reached ({$current}/{$limit}) for your {$plan} plan.";
                    return $request->expectsJson()
                        ? response()->json(['error' => $msg, 'upgrade' => '/billing'], 403)
                        : redirect()->back()->with('error', $msg . ' Please upgrade your plan.');
                }
                break;

            case 'schools':
                $limit   = $limits['max_schools'] ?? 1;
                $current = \App\Models\School::count();
                if ($limit !== -1 && $current >= $limit) {
                    $msg = "School limit reached ({$current}/{$limit}) for your {$plan} plan.";
                    return $request->expectsJson()
                        ? response()->json(['error' => $msg, 'upgrade' => '/billing'], 403)
                        : redirect()->back()->with('error', $msg . ' Please upgrade your plan.');
                }
                break;
        }

        return $next($request);
    }
}
