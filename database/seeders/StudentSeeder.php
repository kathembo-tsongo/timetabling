<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        // Verify Student role exists before proceeding
        if (!Role::where('name', 'Student')->exists()) {
            $this->command->error('Student role does not exist. Please run RoleAndPermissionSeeder first.');
            return;
        }

        $programs = [
            //'BBIT' => 'SCES',
            //'BSICS' => 'SCES',
            //'CNS' => 'SCES',
           // 'BFS' => 'SBS',
            //'BCOM' => 'SBS',
            //'BTM' => 'STH',
            //'BHM' => 'STH',
            'BSCM' => 'SBS',
        ];

        $this->command->info('Starting student creation...');
        $totalStudents = 0;
        $failedAssignments = 0;

        foreach ($programs as $program => $school) {
            $this->command->info("Creating students for program: {$program} in school: {$school}");
            
            for ($i = 1; $i <= 400; $i++) {
                try {
                    $user = User::create([
                        'first_name' => 'Student' . $i,
                        'last_name' => $program,
                        'email' => strtolower($program) . $i . '@strathmore.edu',
                        'phone' => '0730501' . str_pad($i, 4, '0', STR_PAD_LEFT),
                        'code' => strtoupper($program) . str_pad($i, 4, '0', STR_PAD_LEFT),
                        'schools' => $school,
                        'programs' => $program,
                        'password' => Hash::make('password'),
                    ]);

                    // Assign Student role with error handling
                    try {
                        $user->assignRole('Student');
                        $totalStudents++;
                    } catch (\Exception $e) {
                        $failedAssignments++;
                        $this->command->error("Failed to assign Student role to user {$user->code}: " . $e->getMessage());
                        Log::error('Role assignment failed', [
                            'user_id' => $user->id,
                            'user_code' => $user->code,
                            'error' => $e->getMessage()
                        ]);
                    }

                    // Progress feedback every 50 students
                    if ($i % 50 == 0) {
                        $this->command->info("Created {$i}/400 students for {$program}");
                    }

                } catch (\Exception $e) {
                    $this->command->error("Failed to create student {$i} for {$program}: " . $e->getMessage());
                    Log::error('Student creation failed', [
                        'program' => $program,
                        'student_number' => $i,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->command->info("Completed creating students for {$program}");
        }

        // Uncomment the following section to create additional users (401-529)
        /*
        $this->command->info('Creating additional students (401-529)...');
        
        foreach ($programs as $program => $school) {
            $this->command->info("Creating additional students for program: {$program}");
            
            for ($i = 401; $i < 530; $i++) {
                try {
                    $user = User::create([
                        'first_name' => 'Student' . $i,
                        'last_name' => $program,
                        'email' => strtolower($program) . $i . '@strathmore.edu',
                        'phone' => '07013' . str_pad($i, 4, '0', STR_PAD_LEFT),
                        'code' => strtoupper($program) . str_pad($i, 4, '0', STR_PAD_LEFT),
                        'schools' => $school,
                        'programs' => $program,
                        'password' => Hash::make('password'),
                    ]);

                    try {
                        $user->assignRole('Student');
                        $totalStudents++;
                    } catch (\Exception $e) {
                        $failedAssignments++;
                        $this->command->error("Failed to assign Student role to user {$user->code}: " . $e->getMessage());
                        Log::error('Role assignment failed', [
                            'user_id' => $user->id,
                            'user_code' => $user->code,
                            'error' => $e->getMessage()
                        ]);
                    }

                    if (($i - 400) % 25 == 0) {
                        $this->command->info("Created " . ($i - 400) . "/129 additional students for {$program}");
                    }

                } catch (\Exception $e) {
                    $this->command->error("Failed to create additional student {$i} for {$program}: " . $e->getMessage());
                    Log::error('Additional student creation failed', [
                        'program' => $program,
                        'student_number' => $i,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        */

        // Final summary
        $this->command->info('Student seeding completed!');
        $this->command->info("Total students created: {$totalStudents}");
        
        if ($failedAssignments > 0) {
            $this->command->warn("Failed role assignments: {$failedAssignments}");
            $this->command->warn("Check the logs for details on failed role assignments.");
        }

        // Verify final counts
        $studentCount = User::role('Student')->count();
        $this->command->info("Students with 'Student' role: {$studentCount}");
        
        // Log summary
        Log::info('Student seeding completed', [
            'total_created' => $totalStudents,
            'failed_assignments' => $failedAssignments,
            'final_student_role_count' => $studentCount
        ]);
    }
}