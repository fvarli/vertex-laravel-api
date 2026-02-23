<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Reminder\BulkReminderActionRequest;
use App\Http\Requests\Api\V1\Reminder\ListReminderRequest;
use App\Http\Requests\Api\V1\Reminder\RequeueReminderRequest;
use App\Http\Resources\AppointmentReminderResource;
use App\Models\AppointmentReminder;
use App\Services\AppointmentReminderService;
use App\Services\DomainAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReminderController extends BaseController
{
    private const AUDIT_FIELDS = [
        'status',
        'attempt_count',
        'last_attempted_at',
        'next_retry_at',
        'escalated_at',
        'failure_reason',
        'opened_at',
        'marked_sent_at',
        'marked_sent_by_user_id',
    ];

    public function __construct(
        private readonly DomainAuditService $auditService,
        private readonly AppointmentReminderService $reminderService,
    ) {}

    public function index(ListReminderRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');
        $trainerUserId = $workspaceRole !== WorkspaceRole::OwnerAdmin->value ? $request->user()->id : null;

        $reminders = $this->reminderService->listReminders($workspaceId, $trainerUserId, $request->validated());

        return $this->sendResponse(AppointmentReminderResource::collection($reminders)->response()->getData(true));
    }

    public function exportCsv(ListReminderRequest $request): StreamedResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');
        $trainerUserId = $workspaceRole !== WorkspaceRole::OwnerAdmin->value ? $request->user()->id : null;

        $fileName = 'reminders_export_'.now()->format('Ymd_His').'.csv';
        $rows = $this->reminderService->listForExport($workspaceId, $trainerUserId, $request->validated());

        $response = response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id',
                'appointment_id',
                'student_id',
                'trainer_user_id',
                'scheduled_for',
                'status',
                'attempt_count',
                'next_retry_at',
                'escalated_at',
                'failure_reason',
            ]);

            foreach ($rows as $reminder) {
                fputcsv($handle, [
                    $reminder->id,
                    $reminder->appointment_id,
                    $reminder->appointment?->student_id,
                    $reminder->appointment?->trainer_user_id,
                    optional($reminder->scheduled_for)->toDateTimeString(),
                    $reminder->status,
                    $reminder->attempt_count,
                    optional($reminder->next_retry_at)->toDateTimeString(),
                    optional($reminder->escalated_at)->toDateTimeString(),
                    $reminder->failure_reason,
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);

        return $response;
    }

    public function open(AppointmentReminder $reminder): JsonResponse
    {
        $this->authorize('update', $reminder);

        $before = $reminder->toArray();
        $reminder = $this->reminderService->openReminder($reminder);

        $this->auditService->record(
            request: request(),
            event: 'appointment.reminder_opened',
            auditable: $reminder,
            before: $before,
            after: $reminder->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new AppointmentReminderResource($reminder), __('api.reminder.opened'));
    }

    public function markSent(AppointmentReminder $reminder): JsonResponse
    {
        $this->authorize('update', $reminder);

        $before = $reminder->toArray();
        $reminder = $this->reminderService->markSent($reminder, request()->user()->id);

        $this->auditService->record(
            request: request(),
            event: 'appointment.reminder_marked_sent',
            auditable: $reminder,
            before: $before,
            after: $reminder->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new AppointmentReminderResource($reminder), __('api.reminder.marked_sent'));
    }

    public function cancel(AppointmentReminder $reminder): JsonResponse
    {
        $this->authorize('update', $reminder);

        $before = $reminder->toArray();
        $reminder = $this->reminderService->cancelReminder($reminder);

        $this->auditService->record(
            request: request(),
            event: 'appointment.reminder_cancelled',
            auditable: $reminder,
            before: $before,
            after: $reminder->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new AppointmentReminderResource($reminder), __('api.reminder.cancelled'));
    }

    public function requeue(RequeueReminderRequest $request, AppointmentReminder $reminder): JsonResponse
    {
        $this->authorize('update', $reminder);

        $before = $reminder->toArray();
        $reminder = $this->reminderService->requeue($reminder, $request->validated('failure_reason'));

        $this->auditService->record(
            request: $request,
            event: 'appointment.reminder_requeued',
            auditable: $reminder,
            before: $before,
            after: $reminder->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new AppointmentReminderResource($reminder->load('appointment')), __('api.reminder.requeued'));
    }

    public function bulk(BulkReminderActionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $action = (string) $validated['action'];

        $reminders = AppointmentReminder::query()
            ->with('appointment')
            ->whereIn('id', $validated['ids'])
            ->get();

        $affected = 0;
        $items = Collection::make();

        foreach ($reminders as $reminder) {
            $this->authorize('update', $reminder);

            $before = $reminder->toArray();

            if ($action === 'mark-sent') {
                $reminder = $this->reminderService->markSent($reminder, $request->user()->id);
            } elseif ($action === 'cancel') {
                $reminder = $this->reminderService->cancelReminder($reminder);
            } elseif ($action === 'requeue') {
                $reminder = $this->reminderService->requeue($reminder, $validated['failure_reason'] ?? null);
            }

            $fresh = $reminder->refresh()->load('appointment');
            $this->auditService->record(
                request: $request,
                event: 'appointment.reminder_bulk_action',
                auditable: $fresh,
                before: $before,
                after: $fresh->toArray(),
                allowedFields: self::AUDIT_FIELDS,
            );

            $affected++;
            $items->push($fresh);
        }

        return $this->sendResponse([
            'affected' => $affected,
            'action' => $action,
            'items' => AppointmentReminderResource::collection($items),
        ], __('api.reminder.bulk_applied'));
    }
}
