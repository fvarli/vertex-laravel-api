<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Appointment\CalendarAvailabilityRequest;
use App\Services\CalendarService;
use Illuminate\Http\JsonResponse;

class CalendarController extends BaseController
{
    public function __construct(private readonly CalendarService $calendarService) {}

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

        $result = $this->calendarService->availability($workspaceId, $trainerId, $validated);

        return $this->sendResponse($result);
    }
}
