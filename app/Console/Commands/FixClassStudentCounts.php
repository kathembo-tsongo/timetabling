<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClassModel;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

class FixClassStudentCounts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'classes:fix-student-counts 
                           {--class= : Fix count for specific class ID only}
                           {--semester= : Fix counts for specific semester only}
                           {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Fix and update the students_count column in classes table based on actual enrollments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to fix class student counts...');
        
        $dryRun = $this->option('dry-run');
        $classId = $this->option('class');
        $semesterId = $this->option('semester');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        // Build the query
        $query = ClassModel::query();
        
        if ($classId) {
            $query->where('id', $classId);
        }
        
        $classes = $query->get();
        
        if ($classes->isEmpty()) {
            $this->error('No classes found matching the criteria.');
            return 1;
        }
        
        $this->info("Found {$classes->count()} classes to process...");
        $this->newLine();
        
        $updatedCount = 0;
        $errors = 0;
        
        // Create progress bar
        $bar = $this->output->createProgressBar($classes->count());
        $bar->start();
        
        foreach ($classes as $class) {
            try {
                // For each class, we need to get the correct semester
                // If semester is specified, use that, otherwise get the most recent semester for the class
                if ($semesterId) {
                    $targetSemester = $semesterId;
                } else {
                    // Get the most recent semester where this class has enrollments
                    $targetSemester = Enrollment::where('class_id', $class->id)
                        ->orderBy('created_at', 'desc')
                        ->value('semester_id');
                }
                
                if (!$targetSemester) {
                    $this->newLine();
                    $this->warn("Class {$class->id} ({$class->name}) has no enrollments, setting count to 0");
                    if (!$dryRun) {
                        $class->update(['students_count' => 0]);
                    }
                    $updatedCount++;
                    $bar->advance();
                    continue;
                }
                
                // Count unique enrolled students for this class and semester
                $actualCount = Enrollment::where('class_id', $class->id)
                    ->where('semester_id', $targetSemester)
                    ->where('status', 'enrolled')
                    ->distinct('student_code')
                    ->count();
                
                $currentCount = $class->students_count ?? 0;
                
                if ($actualCount !== $currentCount) {
                    if ($dryRun) {
                        $this->newLine();
                        $this->info("Would update Class {$class->id} ({$class->name} Section {$class->section}): {$currentCount} → {$actualCount}");
                    } else {
                        $class->update(['students_count' => $actualCount]);
                        $this->newLine();
                        $this->info("Updated Class {$class->id} ({$class->name} Section {$class->section}): {$currentCount} → {$actualCount}");
                    }
                    $updatedCount++;
                }
                
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error processing class {$class->id}: " . $e->getMessage());
                $errors++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Summary
        if ($dryRun) {
            $this->info("DRY RUN COMPLETE:");
            $this->info("- Classes that would be updated: {$updatedCount}");
        } else {
            $this->info("OPERATION COMPLETE:");
            $this->info("- Classes processed: {$classes->count()}");
            $this->info("- Classes updated: {$updatedCount}");
        }
        
        if ($errors > 0) {
            $this->error("- Errors encountered: {$errors}");
            return 1;
        }
        
        $this->info("✓ All operations completed successfully!");
        
        return 0;
    }
}