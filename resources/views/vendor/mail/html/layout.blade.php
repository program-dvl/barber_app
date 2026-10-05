<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('brand.product_name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light" />
<meta name="supported-color-schemes" content="light" />
<style>
@media only screen and (max-width: 680px) {
.header,
.body {
padding-left: 12px !important;
padding-right: 12px !important;
}

.header {
padding-top: 18px !important;
}

.header-shell,
.inner-body,
.footer {
width: 100% !important;
}

.content-cell {
padding: 34px 28px 32px !important;
}
}

@media only screen and (max-width: 500px) {
h1 {
font-size: 25px !important;
}

.brand-context {
display: none !important;
}

.button {
text-align: center !important;
width: 100% !important;
}

.content-cell {
padding-left: 22px !important;
padding-right: 22px !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="640" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
