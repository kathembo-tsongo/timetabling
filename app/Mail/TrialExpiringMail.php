<?php
namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Tenant $tenant, public int $daysLeft) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "⏰ Your trial expires in {$this->daysLeft} days");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.trial-expiring');
    }
}
