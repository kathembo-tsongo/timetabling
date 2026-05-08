<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {{ $tenant->primary_color ?? '#1a56db' }}; color: white; padding: 30px; border-radius: 8px 8px 0 0; text-align: center; }
        .body { background: #f9f9f9; padding: 30px; border: 1px solid #e5e7eb; }
        .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 8px 8px; }
        .btn { display: inline-block; background: #1a56db; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; margin: 20px 0; }
        .info-box { background: white; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to the Timetabling System!</h1>
        <p>{{ $tenant->name }} is all set up</p>
    </div>
    <div class="body">
        <p>Hello,</p>
        <p>Your university timetabling system is ready. Here's what you can do next:</p>

        <div class="info-box">
            <strong>Your account details:</strong><br>
            University: {{ $tenant->name }}<br>
            Plan: {{ ucfirst($tenant->plan) }}<br>
            URL: {{ $tenant->domain ?? $tenant->slug . '.yoursaas.com' }}<br>
            Trial ends: {{ $tenant->trial_ends_at ? $tenant->trial_ends_at->format('M d, Y') : '30 days from today' }}
        </div>

        <p><strong>Getting started:</strong></p>
        <ol>
            <li>Log in with your admin credentials</li>
            <li>Add your schools and programs</li>
            <li>Import or add students and lecturers</li>
            <li>Create semesters and start timetabling</li>
        </ol>

        <a href="http://{{ $tenant->domain ?? $tenant->slug . '.yoursaas.com' }}/login" class="btn">
            Go to your dashboard →
        </a>
    </div>
    <div class="footer">
        <p>© {{ date('Y') }} Timetabling SaaS. All rights reserved.</p>
        <p>If you need help, reply to this email.</p>
    </div>
</body>
</html>
