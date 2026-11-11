<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $subject }}</title>
    <style>
        @media only screen and (max-width: 620px) {
            .email-outer { padding: 20px 12px !important; }
            .email-content { padding: 28px 22px !important; }
            .email-title { font-size: 28px !important; }
            .email-detail-label { width: 100px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#faf9f5;color:#202d29;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">{{ $presentation['status'] }}{{ filled($values['business_name'] ?? null) ? ' · '.$values['business_name'] : '' }}. {{ $presentation['intro'] }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#faf9f5;">
<tr><td class="email-outer" align="center" style="padding:40px 20px;">
    <!--[if mso]><table role="presentation" width="600" align="center"><tr><td><![endif]-->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;text-align:left;">
        <tr><td style="padding:0 4px 26px;">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                @include('emails.partials.marketplace-brand', ['url' => rtrim(config('brand.website_url'), '/').'/marketplace'])
            </tr></table>
        </td></tr>
        <tr><td style="background-color:#ffffff;border:1px solid #dedfd6;border-radius:16px;overflow:hidden;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="height:5px;background-color:#304c3c;border-radius:16px 16px 0 0;font-size:1px;line-height:5px;">&nbsp;</td></tr>
                <tr><td class="email-content" style="padding:36px;">
                    <p style="margin:0 0 20px;font-size:11px;line-height:18px;font-weight:bold;color:#304c3c;letter-spacing:1px;text-transform:uppercase;">{{ $presentation['status'] }}</p>
                    <h1 class="email-title" style="margin:0 0 16px;font-size:32px;line-height:1.2;letter-spacing:-0.8px;color:#202d29;">{{ $structured ? $presentation['title'] : $subject }}</h1>
                    @if ($structured)
                        <p style="margin:0 0 10px;font-size:15px;line-height:24px;color:#39473e;">Hi {{ $values['client_name'] ?? 'there' }},</p>
                        <p style="margin:0 0 24px;font-size:15px;line-height:24px;color:#58664f;">{{ $presentation['intro'] }}</p>
                        @if (filled($values['business_name'] ?? null))<p style="margin:0 0 14px;font-size:15px;font-weight:bold;color:#202d29;">{{ $values['business_name'] }}</p>@endif
                        @if ($appointment && (filled($values['appointment_date'] ?? null) || filled($values['appointment_time'] ?? null)))
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#edf3e5;border:1px solid #dce5d1;border-radius:10px;margin-bottom:18px;"><tr><td style="padding:20px;">
                            <p style="margin:0 0 8px;font-size:11px;line-height:16px;font-weight:bold;color:#61754d;letter-spacing:0.7px;">{{ $presentation['status'] === 'Cancelled' ? 'CANCELLED APPOINTMENT' : 'YOUR APPOINTMENT' }}</p>
                            <p style="margin:0;font-size:20px;line-height:28px;font-weight:bold;color:#202d29;">{{ $values['appointment_date'] ?? '' }}</p>
                            <p style="margin:6px 0 0;font-size:18px;line-height:26px;color:#202d29;">{{ $values['appointment_time'] ?? '' }}@if(filled($values['time_zone'] ?? null))<span style="font-size:12px;color:#61754d;"> · {{ $values['time_zone'] }}</span>@endif</p>
                        </td></tr></table>
                        @endif
                        @if (filled($values['amount'] ?? null) && in_array($presentation['status'], ['Receipt ready', 'Deposit received', 'Deposit information'], true))
                            <p style="margin:0 0 18px;padding:20px;background-color:#edf3e5;border-radius:10px;"><span style="display:block;font-size:11px;line-height:18px;color:#61754d;">{{ $presentation['status'] === 'Deposit information' ? 'REQUESTED DEPOSIT' : 'RECORDED AMOUNT' }}</span><strong style="font-size:28px;line-height:38px;color:#202d29;">{{ $values['currency'] ?? '' }} {{ $values['amount'] }}</strong></p>
                        @endif
                        @if ($details)
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:20px;table-layout:fixed;">
                            @foreach($details as $label => $value)<tr><td class="email-detail-label" width="125" valign="top" style="width:125px;padding:10px 12px 10px 0;border-bottom:1px solid #e3e8dc;font-size:12px;line-height:20px;color:#68765f;">{{ $label }}</td><td valign="top" style="padding:10px 0;border-bottom:1px solid #e3e8dc;font-size:13px;line-height:20px;color:#202d29;overflow-wrap:anywhere;word-break:break-word;">{{ $value }}</td></tr>@endforeach
                        </table>
                        @endif
                        @if ($appointment && (filled($values['location_address'] ?? null) || filled($values['arrival_instructions'] ?? null)))
                            <h2 style="margin:24px 0 10px;font-size:15px;line-height:22px;color:#202d29;">Getting there</h2>
                            @if(filled($values['location_address'] ?? null))<p style="margin:0 0 8px;font-size:13px;line-height:22px;color:#58664f;overflow-wrap:anywhere;">{{ $values['location_address'] }}</p>@endif
                            @if(filled($values['arrival_instructions'] ?? null))<p style="margin:0 0 12px;font-size:13px;line-height:22px;color:#58664f;overflow-wrap:anywhere;">{{ $values['arrival_instructions'] }}</p>@endif
                        @endif
                        @if ($directions)<p style="margin:0 0 22px;"><a href="{{ $directions }}" style="font-size:13px;line-height:22px;color:#304c3c;text-decoration:underline;">Get directions</a></p>@endif
                        @if ($action)
                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:26px;"><tr><td bgcolor="#304c3c" style="background-color:#304c3c;border-radius:8px;text-align:center;mso-padding-alt:15px 24px;"><a href="{{ $action }}" style="display:inline-block;padding:15px 24px;font-size:14px;line-height:20px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;">{{ $presentation['action'] }}</a></td></tr></table>
                        <p style="margin:12px 0 0;font-size:11px;line-height:18px;color:#68765f;">No account needed. This secure link is personal to you.</p>
                        @endif
                    @else
                        <p style="margin:0;font-size:15px;line-height:25px;color:#58664f;overflow-wrap:anywhere;">{!! nl2br(e($text)) !!}</p>
                    @endif
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;border-top:1px solid #e3e8dc;"><tr><td style="padding-top:20px;font-size:12px;line-height:20px;color:#68765f;">@if(filled($values['unsubscribe_link'] ?? null))<a href="{{ $values['unsubscribe_link'] }}" style="color:#58664f;display:inline-block;padding:12px 0;">Stop review emails</a><br>@endif Questions about your visit? Contact {{ $values['business_name'] ?? 'the business' }} directly.<br>Please don’t reply to this automated email.</td></tr></table>
                </td></tr>
            </table>
        </td></tr>
        <tr><td align="center" style="padding:24px 16px 0;font-size:11px;line-height:19px;color:#68765f;">
            Sent by <strong style="color:#202d29;">{{ config('brand.marketplace_email_name') }}</strong>@if(filled($values['business_name'] ?? null)) for {{ $values['business_name'] }}@endif.<br>
            Appointment and service updates, thoughtfully delivered.
        </td></tr>
    </table>
    <!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>
