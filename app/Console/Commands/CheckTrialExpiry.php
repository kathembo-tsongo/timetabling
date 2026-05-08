<?php
namespace App\Console\Commands;

use App\Mail\TrialExpiringMail;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckTrialExpiry extends Command
{
    protected $signature   = 'tenants:check-trials';
    protected $description = 'Send trial expiry warnings to tenants';

    public function handle()
    {
        // Send warning 7 days before trial ends
        $tenants7 = Tenant::where('billing_status', 'trial')
            ->whereDate('trial_ends_at', now()->addDays(7)->toDateString())
            ->get();

        foreach ($tenants7 as $tenant) {
            if ($tenant->billing_email) {
                Mail::to($tenant->billing_email)->send(new TrialExpiringMail($tenant, 7));
                $this->info("Sent 7-day warning to {$tenant->name}");
            }
        }

        // Send warning 1 day before trial ends
        $tenants1 = Tenant::where('billing_status', 'trial')
            ->whereDate('trial_ends_at', now()->addDay()->toDateString())
            ->get();

        foreach ($tenants1 as $tenant) {
            if ($tenant->billing_email) {
                Mail::to($tenant->billing_email)->send(new TrialExpiringMail($tenant, 1));
                $this->info("Sent 1-day warning to {$tenant->name}");
            }
        }

        // Suspend expired trials
        $expired = Tenant::where('billing_status', 'trial')
            ->whereDate('trial_ends_at', '<', now()->toDateString())
            ->get();

        foreach ($expired as $tenant) {
            $tenant->update([
                'billing_status' => 'suspended',
                'is_active'      => false,
            ]);
            $this->warn("Suspended expired trial: {$tenant->name}");
        }

        $this->info('Trial check complete!');
    }
}
