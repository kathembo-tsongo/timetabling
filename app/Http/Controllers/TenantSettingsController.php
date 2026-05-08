<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class TenantSettingsController extends Controller
{
    public function index()
    {
        $tenant = tenant();

        return Inertia::render('Settings/Index', [
            'tenant'   => $tenant,
            'settings' => $tenant->settings ?? [],
        ]);
    }

    public function updateGeneral(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'university_name'  => 'required|string|max:255',
            'university_motto' => 'nullable|string|max:255',
            'country'          => 'nullable|string|max:100',
            'timezone'         => 'required|string|timezone',
            'locale'           => 'nullable|string|max:10',
            'currency'         => 'nullable|string|max:5',
            'academic_year'    => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenant = tenant();
        $settings = $tenant->settings ?? [];

        $settings = array_merge($settings, [
            'university_name'  => $request->university_name,
            'university_motto' => $request->university_motto,
            'country'          => $request->country,
            'academic_year'    => $request->academic_year,
        ]);

        $tenant->update([
            'timezone' => $request->timezone,
            'locale'   => $request->locale ?? 'en',
            'currency' => $request->currency ?? 'KES',
            'settings' => $settings,
        ]);

        return back()->with('success', 'General settings updated successfully!');
    }

    public function updateBranding(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'primary_color'   => 'required|string|max:7',
            'secondary_color' => 'required|string|max:7',
            'logo'            => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenant = tenant();
        $data = [
            'primary_color'   => $request->primary_color,
            'secondary_color' => $request->secondary_color,
        ];

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store(
                "tenants/{$tenant->id}/logos", 'public'
            );
            $data['logo'] = $path;
        }

        $tenant->update($data);

        return back()->with('success', 'Branding updated successfully!');
    }

    public function updateDomain(Request $request)
    {
        $request->validate([
            'domain' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9][a-zA-Z0-9\.\-]+[a-zA-Z0-9]$/',
        ]);

        $tenant = tenant();

        // Check domain not taken by another tenant
        if ($request->domain) {
            $existing = \App\Models\Tenant::where('domain', $request->domain)
                ->where('id', '!=', $tenant->id)
                ->first();
            if ($existing) {
                return back()->withErrors(['domain' => 'This domain is already in use.']);
            }
        }

        $tenant->update(['domain' => $request->domain ?: null]);

        return back()->with('success', $request->domain
            ? "Custom domain set! Point your DNS CNAME to: yoursaas.com"
            : 'Custom domain removed.');
    }

    public function updateAcademic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'grading_system'     => 'required|in:GPA,percentage,letter',
            'max_credit_hours'   => 'required|integer|min:1|max:40',
            'semester_type'      => 'required|in:semester,trimester,quarter',
            'credit_hour_system' => 'boolean',
            'exam_gap_days'      => 'nullable|integer|min:0|max:10',
            'max_exams_per_day'  => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenant = tenant();
        $settings = $tenant->settings ?? [];

        $settings = array_merge($settings, [
            'grading_system'     => $request->grading_system,
            'max_credit_hours'   => $request->max_credit_hours,
            'semester_type'      => $request->semester_type,
            'credit_hour_system' => $request->boolean('credit_hour_system'),
            'exam_rules'         => [
                'min_gap_days'   => $request->exam_gap_days ?? 2,
                'max_per_day'    => $request->max_exams_per_day ?? 3,
                'allow_weekends' => $request->boolean('allow_weekends'),
            ],
        ]);

        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Academic settings updated successfully!');
    }
}
