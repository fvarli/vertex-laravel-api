<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Appointment\CalendarAvailabilityRequest;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;

class CalendarController extends BaseController
{
    /**
     * Return appointment slots for calendar rendering in a date range.
     */
    public function availability(CalendarAvailabilityRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $trainerId = $validated['trainer_id'] ?? null;

        if (! $trainerId) {
            $trainerId = $workspaceRole === 'owner_admin'
                ? null
                : $request->user()->id;
        }

        $from = $validated['from'] ?? now()->startOfDay()->toDateTimeString();
        $to = $validated['to'] ?? now()->addWeek()->endOfDay()->toDateTimeString();

        $appointments = Appointment::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('starts_at', [$from, $to])
            ->when($trainerId, fn ($q) => $q->where('trainer_user_id', $trainerId))
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED])
            ->orderBy('starts_at')
            ->get(['id', 'trainer_user_id', 'student_id', 'starts_at', 'ends_at', 'status']);

        $days = $appointments
            ->groupBy(fn ($appointment) => $appointment->starts_at->toDateString())
            ->map(fn ($items, $date) => [
                'date' => $date,
                'items' => $items->values(),
            ])
            ->values();

        return $this->sendResponse([
            'from' => $from,
            'to' => $to,
            'appointments' => $appointments,
            'days' => $days,
        ]);
    }
}
