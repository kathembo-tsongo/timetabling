<?php
namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use App\Mail\WelcomeTenantMail;
use Illuminate\Support\Facades\Mail;

class OnboardingController extends Controller
{
    // Step 1 — Show welcome/signup page
    public function index()
    {
        return Inertia::render('Onboarding/Index');
    }

    // Step 2 — Show setup wizard
    public function setup()
    {
        return Inertia::render('Onboarding/Setup', [
            'timezones' => \DateTimeZone::listIdentifiers(),
            'plans'     => [
                ['id' => 'free',       'name' => 'Free',       'limit' => '50 users'],
                ['id' => 'pro',        'name' => 'Pro',        'limit' => '500 users'],
                ['id' => 'enterprise', 'name' => 'Enterprise', 'limit' => 'Unlimited'],
            ],
        ]);
    }

    // Step 3 — Process and create the tenant
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // University details
            'university_name'  => 'required|string|max:255',
            'university_slug'  => 'required|string|max:60|unique:tenants,slug|alpha_dash',
            'country'          => 'required|string|max:100',
            'timezone'         => 'required|string|timezone',
            'plan'             => 'required|in:free,pro,enterprise',

            // Branding
            'primary_color'    => 'required|string|max:7',
            'secondary_color'  => 'required|string|max:7',

            // Academic structure
            'schools'          => 'required|array|min:1',
            'schools.*.name'   => 'required|string|max:255',
            'schools.*.code'   => 'required|string|max:20',

            // Semester type
            'semester_type'    => 'required|in:semester,trimester,quarter',

            // Admin account
            'admin_first_name' => 'required|string|max:100',
            'admin_last_name'  => 'required|string|max:100',
            'admin_email'      => 'required|email|unique:users,email',
            'admin_password'   => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // 1. Create the tenant
            $tenant = Tenant::create([
                'name'            => $request->university_name,
                'slug'            => $request->university_slug,
                'primary_color'   => $request->primary_color,
                'secondary_color' => $request->secondary_color,
                'timezone'        => $request->timezone,
                'plan'            => $request->plan,
                'is_active'       => true,
                'trial_ends_at'   => now()->addDays(30),
                'settings'        => [
                    'university_name'  => $request->university_name,
                    'country'          => $request->country,
                    'semester_type'    => $request->semester_type,
                    'max_credit_hours' => 21,
                    'grading_system'   => 'GPA',
                ],
            ]);

            // 2. Create schools for this tenant
            foreach ($request->schools as $school) {
                DB::table('schools')->insert([
                    'name'       => $school['name'],
                    'code'       => strtoupper($school['code']),
                    'tenant_id'  => $tenant->id,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 3. Create the admin user
            $admin = User::create([
                'first_name' => $request->admin_first_name,
                'last_name'  => $request->admin_last_name,
                'email'      => $request->admin_email,
                'password'   => Hash::make($request->admin_password),
                'code'       => strtoupper(substr($request->admin_first_name, 0, 3)) . '001',
                'tenant_id'  => $tenant->id,
                'is_active'  => true,
            ]);

            // 4. Assign Admin role
            $admin->assignRole('Admin');

            // Send welcome email
            Mail::to($request->admin_email)->send(new WelcomeTenantMail($tenant, $request->admin_email));

            DB::commit();

            return redirect()->route('onboarding.success', ['slug' => $tenant->slug]);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Setup failed: ' . $e->getMessage());
        }
    }

    // Step 4 — Success page
    public function success(Request $request)
    {
        $tenant = Tenant::where('slug', $request->slug)->firstOrFail();
        return Inertia::render('Onboarding/Success', [
            'tenant' => $tenant,
        ]);
    }
}
