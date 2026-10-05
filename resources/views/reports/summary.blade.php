<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $business->name }} · {{ $meta['title'] }}</title>
    <style>
        @include('brand.document-styles')
        body { margin: 32px auto; max-width: 1120px; padding: 0 24px; }
        h1 { margin: 8px 0; font-size: 26px; } .meta, .note { color: #475569; font-size: 12px; }
        .scope { border-bottom: 1px solid #cbd5e1; padding-bottom: 16px; margin-bottom: 20px; overflow-wrap: anywhere; }
        .metrics { display: flex; flex-wrap: wrap; gap: 12px; margin: 20px 0; } .metric { border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; flex: 1; min-width: 180px; } .metric strong { display: block; font-size: 22px; margin: 4px 0; }
        table { font-size: 11px; table-layout: fixed; } td { overflow-wrap: anywhere; } th,td { padding: 8px; } .number { text-align: right; font-variant-numeric: tabular-nums; } thead { display: table-header-group; } tr { break-inside: avoid; }
        dl { font-size: 11px; } dt { font-weight: bold; margin-top: 10px; } dd { margin: 2px 0; color: #475569; } footer { margin-top: 24px; color: #475569; font-size: 11px; }
        .tools { display: flex; gap: 12px; margin-bottom: 24px; } .tools button { font: inherit; padding: 8px 16px; border: 1px solid #cbd5e1; background: white; border-radius: 8px; cursor: pointer; }
        @page { size: A4 landscape; margin: 12mm; } @media print { body { margin: 0; padding: 0; max-width: none; } .tools { display: none; } .metric { break-inside: avoid; } }
    </style>
</head>
<body>
@php
    $label = fn ($key) => ['collected_minor' => 'Net receipts', 'net_minor' => in_array($report['report_key'], ['sales','overview','tax','location']) ? 'Sales less returns' : 'After discount (before returns)', 'gross_minor' => 'Recorded sales', 'row_count' => 'Matching records', 'average_ticket_minor' => 'Average sale value', 'allocated_refund_minor' => 'Item returns', 'available_minutes' => 'Configured capacity', 'recorded_minutes' => 'Recorded service time', 'booked_minutes' => 'Occupied within windows', 'free_minutes' => 'Unused capacity'][$key] ?? ucfirst(str_replace('_', ' ', preg_replace('/_(minor|percent)$/', '', $key)));
    $format = function ($column, $value) use ($report) {
        if ($value === null || $value === '') return '—';
        if (str_ends_with($column, '_minor')) return $report['currency_code'].' '.\App\Domain\Reporting\Services\ReportMoney::decimal($value, $report['currency_code']);
        if (str_ends_with($column, '_percent')) return number_format($value, 1).'%';
        if (str_ends_with($column, '_minutes')) return number_format($value, 1).' min';
        if (str_ends_with($column, '_at')) return \Carbon\CarbonImmutable::parse($value, 'UTC')->setTimezone($report['time_zone'])->format('j M Y, H:i');
        if (in_array($column, ['sale','appointment','transaction','client','entry'])) return '…'.substr((string) $value, -8);
        return is_scalar($value) ? str_replace('_', ' ', (string) $value) : '—';
    };
    $columns = array_values(array_intersect($meta['columns'], $report['columns']));
    $metrics = array_slice(array_values(array_intersect($meta['metrics'], array_keys($report['totals']))), 0, 4);
@endphp
<div class="tools"><button type="button" onclick="window.print()">Print / save PDF</button><a href="{{ route('business.reports.index', ['business'=>$business->public_id, 'report'=>$report['report_key'], ...$report['filters']]) }}">Back to Reports</a></div>
<div class="cd-document-label">{{ config('brand.product_name') }} · {{ $business->name }}</div>
<h1>{{ $meta['title'] }}</h1><p>{{ $meta['description'] }}</p>
<div class="scope"><p class="meta">{{ $report['filters']['start_date'] }} – {{ $report['filters']['end_date'] }} · {{ $report['time_zone'] }} · {{ $report['currency_code'] }} · Loaded {{ \Carbon\CarbonImmutable::parse($report['fresh_at'])->setTimezone($report['time_zone'])->format('j M Y, H:i') }}</p><p class="meta">Locations: {{ $scopeNames['locations'] }} · Staff: {{ $scopeNames['staff'] }} · Services: {{ $scopeNames['services'] }}@if($report['filters']['search']) · Search: {{ $report['filters']['search'] }}@endif @if($report['filters']['method']) · Method: {{ $report['filters']['method'] }}@endif @if($report['filters']['statuses']) · Status: {{ implode(', ', $report['filters']['statuses']) }}@endif @if($report['filters']['dimension_value']) · {{ ucfirst($report['filters']['dimension']) }}: {{ $report['filters']['dimension_value'] }}@endif</p></div>
<div class="metrics">@foreach($metrics as $metric)<div class="metric">{{ $label($metric) }}<strong>{{ $format($metric,$report['totals'][$metric]) }}</strong>@if($report['previous_period'])<span class="note">Previous {{ $report['previous_period']['from'] }} – {{ $report['previous_period']['to'] }}: {{ $format($metric,$report['previous_period']['totals'][$metric] ?? null) }}</span>@endif</div>@endforeach</div>
<p class="note"><strong>Totals:</strong> Full filtered summary above; corresponding totals also appear below the record table.</p>
<h2>Underlying records</h2><p class="note">Showing {{ count($report['rows']) }} of {{ $report['totals']['row_count'] }} matching records. Summary totals cover the full filtered report. @if(count($report['rows']) < $report['totals']['row_count']) Export CSV for the complete record list. @endif</p>
<table><thead><tr>@foreach($columns as $column)<th scope="col" class="{{ str_ends_with($column,'_minor') ? 'number' : '' }}">{{ $label($column) }}</th>@endforeach</tr></thead><tbody>@forelse($report['rows'] as $row)<tr>@foreach($columns as $column)<td class="{{ str_ends_with($column,'_minor') ? 'number' : '' }}">{{ $format($column,$row[$column] ?? null) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ max(1,count($columns)) }}">No records match these filters.</td></tr>@endforelse</tbody><tfoot><tr>@foreach($columns as $column)<td class="{{ str_ends_with($column,'_minor') ? 'number' : '' }}"><strong>{{ array_key_exists($column,$report['totals']) ? $format($column,$report['totals'][$column]) : '—' }}</strong></td>@endforeach</tr></tfoot></table>
<h2 style="margin-top:24px">Metric definitions</h2><dl>@foreach($metrics as $metric)<dt>{{ $label($metric) }}</dt><dd>{{ $meta['definitions'][$metric] ?? 'Corresponding recorded values across all matching records.' }}</dd>@endforeach</dl>
@if($report['report_key']==='utilisation')<p class="note">Capacity uses retained schedule configuration. Historical configuration is not a frozen attendance snapshot. Recorded service time outside configured windows remains visible and is excluded from utilisation.</p>@endif
<footer>{{ $report['source'] }} · Definitions v{{ $report['metric_version'] }} · Permission-scoped report</footer>
</body></html>
