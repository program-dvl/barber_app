<?php

namespace App\Mail;

use App\Models\SalesEnquiry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SalesEnquiryMail extends Mailable
{
    public function __construct(public SalesEnquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ClipperDesk website enquiry · '.$this->enquiry->reference,
            replyTo: [new Address($this->enquiry->email, $this->enquiry->name)]);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.sales-enquiry');
    }
}
