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
<body style="margin:0;padding:0;background-color:#f3f5fa;color:#172554;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">{{ $presentation['status'] }}{{ filled($values['business_name'] ?? null) ? ' · '.$values['business_name'] : '' }}. {{ $presentation['intro'] }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f5fa;">
<tr><td class="email-outer" align="center" style="padding:40px 20px;">
    <!--[if mso]><table role="presentation" width="600" align="center"><tr><td><![endif]-->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;text-align:left;">
        <tr><td style="padding:0 4px 26px;">
            @if ($logo)<img src="{{ $logo }}" width="169" height="43" alt="{{ config('brand.product_name', 'ClipperDesk') }}" style="display:block;border:0;max-width:100%;object-fit:contain;">@else<strong style="font-size:24px;">{{ config('brand.product_name', 'ClipperDesk') }}</strong>@endif
            @if (! $logo)<p style="margin:10px 0 0;font-size:12px;color:#64748b;">{{ config('brand.tagline') }}</p>@endif
        </td></tr>
        <tr><td style="background-color:#ffffff;border:1px solid #dfe4ef;border-radius:16px;overflow:hidden;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="height:5px;background-color:#4338ca;border-radius:16px 16px 0 0;font-size:1px;line-height:5px;">&nbsp;</td></tr>
                <tr><td class="email-content" style="padding:36px;">
                    <p style="margin:0 0 20px;font-size:11px;line-height:18px;font-weight:bold;color:#4338ca;letter-spacing:1px;text-transform:uppercase;">{{ $presentation['status'] }}</p>
                    <h1 class="email-title" style="margin:0 0 16px;font-size:32px;line-height:1.2;letter-spacing:-0.8px;color:#172554;">{{ $structured ? $presentation['title'] : $subject }}</h1>
                    @if ($structured)
                        <p style="margin:0 0 10px;font-size:15px;line-height:24px;color:#334155;">Hi {{ $values['client_name'] ?? 'there' }},</p>
                        <p style="margin:0 0 24px;font-size:15px;line-height:24px;color:#475569;">{{ $presentation['intro'] }}</p>
                        @if (filled($values['business_name'] ?? null))<p style="margin:0 0 14px;font-size:15px;font-weight:bold;color:#172554;">{{ $values['business_name'] }}</p>@endif
                        @if ($appointment && (filled($values['appointment_date'] ?? null) || filled($values['appointment_time'] ?? null)))
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f3ff;border:1px solid #e0e4fb;border-radius:10px;margin-bottom:18px;"><tr><td style="padding:20px;">
                            <p style="margin:0 0 8px;font-size:11px;line-height:16px;font-weight:bold;color:#5753a0;letter-spacing:0.7px;">{{ $presentation['status'] === 'Cancelled' ? 'CANCELLED APPOINTMENT' : 'YOUR APPOINTMENT' }}</p>
                            <p style="margin:0;font-size:20px;line-height:28px;font-weight:bold;color:#172554;">{{ $values['appointment_date'] ?? '' }}</p>
                            <p style="margin:6px 0 0;font-size:18px;line-height:26px;color:#172554;">{{ $values['appointment_time'] ?? '' }}@if(filled($values['time_zone'] ?? null))<span style="font-size:12px;color:#5753a0;"> · {{ $values['time_zone'] }}</span>@endif</p>
                        </td></tr></table>
                        @endif
                        @if (filled($values['amount'] ?? null) && in_array($presentation['status'], ['Receipt ready', 'Deposit received', 'Deposit information'], true))
                            <p style="margin:0 0 18px;padding:20px;background-color:#f1f3ff;border-radius:10px;"><span style="display:block;font-size:11px;line-height:18px;color:#5753a0;">{{ $presentation['status'] === 'Deposit information' ? 'REQUESTED DEPOSIT' : 'RECORDED AMOUNT' }}</span><strong style="font-size:28px;line-height:38px;color:#172554;">{{ $values['currency'] ?? '' }} {{ $values['amount'] }}</strong></p>
                        @endif
                        @if ($details)
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:20px;table-layout:fixed;">
                            @foreach($details as $label => $value)<tr><td class="email-detail-label" width="125" valign="top" style="width:125px;padding:10px 12px 10px 0;border-bottom:1px solid #edf0f5;font-size:12px;line-height:20px;color:#64748b;">{{ $label }}</td><td valign="top" style="padding:10px 0;border-bottom:1px solid #edf0f5;font-size:13px;line-height:20px;color:#172554;overflow-wrap:anywhere;word-break:break-word;">{{ $value }}</td></tr>@endforeach
                        </table>
                        @endif
                        @if ($appointment && (filled($values['location_address'] ?? null) || filled($values['arrival_instructions'] ?? null)))
                            <h2 style="margin:24px 0 10px;font-size:15px;line-height:22px;color:#172554;">Getting there</h2>
                            @if(filled($values['location_address'] ?? null))<p style="margin:0 0 8px;font-size:13px;line-height:22px;color:#475569;overflow-wrap:anywhere;">{{ $values['location_address'] }}</p>@endif
                            @if(filled($values['arrival_instructions'] ?? null))<p style="margin:0 0 12px;font-size:13px;line-height:22px;color:#475569;overflow-wrap:anywhere;">{{ $values['arrival_instructions'] }}</p>@endif
                        @endif
                        @if ($directions)<p style="margin:0 0 22px;"><a href="{{ $directions }}" style="font-size:13px;line-height:22px;color:#4338ca;text-decoration:underline;">Get directions</a></p>@endif
                        @if ($action)
                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:26px;"><tr><td bgcolor="#4338ca" style="background-color:#4338ca;border-radius:8px;text-align:center;mso-padding-alt:15px 24px;"><a href="{{ $action }}" style="display:inline-block;padding:15px 24px;font-size:14px;line-height:20px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;">{{ $presentation['action'] }}</a></td></tr></table>
                        <p style="margin:12px 0 0;font-size:11px;line-height:18px;color:#64748b;">No account needed. This secure link is personal to you.</p>
                        @endif
                    @else
                        <p style="margin:0;font-size:15px;line-height:25px;color:#475569;overflow-wrap:anywhere;">{!! nl2br(e($text)) !!}</p>
                    @endif
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;border-top:1px solid #edf0f5;"><tr><td style="padding-top:20px;font-size:12px;line-height:20px;color:#64748b;">Questions about your visit? Contact {{ $values['business_name'] ?? 'the business' }} directly.<br>Please don’t reply to this automated email.</td></tr></table>
                </td></tr>
            </table>
        </td></tr>
        <tr><td align="center" style="padding:24px 16px 0;font-size:11px;line-height:19px;color:#64748b;">
            Sent by <strong style="color:#172554;">{{ config('brand.product_name', 'ClipperDesk') }}</strong>@if(filled($values['business_name'] ?? null)) for {{ $values['business_name'] }}@endif.<br>
            Appointment and service updates, thoughtfully delivered.
        </td></tr>
    </table>
    <!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>
