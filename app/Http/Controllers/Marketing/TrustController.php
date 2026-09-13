<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TrustController extends Controller
{
    public function company(): Response
    {
        return Inertia::render('Marketing/Company', [
            'seo' => [
                'title' => 'About ClipperDesk service business software',
                'description' => 'Learn why ClipperDesk connects booking, daily operations and reporting for appointment-led service businesses—and see the limits kept in view.',
                'canonical' => route('marketing.company'),
                'image' => url('/images/marketing/editorial/company-story.webp'),
            ],
        ]);
    }

    public function security(): Response
    {
        return Inertia::render('Marketing/Security', [
            'controls' => [
                ['title' => 'Tenant and permission boundaries', 'body' => 'Business-owned records are resolved through tenant context, membership permissions and server-side entitlement checks; identifiers alone do not grant access.', 'state' => 'Implemented and covered by isolation tests'],
                ['title' => 'Account security', 'body' => 'Launch authentication uses verified email/password plus optional TOTP and recovery codes. Platform administration additionally requires verified email and confirmed TOTP.', 'state' => 'Implemented; independent identity audit pending'],
                ['title' => 'Sensitive links and files', 'body' => 'Appointment, form and file actions use purpose-bound expiring tokens. Private attachments use tenant-scoped storage and expiring delivery.', 'state' => 'Implemented; malware scanning remains a launch blocker'],
                ['title' => 'Change and financial history', 'body' => 'Sensitive changes produce audit evidence, and completed commerce corrections use append-only or compensating records rather than erasing history.', 'state' => 'Implemented and tested locally'],
                ['title' => 'Provider and operational assurance', 'body' => 'Stripe, Resend and Twilio production credentials, webhooks, sender identities and settlement/delivery paths require target-environment certification.', 'state' => 'Not production-certified'],
                ['title' => 'Recovery and independent review', 'body' => 'Production backup/restore, disaster recovery, on-call monitoring, penetration testing and independent accessibility/security review are not yet evidenced.', 'state' => 'Open launch controls'],
            ],
            'seo' => [
                'title' => 'Service business software security | ClipperDesk',
                'description' => 'Review ClipperDesk tenant isolation, account security, audit history and sensitive-link controls alongside current assurance limits.',
                'canonical' => route('marketing.security'),
                'image' => url('/images/marketing/editorial/security-trust.webp'),
            ],
        ]);
    }
}
