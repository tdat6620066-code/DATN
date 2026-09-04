<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Notification $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: strip_tags($this->alert->title).' - SmashZone');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer-alert');
    }
}
