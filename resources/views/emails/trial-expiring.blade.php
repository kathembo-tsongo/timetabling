<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f59e0b; color: white; padding: 30px; border-radius: 8px 8px 0 0; text-align: center; }
        .body { background: #f9f9f9; padding: 30px; border: 1px solid #e5e7eb; }
        .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 8px 8px; }
        .btn { display: inline-block; background: #1a56db; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; margin: 20px 0; }
        .warning { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 16px; margin: 16px 0; font-weight: bold; text-align: center; font-size: 18px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>⏰ Trial Expiring Soon</h1>
        <p>{{ $tenant->name }}</p>
    </div>
    <div class="body">
        <div class="warning">
            Your trial expires in {{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }}
        </div>

        <p>Don't lose access to your timetabling system. Upgrade now to keep using all features.</p>

        <p><strong>Available plans:</strong></p>
        <ul>
            <li><strong>Pro — KES 15,000/month:</strong> 500 users, 5 schools, exam scheduling</li>
            <li><strong>Enterprise — KES 45,000/month:</strong> Unlimited everything + custom domain</li>
        </ul>

        <p>Payment methods: M-Pesa, Bank Transfer, PayPal</p>

        <a href="http://{{ $tenant->domain ?? $tenant->slug . '.yoursaas.com' }}/billing" class="btn">
            Upgrade now →
        </a>
    </div>
    <div class="footer">
        <p>© {{ date('Y') }} Timetabling SaaS</p>
    </div>
</body>
</html>
