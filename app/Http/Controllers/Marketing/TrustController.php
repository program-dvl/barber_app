<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Support\Seo\PublicPage;
use Inertia\Response;

class TrustController extends Controller
{
    public function company(): Response
    {
        return PublicPage::render('Marketing/Company', [
            'legalIdentity' => $this->legalIdentity(),
            'seo' => [
                'title' => 'About ClipperDesk | Service business management software',
                'description' => 'Learn how ClipperDesk connects appointments, client records, team schedules and recorded sales for independent professionals and growing service businesses.',
                'canonical' => route('marketing.company'),
                'image' => url('/images/marketing/editorial/company-story.webp'),
            ],
        ]);
    }

    public function security(): Response
    {
        return PublicPage::render('Marketing/Security', [
            'legalIdentity' => $this->legalIdentity(),
            'controls' => [
                ['title' => 'Separate business records', 'body' => 'Your business records are kept separate from other workspaces. Team members can access only the locations and records permitted by their role.'],
                ['title' => 'Account protection', 'body' => 'Sign in with email and password or Google when enabled. Optional authenticator verification and recovery codes add protection to your account.'],
                ['title' => 'Private links and attachments', 'body' => 'Appointment and form links expire and allow only their intended actions. Client photos and PDFs are restricted to authorised team members. Do not upload files from untrusted sources.'],
                ['title' => 'A history of important changes', 'body' => 'Sensitive changes record who made them. Payment and refund adjustments keep the original transaction history.'],
            ],
            'seo' => [
                'title' => 'Account and data security | ClipperDesk',
                'description' => 'Understand ClipperDesk business data separation, role-based team access, account protection, private appointment links and payment change history.',
                'canonical' => route('marketing.security'),
                'image' => url('/images/marketing/editorial/security-trust.webp'),
            ],
        ]);
    }

    private function legalIdentity(): array
    {
        return collect(config('privacy'))->only(['operator_name', 'operator_address', 'privacy_email'])->all();
    }
}
