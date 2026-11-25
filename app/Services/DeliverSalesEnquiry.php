<?php

namespace App\Services;

use App\Mail\SalesEnquiryMail;
use App\Models\SalesEnquiry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverSalesEnquiry
{
    public function send(SalesEnquiry $enquiry): void
    {
        // Atomic claim prevents concurrent submissions from sending twice.
        if (! SalesEnquiry::whereKey($enquiry->id)->where('delivery_status', 'pending')->update(['delivery_status' => 'sending'])) {
            return;
        }
        try {
            Mail::to(config('sales-contact.to'))->bcc(config('sales-contact.bcc'))->send(new SalesEnquiryMail($enquiry));
            $enquiry->update(['delivery_status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $enquiry->update(['delivery_status' => 'failed']);
            // Keep private message content and provider credentials out of logs.
            Log::error('Sales enquiry email requires operator attention.', ['reference' => $enquiry->reference, 'exception_type' => $exception::class]);
        }
    }
}
