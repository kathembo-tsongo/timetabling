<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExamTimetable;
use Illuminate\Support\Facades\Log;

class TestExamUpdate extends Command
{
    protected $signature = 'exams:test-observer {exam_id?}';
    protected $description = 'Test the ExamTimetable observer by updating an exam';

    public function handle()
    {
        $examId = $this->argument('exam_id');
        
        if (!$examId) {
            // Get the first exam
            $exam = ExamTimetable::first();
            if (!$exam) {
                $this->error('No exams found in the database.');
                return 2;
            }
            $examId = $exam->id;
        } else {
            $exam = ExamTimetable::find($examId);
            if (!$exam) {
                $this->error("Exam with ID {$examId} not found.");
                return 2;
            }
        }
        
        $this->info("Testing observer with exam ID: {$examId}");
        $this->info("Current venue: {$exam->venue}");
        
        // Make a small change to trigger the observer
        $newVenue = $exam->venue . ' (Updated)';
        $exam->venue = $newVenue;
        $exam->save();
        
        $this->info("Updated venue to: {$newVenue}");
        $this->info("Observer should have been triggered. Check logs and notification_logs table.");
        
        return 0;
    }
}