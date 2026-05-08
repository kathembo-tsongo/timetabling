<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->is_super_admin) {
            abort(403, 'Super admin access only.');
        }

        // Remove tenant scoping for super admin
        config(['app.is_super_admin' => true]);

        return $next($request);
    }
}
