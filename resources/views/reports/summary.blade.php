<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $business->name }} — {{ str($report['report_key'])->headline() }}</title>
    <style>
        @include('brand.document-styles')
        body { margin: 32px; }
        h1 { margin-bottom: 4px; } .meta { color: #64748b; margin-bottom: 24px; }
        .totals { margin-top: 20px; } @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
<div class="cd-document-label">{{ config('brand.product_name') }} report</div>
<h1>{{ str($report['report_key'])->headline() }}</h1>
<div class="meta">{{ $business->name }} · {{ $report['filters']['start_date'] }} to {{ $report['filters']['end_date'] }} · {{ $report['time_zone'] }} · Fresh {{ $report['fresh_at'] }} · Definition {{ $report['metric_version'] }}</div>
<table>
    <thead><tr>@foreach($report['columns'] as $column)<th>{{ str($column)->headline() }}</th>@endforeach</tr></thead>
    <tbody>@forelse($report['rows'] as $row)<tr>@foreach($report['columns'] as $column)<td>{{ is_scalar($row[$column] ?? null) ? $row[$column] : json_encode($row[$column] ?? null) }}</td>@endforeach</tr>@empty<tr><td colspan="99">No source records match these filters.</td></tr>@endforelse</tbody>
</table>
<div class="totals"><strong>Reconciled totals:</strong> {{ collect($report['totals'])->map(fn($value, $key) => str($key)->headline().': '.$value)->implode(' · ') }}</div>
</body>
</html>
