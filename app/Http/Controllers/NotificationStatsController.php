<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificationStatsController extends Controller
{
    /**
     * Display notification statistics.
     */
    public function index()
    {
        // Get notification counts by day for the last 30 days
        $dailyStats = DB::table('notification_logs')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful'),
                DB::raw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed')
            )
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
            
        // Get notification counts by channel
        $channelStats = DB::table('notification_logs')
            ->select(
                'channel',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful'),
                DB::raw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed')
            )
            ->groupBy('channel')
            ->get();
            
        // Get notification counts by type
        $typeStats = DB::table('notification_logs')
            ->select(
                'notification_type',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful'),
                DB::raw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed')
            )
            ->groupBy('notification_type')
            ->get()
            ->map(function ($item) {
                // Clean up the notification type name
                $item->notification_type = str_replace('App\\Notifications\\', '', $item->notification_type);
                return $item;
            });
            
        // Get recent failures with error messages
        $recentFailures = DB::table('notification_logs')
            ->where('success', 0)
            ->whereNotNull('error_message')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        // Get overall statistics
        $overallStats = [
            'total' => DB::table('notification_logs')->count(),
            'successful' => DB::table('notification_logs')->where('success', 1)->count(),
            'failed' => DB::table('notification_logs')->where('success', 0)->count(),
        ];
        
        $overallStats['success_rate'] = $overallStats['total'] > 0 
            ? round(($overallStats['successful'] / $overallStats['total']) * 100, 2) 
            : 0;
            
        return Inertia::render('Notifications/Statistics', [
            'dailyStats' => $dailyStats,
            'channelStats' => $channelStats,
            'typeStats' => $typeStats,
            'recentFailures' => $recentFailures,
            'overallStats' => $overallStats,
            'can' => [
                'view_stats' => auth()->user()->can('view-notification-stats'),
            ],
        ]);
    }
}
