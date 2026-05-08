<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #16a34a; color: white; padding: 30px; border-radius: 8px 8px 0 0; text-align: center; }
        .body { background: #f9f9f9; padding: 30px; border: 1px solid #e5e7eb; }
        .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 8px 8px; }
        .btn { display: inline-block; background: #1a56db; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; margin: 20px 0; }
        .feature-list { background: white; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Plan Activated!</h1>
        <p>{{ $tenant->name }} is now on the {{ ucfirst($invoice->plan) }} plan</p>
    </div>
    <div class="body">
        <p>Great news! Your payment has been verified and your {{ ucfirst($invoice->plan) }} plan is now active.</p>

        <div class="feature-list">
            <strong>What's included in your {{ ucfirst($invoice->plan) }} plan:</strong>
            @if($invoice->plan === 'pro')
            <ul>
                <li>Up to 500 users</li>
                <li>Up to 5 schools</li>
                <li>Exam timetabling</li>
                <li>Lecturer assignments</li>
                <li>Analytics dashboard</li>
                <li>Priority support</li>
            </ul>
            @elseif($invoice->plan === 'enterprise')
            <ul>
                <li>Unlimited users & schools</li>
                <li>All Pro features</li>
                <li>Custom domain</li>
                <li>API access</li>
                <li>Dedicated support</li>
                <li>SLA guarantee</li>
            </ul>
            @endif
        </div>

        <p>Next billing date: {{ $tenant->next_billing_at?->format('M d, Y') ?? 'N/A' }}</p>

        <a href="http://{{ $tenant->domain ?? $tenant->slug . '.yoursaas.com' }}/admin" class="btn">
            Go to dashboard →
        </a>
    </div>
    <div class="footer">
        <p>© {{ date('Y') }} Timetabling SaaS</p>
    </div>
</body>
</html>
