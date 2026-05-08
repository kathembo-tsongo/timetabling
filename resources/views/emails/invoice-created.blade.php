<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a56db; color: white; padding: 30px; border-radius: 8px 8px 0 0; text-align: center; }
        .body { background: #f9f9f9; padding: 30px; border: 1px solid #e5e7eb; }
        .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 8px 8px; }
        .btn { display: inline-block; background: #16a34a; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; margin: 20px 0; }
        .invoice-box { background: white; border: 2px solid #1a56db; border-radius: 6px; padding: 20px; margin: 16px 0; }
        .amount { font-size: 32px; font-weight: bold; color: #1a56db; }
        .payment-box { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 16px; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoice #{{ $invoice->invoice_number }}</h1>
        <p>Payment required by {{ $invoice->due_date->format('M d, Y') }}</p>
    </div>
    <div class="body">
        <div class="invoice-box">
            <p><strong>Invoice details:</strong></p>
            Plan: {{ ucfirst($invoice->plan) }}<br>
            <div class="amount">KES {{ number_format($invoice->amount) }}</div>
            Due date: {{ $invoice->due_date->format('M d, Y') }}<br>
            Payment method: {{ strtoupper($invoice->payment_method) }}
        </div>

        <div class="payment-box">
            <strong>Payment instructions:</strong><br>
            @if($invoice->payment_method === 'mpesa')
                Send KES {{ number_format($invoice->amount) }} to M-Pesa Till: <strong>123456</strong><br>
                Use your university name as reference.
            @elseif($invoice->payment_method === 'bank')
                Transfer KES {{ number_format($invoice->amount) }} to:<br>
                Bank: Equity Bank<br>
                Account: 1234567890<br>
                Branch: Nairobi
            @else
                Send USD {{ number_format($invoice->amount / 130) }} to: payments@yoursaas.com via PayPal
            @endif
        </div>

        <p>After payment, submit your payment reference in the billing portal:</p>
        <a href="http://yoursaas.com/billing" class="btn">Submit Payment Reference →</a>
    </div>
    <div class="footer">
        <p>© {{ date('Y') }} Timetabling SaaS</p>
    </div>
</body>
</html>
