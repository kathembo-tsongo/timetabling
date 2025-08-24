<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;

class AssignFacultyAdminToSchool extends Command
{
    protected $signature = 'faculty:assign-to-school {email} {school_code}';
    protected $description = 'Assign a faculty admin to a specific school using Spatie permissions';

    public function handle()
    {
        $email = $this->argument('email');
        $schoolCode = $this->argument('school_code');

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("User with email {$email} not found");
            return;
        }

        $school = School::where('code', $schoolCode)->first();
        if (!$school) {
            $this->error("School with code {$schoolCode} not found");
            return;
        }

        try {
            // First, create school-specific permissions if they don't exist
            \Artisan::call('permissions:create-school-specific');

            // Assign the user to the school
            $user->assignToSchool($schoolCode);

            $this->info("Successfully assigned {$user->email} as Faculty Admin for {$school->name}");
            $this->info("User details:");
            $this->info("- Name: {$user->first_name} {$user->last_name}");
            $this->info("- Email: {$user->email}");
            $this->info("- Roles: " . $user->getRoleNames()->join(', '));
            $this->info("- Assigned School: {$school->name} ({$school->code})");
            $this->info("- Can manage {$school->code}: " . ($user->canManageSchool($school->code) ? 'YES' : 'NO'));

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return;
        }

        return 0;
    }
}
