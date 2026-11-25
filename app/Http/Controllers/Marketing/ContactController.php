<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\SalesEnquiry;
use App\Services\DeliverSalesEnquiry;
use App\Support\Seo\PublicPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $token = $request->session()->get('sales_contact_token') ?? (string) Str::uuid();
        $request->session()->put('sales_contact_token', $token);
        $industry = $request->string('industry')->toString();

        return PublicPage::render('Marketing/Contact', [
            'submissionId' => $token,
            'selectedIndustry' => array_key_exists($industry, config('frontsite.solutions')) ? $industry : '',
            'industries' => collect(config('frontsite.solutions'))->map(fn ($item, $slug) => ['value' => $slug, 'label' => $item['label']])->values(),
            'receipt' => $request->session()->get('sales_contact_receipt'),
            'seo' => ['title' => 'Contact ClipperDesk | Setup, Pricing & Business Fit', 'description' => 'Tell us about your business. Get help choosing ClipperDesk, planning setup or discussing migration before you make the move.', 'canonical' => route('marketing.contact')],
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function createAnother(Request $request)
    {
        $request->session()->forget(['sales_contact_token', 'sales_contact_receipt']);

        return redirect()->route('marketing.contact');
    }

    public function store(Request $request, DeliverSalesEnquiry $delivery)
    {
        $data = $request->validate([
            'submission_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'email:rfc', 'max:254', 'not_regex:/[\r\n]/'],
            'business' => ['nullable', 'string', 'max:160'],
            'industry' => ['nullable', Rule::in(array_keys(config('frontsite.solutions')))],
            'message' => ['required', 'string', 'min:15', 'max:5000'],
            'website' => ['nullable', 'string', 'max:0'],
        ], [
            'name.required' => 'Enter your name so we know who to reply to.',
            'email.required' => 'Enter the email address where we can reach you.',
            'email.email' => 'Enter a valid email address, such as name@example.com.',
            'message.required' => 'Tell us what you would like help with.',
            'message.min' => 'Please add a little more detail (at least 15 characters).',
            'message.max' => 'Please keep your message within 5,000 characters.',
        ]);
        abort_unless(hash_equals((string) $request->session()->get('sales_contact_token', ''), $data['submission_id']), 419);
        $existing = SalesEnquiry::where('reference', $data['submission_id'])->first();
        if (! $existing) {
            $key = 'sales-contact:'.hash('sha256', $request->ip());
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw ValidationException::withMessages(['message' => 'Please wait '.RateLimiter::availableIn($key).' seconds before sending another enquiry. Your text is still here.', 'retry_after' => (string) RateLimiter::availableIn($key)]);
            }
            RateLimiter::hit($key, 600);
        }
        $enquiry = $existing ?? SalesEnquiry::firstOrCreate(['reference' => $data['submission_id']], collect($data)->only(['name', 'email', 'business', 'industry', 'message'])->all());
        $delivery->send($enquiry);
        $request->session()->put('sales_contact_receipt', ['reference' => $enquiry->reference, 'delayed' => $enquiry->fresh()->delivery_status !== 'sent']);

        return redirect()->route('marketing.contact');
    }
}
