<?php
namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Mail\InvoiceCreatedMail;
use App\Mail\PlanActivatedMail;
use Illuminate\Support\Facades\Mail;

class BillingController extends Controller
{
    // Plans configuration
    public static function plans(): array
    {
        return [
            'free' => [
                'name'        => 'Free',
                'price_kes'   => 0,
                'price_usd'   => 0,
                'users'       => 50,
                'schools'     => 1,
                'features'    => ['Basic timetabling', '1 school', 'Up to 50 users', 'Email support'],
            ],
            'pro' => [
                'name'        => 'Pro',
                'price_kes'   => 15000,
                'price_usd'   => 99,
                'users'       => 500,
                'schools'     => 5,
                'features'    => ['Advanced timetabling', 'Up to 5 schools', 'Up to 500 users', 'Priority support', 'Exam scheduling', 'Analytics'],
            ],
            'enterprise' => [
                'name'        => 'Enterprise',
                'price_kes'   => 45000,
                'price_usd'   => 299,
                'users'       => -1,
                'schools'     => -1,
                'features'    => ['Unlimited schools', 'Unlimited users', 'Custom domain', 'Dedicated support', 'SLA guarantee', 'Custom integrations', 'API access'],
            ],
        ];
    }

    // Tenant billing page
    public function index()
    {
        $tenant   = tenant();
        $invoices = Invoice::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Billing/Index', [
            'tenant'   => $tenant,
            'invoices' => $invoices,
            'plans'    => self::plans(),
        ]);
    }

    // Request plan upgrade
    public function requestUpgrade(Request $request)
    {
        $request->validate([
            'plan'           => 'required|in:free,pro,enterprise',
            'payment_method' => 'required|in:mpesa,bank,paypal',
            'billing_email'  => 'required|email',
            'billing_phone'  => 'nullable|string',
            'billing_cycle'  => 'required|in:monthly,annual',
        ]);

        $tenant = tenant();
        $plans  = self::plans();
        $plan   = $plans[$request->plan];

        $amount = $request->billing_cycle === 'annual'
            ? $plan['price_kes'] * 10  // 2 months free on annual
            : $plan['price_kes'];

        DB::beginTransaction();
        try {
            // Update tenant billing info
            $tenant->update([
                'billing_email'  => $request->billing_email,
                'billing_phone'  => $request->billing_phone,
                'billing_amount' => $amount,
                'billing_cycle'  => $request->billing_cycle,
                'payment_method' => $request->payment_method,
            ]);

            // Create invoice
            $invoice = Invoice::create([
                'tenant_id'      => $tenant->id,
                'invoice_number' => Invoice::generateNumber(),
                'status'         => 'pending',
                'plan'           => $request->plan,
                'amount'         => $amount,
                'currency'       => 'KES',
                'payment_method' => $request->payment_method,
                'due_date'       => now()->addDays(7),
                'notes'          => "Plan upgrade to {$plan['name']} ({$request->billing_cycle})",
            ]);

            // Send invoice email
            if ($tenant->billing_email) {
                Mail::to($tenant->billing_email)->send(new InvoiceCreatedMail($invoice));
            }

            DB::commit();

            return back()->with('success',
                "Upgrade request submitted! Invoice #{$invoice->invoice_number} created. " .
                $this->getPaymentInstructions($request->payment_method, $amount)
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to process upgrade request.');
        }
    }

    // Confirm payment (tenant submits payment reference)
    public function confirmPayment(Request $request, Invoice $invoice)
    {
        $request->validate([
            'payment_reference' => 'required|string|max:100',
        ]);

        $invoice->update([
            'payment_reference' => $request->payment_reference,
            'notes'             => $invoice->notes . "\nPayment reference submitted: " . $request->payment_reference,
        ]);

        return back()->with('success',
            'Payment reference submitted! Our team will verify and activate your plan within 24 hours.'
        );
    }

    private function getPaymentInstructions(string $method, float $amount): string
    {
        return match($method) {
            'mpesa'  => "Send KES " . number_format($amount) . " to M-Pesa Till: 123456. Use your university name as reference.",
            'bank'   => "Transfer KES " . number_format($amount) . " to Equity Bank A/C: 1234567890. Branch: Nairobi.",
            'paypal' => "Send USD " . number_format($amount / 130) . " to payments@yoursaas.com via PayPal.",
            default  => "Contact us to arrange payment.",
        };
    }

    // ── Super Admin billing management ────────────────────────────────────

    public function adminIndex()
    {
        $invoices = Invoice::with('tenant')
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'total_revenue'   => Invoice::where('status', 'paid')->sum('amount'),
            'pending_revenue' => Invoice::where('status', 'pending')->sum('amount'),
            'paid_count'      => Invoice::where('status', 'paid')->count(),
            'pending_count'   => Invoice::where('status', 'pending')->count(),
        ];

        return Inertia::render('SuperAdmin/Billing', [
            'invoices' => $invoices,
            'stats'    => $stats,
            'plans'    => self::plans(),
        ]);
    }

    public function adminMarkPaid(Request $request, Invoice $invoice)
    {
        $request->validate([
            'payment_reference' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $invoice->update([
                'status'            => 'paid',
                'paid_at'           => now(),
                'payment_reference' => $request->payment_reference ?? $invoice->payment_reference,
            ]);

            // Activate the plan on the tenant
            $invoice->tenant->update([
                'plan'             => $invoice->plan,
                'billing_status'   => 'active',
                'last_payment_at'  => now(),
                'next_billing_at'  => $invoice->tenant->billing_cycle === 'annual'
                    ? now()->addYear()
                    : now()->addMonth(),
            ]);

            // Send plan activated email
            if ($invoice->tenant->billing_email) {
                Mail::to($invoice->tenant->billing_email)->send(new PlanActivatedMail($invoice->tenant, $invoice));
            }

            DB::commit();

            return back()->with('success',
                "Invoice #{$invoice->invoice_number} marked as paid. {$invoice->tenant->name} upgraded to {$invoice->plan} plan!"
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to mark invoice as paid.');
        }
    }

    public function adminSuspend(Tenant $tenant)
    {
        $tenant->update([
            'billing_status' => 'suspended',
            'is_active'      => false,
            'suspended_at'   => now(),
        ]);

        return back()->with('success', "{$tenant->name} has been suspended due to non-payment.");
    }
}
