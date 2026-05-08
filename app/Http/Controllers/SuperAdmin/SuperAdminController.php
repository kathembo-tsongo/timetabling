<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        $tenants = Tenant::withCount(['users', 'schools'])
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'total_tenants'  => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'total_users'    => User::withoutGlobalScopes()->count(),
            'plans'          => Tenant::groupBy('plan')
                ->select('plan', DB::raw('count(*) as count'))
                ->get()->pluck('count', 'plan'),
        ];

        return Inertia::render('SuperAdmin/Dashboard', [
            'tenants' => $tenants,
            'stats'   => $stats,
        ]);
    }

    public function show(Tenant $tenant)
    {
        $stats = [
            'users'    => User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'schools'  => DB::table('schools')->where('tenant_id', $tenant->id)->count(),
            'programs' => DB::table('programs')->where('tenant_id', $tenant->id)->count(),
            'units'    => DB::table('units')->where('tenant_id', $tenant->id)->count(),
        ];

        return Inertia::render('SuperAdmin/TenantDetail', [
            'tenant' => $tenant,
            'stats'  => $stats,
        ]);
    }

    public function toggleTenant(Tenant $tenant)
    {
        $tenant->update(['is_active' => !$tenant->is_active]);
        return back()->with('success',
            "{$tenant->name} " . ($tenant->is_active ? 'activated' : 'deactivated')
        );
    }

    public function impersonate(Tenant $tenant)
    {
        session(['impersonating_tenant_id' => $tenant->id]);
        return redirect('/dashboard')
            ->with('success', "Now viewing as {$tenant->name}");
    }

    public function stopImpersonating()
    {
        session()->forget('impersonating_tenant_id');
        return redirect('/super-admin')
            ->with('success', 'Stopped impersonating');
    }

    public function destroy(Tenant $tenant)
    {
        $name = $tenant->name;
        $tenant->delete();
        return redirect('/super-admin')
            ->with('success', "{$name} deleted.");
    }
}
