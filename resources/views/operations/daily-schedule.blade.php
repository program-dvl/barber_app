<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $business->name }} daily schedule</title>
    <style>
        body { color: #19201f; font-family: Arial, sans-serif; margin: 2rem; }
        h1 { margin-bottom: .25rem; } .meta { color: #4b5d59; margin-top: 0; }
        table { border-collapse: collapse; width: 100%; } th, td { border-bottom: 1px solid #cbd5d1; padding: .6rem; text-align: left; }
        .cue { font-weight: 700; } @media print { button { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button type="button" onclick="window.print()">Print schedule</button>
    <h1>{{ $business->name }}</h1>
    <p class="meta">{{ $location->name }} · {{ $date->format('l, j F Y') }} · {{ $location->time_zone }}</p>
    <table>
        <thead><tr><th>Time</th><th>Client / block</th><th>Services</th><th>Status</th><th>Staff</th></tr></thead>
        <tbody>
        @forelse ($calendar['events'] as $event)
            <tr>
                <td>{{ \Carbon\CarbonImmutable::parse($event['startsAt'])->format('H:i') }}@if($event['endsAt'])–{{ \Carbon\CarbonImmutable::parse($event['endsAt'])->format('H:i') }}@endif</td>
                <td>{{ $event['title'] }}</td><td>{{ implode(', ', array_column($event['services'] ?? [], 'name')) }}</td>
                <td><span class="cue">{{ $event['statusCue'] }}</span> — {{ $event['statusLabel'] }}</td>
                <td>{{ implode(', ', array_column($event['staff'] ?? [], 'name')) }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No scheduled work.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
