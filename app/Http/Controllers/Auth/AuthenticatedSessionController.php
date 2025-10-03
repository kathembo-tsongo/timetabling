<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
{
    $request->authenticate();
    $request->session()->regenerate();
    
    // Get authenticated user with roles eager-loaded
    $user = Auth::user()->load('roles');
    $roles = $user->getRoleNames();
    
    // Direct role-based redirect
    if ($roles->contains('Admin')) {
        return redirect()->route('admin.dashboard');
    }
    
    if ($roles->contains('Exam office')) {
        return redirect()->route('exam-office.dashboard');
    }
    
    // NEW: Class Office check
    if ($roles->contains('Class Office')) {
        return redirect()->route('class-office.dashboard');
    }
    
    // Faculty Admin check
    $facultyRole = $roles->first(fn($role) => str_starts_with($role, 'Faculty Admin - '));
    if ($facultyRole) {
        $faculty = str_replace('Faculty Admin - ', '', $facultyRole);
        $redirectRoute = match($faculty) {
            'SCES' => 'school.admin.dashboard',
            'SBS' => 'school.admin.dashboard',
            default => null
        };
        if ($redirectRoute) {
            return redirect()->route($redirectRoute);
        }
    }
    
    if ($roles->contains('Lecturer')) {
        return redirect()->route('lecturer.dashboard');
    }
    
    if ($roles->contains('Student')) {
        return redirect()->route('student.dashboard');
    }
    
    // Fallback to default dashboard
    return redirect()->intended(RouteServiceProvider::HOME);
}
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}    