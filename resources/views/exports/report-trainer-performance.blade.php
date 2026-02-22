@extends('exports.report-layout')

@section('content')
<table>
    <thead>
        <tr>
            <th>Trainer</th>
            <th>Students</th>
            <th>Active</th>
            <th>Appointments</th>
            <th>Completed</th>
            <th>No Show</th>
            <th>Cancelled</th>
            <th>Rate %</th>
            <th>Programs</th>
            <th>Reminders</th>
            <th>Reminder %</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['trainers'] as $trainer)
        <tr>
            <td>{{ $trainer['trainer_name'] }}</td>
            <td>{{ $trainer['total_students'] }}</td>
            <td>{{ $trainer['active_students'] }}</td>
            <td>{{ $trainer['total_appointments'] }}</td>
            <td>{{ $trainer['completed_appointments'] }}</td>
            <td>{{ $trainer['no_show_count'] }}</td>
            <td>{{ $trainer['cancellation_count'] }}</td>
            <td>{{ $trainer['completion_rate'] }}</td>
            <td>{{ $trainer['active_programs'] }}</td>
            <td>{{ $trainer['reminders_sent'] }}</td>
            <td>{{ $trainer['reminder_success_rate'] }}</td>
        </tr>
        @empty
        <tr><td colspan="11">No data available.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
