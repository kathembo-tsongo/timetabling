<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyNotificationLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:verify-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the notification_logs table and test direct inserts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Verifying notification_logs table...');

        // Check if the table exists
        if (!Schema::hasTable('notification_logs')) {
            $this->error('The notification_logs table does not exist!');
            return 1;
        }

        // Get table structure
        $columns = Schema::getColumnListing('notification_logs');
        $this->info('Table columns: ' . implode(', ', $columns));

        // Check for required columns
        $requiredColumns = ['notification_type', 'notifiable_type', 'notifiable_id', 'channel', 'success', 'data', 'created_at', 'updated_at'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (!empty($missingColumns)) {
            $this->error('Missing required columns: ' . implode(', ', $missingColumns));
            return 1;
        }

        // Test direct insert with query builder
        $this->info('Testing insert with query builder...');
        try {
            $id = DB::table('notification_logs')->insertGetId([
                'notification_type' => 'Test\\Verification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => 1,
                'channel' => 'test',
                'success' => true,
                'data' => json_encode(['test' => true]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $this->info("Insert successful! Record ID: {$id}");
            
            // Verify the record exists
            $record = DB::table('notification_logs')->find($id);
            if ($record) {
                $this->info("Record verified in database.");
                
                // Clean up
                DB::table('notification_logs')->where('id', $id)->delete();
                $this->info("Test record deleted.");
            } else {
                $this->error("Record not found after insert!");
            }
        } catch (\Exception $e) {
            $this->error("Insert failed: " . $e->getMessage());
            return 1;
        }
        
        // Test direct SQL insert
        $this->info('Testing direct SQL insert...');
        try {
            $result = DB::insert(
                'INSERT INTO notification_logs (notification_type, notifiable_type, notifiable_id, channel, success, data, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'Test\\DirectSQL',
                    'App\\Models\\User',
                    1,
                    'test',
                    true,
                    json_encode(['test' => true]),
                    now(),
                    now()
                ]
            );
            
            if ($result) {
                $this->info("Direct SQL insert successful!");
                
                // Find the record
                $record = DB::table('notification_logs')
                    ->where('notification_type', 'Test\\DirectSQL')
                    ->orderBy('id', 'desc')
                    ->first();
                    
                if ($record) {
                    $this->info("Record verified in database. ID: {$record->id}");
                    
                    // Clean up
                    DB::table('notification_logs')->where('id', $record->id)->delete();
                    $this->info("Test record deleted.");
                } else {
                    $this->error("Record not found after direct SQL insert!");
                }
            } else {
                $this->error("Direct SQL insert failed!");
            }
        } catch (\Exception $e) {
            $this->error("Direct SQL insert failed: " . $e->getMessage());
            return 1;
        }
        
        $this->info('Verification complete! The notification_logs table appears to be working correctly.');
        return 0;
    }
}
