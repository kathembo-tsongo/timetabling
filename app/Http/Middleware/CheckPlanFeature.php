<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPlanFeature
{
    // Define what each plan can access
    public static function planLimits(): array
    {
        return [
            'free' => [
                'max_users'    => 50,
                'max_schools'  => 1,
                'exam_timetabling'     => false,
                'lecturer_assignments' => false,
                'analytics'            => false,
                'api_access'           => false,
                'custom_domain'        => false,
            ],
            'pro' => [
                'max_users'    => 500,
                'max_schools'  => 5,
                'exam_timetabling'     => true,
                'lecturer_assignments' => true,
                'analytics'            => true,
                'api_access'           => false,
                'custom_domain'        => false,
            ],
            'enterprise' => [
                'max_users'    => -1,
                'max_schools'  => -1,
                'exam_timetabling'     => true,
                'lecturer_assignments' => true,
                'analytics'            => true,
                'api_access'           => true,
                'custom_domain'        => true,
            ],
        ];
    }

    public function handle(Request $request, Closure $next, string $feature)
    {
        if (!app()->has('tenant')) {
            return $next($request);
        }

        $tenant = tenant();
        $plan   = $tenant->plan ?? 'free';
        $limits = self::planLimits()[$plan] ?? self::planLimits()['free'];

        if (!($limits[$feature] ?? false)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'Feature not available on your plan',
                    'feature' => $feature,
                    'plan'    => $plan,
                    'upgrade' => '/billing',
                ], 403);
            }

            return redirect('/billing')->with('error',
                "The '{$feature}' feature is not available on your {$plan} plan. Please upgrade to access it."
            );
        }

        return $next($request);
    }
}
