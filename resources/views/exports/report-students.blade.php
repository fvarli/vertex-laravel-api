@extends('exports.report-layout')

@section('content')
<div class="totals">
    <span>Total: {{ $report['totals']['total'] }}</span>
    <span>Active: {{ $report['totals']['active'] }}</span>
    <span>Passive: {{ $report['totals']['passive'] }}</span>
    <span>New in Range: {{ $report['totals']['new_in_range'] }}</span>
</div>

<table>
    <thead>
        <tr>
            <th>Period</th>
            <th>Total</th>
            <th>Active</th>
            <th>Passive</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['buckets'] as $bucket)
        <tr>
            <td>{{ $bucket['bucket'] }}</td>
            <td>{{ $bucket['total'] }}</td>
            <td>{{ $bucket['active'] }}</td>
            <td>{{ $bucket['passive'] }}</td>
        </tr>
        @empty
        <tr><td colspan="4">No data available.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
