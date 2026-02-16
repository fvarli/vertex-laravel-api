<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Reminder\BulkReminderActionRequest;
use App\Http\Requests\Api\V1\Reminder\ListReminderRequest;
use App\Http\Requests\Api\V1\Reminder\RequeueReminderRequest;
use App\Http\Resources\AppointmentReminderResource;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Services\AppointmentReminderService;
use App\Services\DomainAuditService;
use Carbon\Carbon;
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
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $reminders = $this->buildReminderQuery($request)
            ->orderBy('scheduled_for')
            ->paginate($perPage);

        return $this->sendResponse(AppointmentReminderResource::collection($reminders)->response()->getData(true));
    }

    public function exportCsv(ListReminderRequest $request): StreamedResponse
    {
        $fileName = 'reminders_export_'.now()->format('Ymd_His').'.csv';
        $rows = $this->buildReminderQuery($request)
            ->orderBy('scheduled_for')
            ->get();

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
        if ($this->reminderService->canTransition($reminder->status, AppointmentReminder::STATUS_READY)) {
            $reminder->update([
                'opened_at' => now()->utc(),
                'status' => AppointmentReminder::STATUS_READY,
            ]);
        }

        $reminder = $reminder->refresh()->load('appointment');

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
        $now = now()->utc();
        $userId = request()->user()->id;

        $reminder->update([
            'status' => AppointmentReminder::STATUS_SENT,
            'marked_sent_at' => $now,
            'marked_sent_by_user_id' => $userId,
            'last_attempted_at' => $now,
            'next_retry_at' => null,
            'escalated_at' => null,
        ]);

        Appointment::query()
            ->whereKey($reminder->appointment_id)
            ->update([
                'whatsapp_status' => Appointment::WHATSAPP_STATUS_SENT,
                'whatsapp_marked_at' => $now,
                'whatsapp_marked_by_user_id' => $userId,
            ]);

        $reminder = $reminder->refresh()->load('appointment');

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
        if ($this->reminderService->canTransition($reminder->status, AppointmentReminder::STATUS_CANCELLED)) {
            $reminder->update(['status' => AppointmentReminder::STATUS_CANCELLED]);
        }
        $reminder = $reminder->refresh()->load('appointment');

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
                $now = now()->utc();
                $userId = $request->user()->id;
                $reminder->update([
                    'status' => AppointmentReminder::STATUS_SENT,
                    'marked_sent_at' => $now,
                    'marked_sent_by_user_id' => $userId,
                    'last_attempted_at' => $now,
                    'next_retry_at' => null,
                    'escalated_at' => null,
                ]);

                Appointment::query()
                    ->whereKey($reminder->appointment_id)
                    ->update([
                        'whatsapp_status' => Appointment::WHATSAPP_STATUS_SENT,
                        'whatsapp_marked_at' => $now,
                        'whatsapp_marked_by_user_id' => $userId,
                    ]);
            } elseif ($action === 'cancel') {
                if ($this->reminderService->canTransition($reminder->status, AppointmentReminder::STATUS_CANCELLED)) {
                    $reminder->update(['status' => AppointmentReminder::STATUS_CANCELLED]);
                }
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

    private function buildReminderQuery(ListReminderRequest $request)
    {
        $validated = $request->validated();
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');
        $user = $request->user();
        $status = (string) ($validated['status'] ?? 'all');

        return AppointmentReminder::query()
            ->where('workspace_id', $workspaceId)
            ->with('appointment')
            ->whereHas('appointment', function ($query) use ($workspaceRole, $user, $validated) {
                if ($workspaceRole !== 'owner_admin') {
                    $query->where('trainer_user_id', $user->id);
                }
                if (isset($validated['trainer_id'])) {
                    $query->where('trainer_user_id', (int) $validated['trainer_id']);
                }
                if (isset($validated['student_id'])) {
                    $query->where('student_id', (int) $validated['student_id']);
                }
            })
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when(isset($validated['from']), fn ($query) => $query->where('scheduled_for', '>=', Carbon::parse($validated['from'])->utc()))
            ->when(isset($validated['to']), fn ($query) => $query->where('scheduled_for', '<=', Carbon::parse($validated['to'])->utc()))
            ->when((bool) ($validated['escalated_only'] ?? false), fn ($query) => $query->whereNotNull('escalated_at'))
            ->when((bool) ($validated['retry_due_only'] ?? false), fn ($query) => $query->whereNotNull('next_retry_at')->where('next_retry_at', '<=', now()->utc()));
    }
}
