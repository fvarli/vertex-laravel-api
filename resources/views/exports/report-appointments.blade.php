@extends('exports.report-layout')

@section('content')
<div class="totals">
    <span>Total: {{ $report['totals']['total'] }}</span>
    <span>Planned: {{ $report['totals']['planned'] }}</span>
    <span>Done: {{ $report['totals']['done'] }}</span>
    <span>Cancelled: {{ $report['totals']['cancelled'] }}</span>
    <span>No Show: {{ $report['totals']['no_show'] }}</span>
</div>

<table>
    <thead>
        <tr>
            <th>Period</th>
            <th>Total</th>
            <th>Planned</th>
            <th>Done</th>
            <th>Cancelled</th>
            <th>No Show</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['buckets'] as $bucket)
        <tr>
            <td>{{ $bucket['bucket'] }}</td>
            <td>{{ $bucket['total'] }}</td>
            <td>{{ $bucket['planned'] }}</td>
            <td>{{ $bucket['done'] }}</td>
            <td>{{ $bucket['cancelled'] }}</td>
            <td>{{ $bucket['no_show'] }}</td>
        </tr>
        @empty
        <tr><td colspan="6">No data available.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
