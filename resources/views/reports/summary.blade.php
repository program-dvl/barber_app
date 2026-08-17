<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $business->name }} — {{ str($report['report_key'])->headline() }}</title>
    <style>
        body { color: #19201f; font: 13px/1.45 system-ui, sans-serif; margin: 32px; }
        h1 { margin-bottom: 4px; } .meta { color: #59625f; margin-bottom: 24px; }
        table { border-collapse: collapse; width: 100%; } th, td { border-bottom: 1px solid #d8d4cc; padding: 7px; text-align: left; vertical-align: top; }
        th { background: #f6f1e8; } .totals { margin-top: 20px; } @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
<h1>{{ str($report['report_key'])->headline() }}</h1>
<div class="meta">{{ $business->name }} · {{ $report['filters']['start_date'] }} to {{ $report['filters']['end_date'] }} · {{ $report['time_zone'] }} · Fresh {{ $report['fresh_at'] }} · Definition {{ $report['metric_version'] }}</div>
<table>
    <thead><tr>@foreach($report['columns'] as $column)<th>{{ str($column)->headline() }}</th>@endforeach</tr></thead>
    <tbody>@forelse($report['rows'] as $row)<tr>@foreach($report['columns'] as $column)<td>{{ is_scalar($row[$column] ?? null) ? $row[$column] : json_encode($row[$column] ?? null) }}</td>@endforeach</tr>@empty<tr><td colspan="99">No source records match these filters.</td></tr>@endforelse</tbody>
</table>
<div class="totals"><strong>Reconciled totals:</strong> {{ collect($report['totals'])->map(fn($value, $key) => str($key)->headline().': '.$value)->implode(' · ') }}</div>
</body>
</html>
