<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function toCsv(array $data, array $columns, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($data, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_values($columns));

            foreach ($data as $row) {
                $line = [];
                foreach ($columns as $key => $label) {
                    $line[] = $row[$key] ?? '';
                }
                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function toPdf(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data);

        return $pdf->download($filename);
    }

    public function flattenAppointments(array $reportData): array
    {
        $rows = [];
        foreach ($reportData['buckets'] as $bucket) {
            $rows[] = [
                'bucket' => $bucket['bucket'],
                'total' => $bucket['total'],
                'planned' => $bucket['planned'],
                'done' => $bucket['done'],
                'cancelled' => $bucket['cancelled'],
                'no_show' => $bucket['no_show'],
            ];
        }

        return $rows;
    }

    public function flattenStudents(array $reportData): array
    {
        $rows = [];
        foreach ($reportData['buckets'] as $bucket) {
            $rows[] = [
                'bucket' => $bucket['bucket'],
                'total' => $bucket['total'],
                'active' => $bucket['active'],
                'passive' => $bucket['passive'],
            ];
        }

        return $rows;
    }

    public function flattenPrograms(array $reportData): array
    {
        $rows = [];
        foreach ($reportData['buckets'] as $bucket) {
            $rows[] = [
                'bucket' => $bucket['bucket'],
                'total' => $bucket['total'],
                'active' => $bucket['active'],
                'draft' => $bucket['draft'],
                'archived' => $bucket['archived'],
            ];
        }

        return $rows;
    }

    public function flattenReminders(array $reportData): array
    {
        $rows = [];
        foreach ($reportData['buckets'] as $bucket) {
            $rows[] = [
                'bucket' => $bucket['bucket'],
                'total' => $bucket['total'],
                'sent' => $bucket['sent'],
                'pending' => $bucket['pending'],
                'ready' => $bucket['ready'],
                'failed' => $bucket['failed'],
                'missed' => $bucket['missed'],
                'escalated' => $bucket['escalated'],
            ];
        }

        return $rows;
    }

    public function flattenTrainerPerformance(array $reportData): array
    {
        return $reportData['trainers'];
    }

    public function appointmentColumns(): array
    {
        return [
            'bucket' => 'Period',
            'total' => 'Total',
            'planned' => 'Planned',
            'done' => 'Done',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
        ];
    }

    public function studentColumns(): array
    {
        return [
            'bucket' => 'Period',
            'total' => 'Total',
            'active' => 'Active',
            'passive' => 'Passive',
        ];
    }

    public function programColumns(): array
    {
        return [
            'bucket' => 'Period',
            'total' => 'Total',
            'active' => 'Active',
            'draft' => 'Draft',
            'archived' => 'Archived',
        ];
    }

    public function reminderColumns(): array
    {
        return [
            'bucket' => 'Period',
            'total' => 'Total',
            'sent' => 'Sent',
            'pending' => 'Pending',
            'ready' => 'Ready',
            'failed' => 'Failed',
            'missed' => 'Missed',
            'escalated' => 'Escalated',
        ];
    }

    public function trainerPerformanceColumns(): array
    {
        return [
            'trainer_name' => 'Trainer',
            'total_students' => 'Total Students',
            'active_students' => 'Active Students',
            'total_appointments' => 'Total Appointments',
            'completed_appointments' => 'Completed',
            'no_show_count' => 'No Show',
            'cancellation_count' => 'Cancelled',
            'completion_rate' => 'Completion Rate %',
            'active_programs' => 'Active Programs',
            'reminders_sent' => 'Reminders Sent',
            'reminder_success_rate' => 'Reminder Success %',
        ];
    }
}
