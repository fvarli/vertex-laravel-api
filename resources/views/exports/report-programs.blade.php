@extends('exports.report-layout')

@section('content')
<div class="totals">
    <span>Total: {{ $report['totals']['total'] }}</span>
    <span>Active: {{ $report['totals']['active'] }}</span>
    <span>Draft: {{ $report['totals']['draft'] }}</span>
    <span>Archived: {{ $report['totals']['archived'] }}</span>
</div>

<table>
    <thead>
        <tr>
            <th>Period</th>
            <th>Total</th>
            <th>Active</th>
            <th>Draft</th>
            <th>Archived</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['buckets'] as $bucket)
        <tr>
            <td>{{ $bucket['bucket'] }}</td>
            <td>{{ $bucket['total'] }}</td>
            <td>{{ $bucket['active'] }}</td>
            <td>{{ $bucket['draft'] }}</td>
            <td>{{ $bucket['archived'] }}</td>
        </tr>
        @empty
        <tr><td colspan="5">No data available.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
