<main id="main-content" class="cd-public-container" style="padding-block:48px">
<h1>A good fit starts with a conversation.</h1><p>Contact ClipperDesk about business fit, setup, pricing or migration.</p>
@if($page['props']['receipt'])
<p>Your enquiry is saved. You do not need to send it again.</p><p>Reference: {{ $page['props']['receipt']['reference'] }}</p>
<form method="post" action="{{ route('marketing.contact.new') }}">@csrf<button type="submit">Send another enquiry</button></form>
@else
<form method="post" action="{{ route('marketing.contact.store') }}">@csrf
<input type="hidden" name="submission_id" value="{{ $page['props']['submissionId'] }}">
@foreach(['name' => 'Your name', 'email' => 'Email address', 'business' => 'Business name (optional)'] as $key => $label)
<p><label for="fallback-{{ $key }}">{{ $label }}</label><input id="fallback-{{ $key }}" name="{{ $key }}" type="{{ $key === 'email' ? 'email' : 'text' }}" value="{{ old($key) }}" @required($key !== 'business') maxlength="{{ $key === 'email' ? 254 : 100 }}"></p>
@endforeach
<p><label for="fallback-industry">Business type</label><select id="fallback-industry" name="industry"><option value="">Choose your business type</option>@foreach($page['props']['industries'] as $industry)<option value="{{ $industry['value'] }}" @selected(old('industry', $page['props']['selectedIndustry']) === $industry['value'])>{{ $industry['label'] }}</option>@endforeach</select></p>
<p><label for="fallback-message">How can we help?</label><textarea id="fallback-message" name="message" minlength="15" maxlength="5000" required>{{ old('message') }}</textarea></p>
@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
<p>Business requirements only. Please leave out client records, health details and passwords.</p>
<p>We use your details to respond to your enquiry. <a href="{{ route('policy.show') }}">Privacy policy</a></p><button type="submit">Send your enquiry</button>
</form>
@endif
<a href="{{ route('marketing.solutions') }}">Explore industries</a>
</main>
