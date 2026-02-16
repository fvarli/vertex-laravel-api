<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Models\Appointment;
use App\Services\WhatsAppLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends BaseController
{
    public function __construct(private readonly WhatsAppLinkService $whatsAppLinkService) {}

    /**
     * Build WhatsApp deep-link URL for a specific appointment.
     */
    public function appointmentLink(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);

        $link = $this->whatsAppLinkService->build($appointment->student, $appointment, $request->query('template'));

        return $this->sendResponse(['url' => $link]);
    }
}
