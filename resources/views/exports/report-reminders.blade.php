@extends('exports.report-layout')

@section('content')
<div class="totals">
    <span>Total: {{ $report['totals']['total'] }}</span>
    <span>Sent: {{ $report['totals']['sent'] }}</span>
    <span>Missed: {{ $report['totals']['missed'] }}</span>
    <span>Send Rate: {{ $report['totals']['send_rate'] }}%</span>
</div>

<table>
    <thead>
        <tr>
            <th>Period</th>
            <th>Total</th>
            <th>Sent</th>
            <th>Pending</th>
            <th>Ready</th>
            <th>Failed</th>
            <th>Missed</th>
            <th>Escalated</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['buckets'] as $bucket)
        <tr>
            <td>{{ $bucket['bucket'] }}</td>
            <td>{{ $bucket['total'] }}</td>
            <td>{{ $bucket['sent'] }}</td>
            <td>{{ $bucket['pending'] }}</td>
            <td>{{ $bucket['ready'] }}</td>
            <td>{{ $bucket['failed'] }}</td>
            <td>{{ $bucket['missed'] }}</td>
            <td>{{ $bucket['escalated'] }}</td>
        </tr>
        @empty
        <tr><td colspan="8">No data available.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
