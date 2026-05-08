<?php
namespace App\Mail;

use App\Models\Tenant;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlanActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Tenant $tenant, public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "🎉 Your {$this->invoice->plan} plan is now active!");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.plan-activated');
    }
}
