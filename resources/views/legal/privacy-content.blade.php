<div class="cd-privacy">
        <section class="cd-privacy-hero">
            <div class="cd-public-container">
                <nav aria-label="Breadcrumb"><a href="{{ route('marketing.home') }}">Home</a><span aria-hidden="true"> / </span><span aria-current="page">Privacy Policy</span></nav>
                <p class="cd-eyebrow">Trust and legal</p>
                <h1>Privacy Policy</h1>
                <p class="cd-privacy-summary">{{ $seo['description'] }}</p>
                <dl class="cd-privacy-dates">
                    <div><dt>Last updated</dt><dd><time datetime="{{ config('privacy.updated_at') }}">{{ \Carbon\CarbonImmutable::parse(config('privacy.updated_at'))->format('F j, Y') }}</time></dd></div>
                    @if($published)
                        <div><dt>Effective date</dt><dd><time datetime="{{ config('privacy.effective_at') }}">{{ \Carbon\CarbonImmutable::parse(config('privacy.effective_at'))->format('F j, Y') }}</time></dd></div>
                    @endif
                    <div><dt>Version</dt><dd>{{ config('privacy.version') }}</dd></div>
                </dl>
            @unless($published)
                <p class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-slate-800" role="status">This privacy policy is under review and is not yet effective.</p>
            @endunless
            </div>
        </section>
        <div class="cd-public-container cd-privacy-layout">
            <nav class="cd-privacy-contents" aria-label="On this page">
                <details>
                    <summary><span>On this page</span><span class="cd-privacy-contents-hint">Browse sections <span aria-hidden="true">⌄</span></span></summary>
                    <ol>
                        @foreach($sections as $section)
                            <li><a href="#{{ $section['id'] }}">{{ $section['label'] }}</a></li>
                        @endforeach
                        <li><a href="#contact-us">Contact us</a></li>
                    </ol>
                </details>
            </nav>
            <article class="cd-privacy-copy" aria-label="Clipperdesk Privacy Policy">
                {!! $html !!}
                <h2 id="contact-us" tabindex="-1">Contact us</h2>
                <p>For privacy requests, Google account unlinking or deletion requests, and concerns about information Clipperdesk controls:</p>
                <address>
                    <strong>{{ config('privacy.operator_name') }}</strong><br>
                    {{ config('privacy.operator_address') }}<br>
                    Email: <a href="mailto:{{ config('privacy.privacy_email') }}">{{ config('privacy.privacy_email') }}</a>
                </address>
                <p>For customer information controlled by a business using Clipperdesk, contact that business using the contact details on its booking page or service records.</p>
                @if(config('privacy.processing_countries'))
                    <h3>Processing locations</h3>
                    <p>{{ config('privacy.processing_countries') }}</p>
                @endif
                @if(collect(config('privacy.providers'))->filter()->isNotEmpty())
                    <h3>Operating service providers</h3>
                    <p>Google handles optional sign-in and Stripe handles enabled payment services. The following providers support the indicated operations:</p>
                    <dl class="cd-privacy-providers">
                        @foreach(['hosting' => 'Hosting', 'storage' => 'Data and file storage', 'account_email' => 'Account email', 'client_email' => 'Business-enabled client email', 'sms' => 'Business-enabled SMS', 'monitoring' => 'Operational monitoring'] as $key => $label)
                            @if(config('privacy.providers.'.$key))
                                <div><dt>{{ $label }}</dt><dd>{{ config('privacy.providers.'.$key) }}</dd></div>
                            @endif
                        @endforeach
                    </dl>
                @endif
                <nav class="cd-privacy-related" aria-label="Related legal information">
                    <a href="{{ route('terms.show') }}">Terms of Service</a>
                    <a href="{{ route('refund.show') }}">Refund Policy</a>
                    <a href="{{ route('marketing.security') }}">Security</a>
                    <a href="#main-content">Back to top</a>
                </nav>
            </article>
        </div>
</div>
