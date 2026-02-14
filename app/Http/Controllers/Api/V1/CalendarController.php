<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends BaseController
{
    /**
     * Return appointment slots for calendar rendering in a date range.
     */
    public function availability(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $trainerId = $request->query('trainer_id');

        if (! $trainerId) {
            $trainerId = $workspaceRole === 'owner_admin'
                ? null
                : $request->user()->id;
        }

        $from = $request->query('from', now()->startOfDay()->toDateTimeString());
        $to = $request->query('to', now()->addWeek()->endOfDay()->toDateTimeString());

        $appointments = Appointment::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('starts_at', [$from, $to])
            ->when($trainerId, fn ($q) => $q->where('trainer_user_id', $trainerId))
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED])
            ->orderBy('starts_at')
            ->get(['id', 'trainer_user_id', 'student_id', 'starts_at', 'ends_at', 'status']);

        return $this->sendResponse([
            'from' => $from,
            'to' => $to,
            'appointments' => $appointments,
        ]);
    }
}
