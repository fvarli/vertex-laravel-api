<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Reminder\ListReminderRequest;
use App\Http\Resources\AppointmentReminderResource;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Services\DomainAuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ReminderController extends BaseController
{
    private const AUDIT_FIELDS = ['status', 'opened_at', 'marked_sent_at', 'marked_sent_by_user_id'];

    public function __construct(private readonly DomainAuditService $auditService) {}

    public function index(ListReminderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');
        $user = $request->user();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $status = (string) ($validated['status'] ?? 'all');

        $reminders = AppointmentReminder::query()
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
            ->orderBy('scheduled_for')
            ->paginate($perPage);

        return $this->sendResponse(AppointmentReminderResource::collection($reminders)->response()->getData(true));
    }

    public function open(AppointmentReminder $reminder): JsonResponse
    {
        $this->authorize('update', $reminder);

        $before = $reminder->toArray();
        $reminder->update([
            'opened_at' => now()->utc(),
            'status' => $reminder->status === AppointmentReminder::STATUS_PENDING
                ? AppointmentReminder::STATUS_READY
                : $reminder->status,
        ]);
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
        $reminder->update(['status' => AppointmentReminder::STATUS_CANCELLED]);
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
}
