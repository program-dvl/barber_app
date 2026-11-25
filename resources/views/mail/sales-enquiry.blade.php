<h1>New ClipperDesk enquiry</h1>
<p>Reference: {{ $enquiry->reference }}</p>
<p>From: {{ $enquiry->name }} · {{ $enquiry->email }}</p>
<p>Business: {{ $enquiry->business ?: 'Not provided' }}</p>
<p>Industry: {{ config('frontsite.solutions.'.$enquiry->industry.'.label', 'Not specified') }}</p>
<p style="white-space:pre-wrap">{{ $enquiry->message }}</p>
<p>Reply to this email to contact the sender.</p>
