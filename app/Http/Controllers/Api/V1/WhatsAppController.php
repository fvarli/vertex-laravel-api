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

    /**
     * Generate bulk WhatsApp links for all appointments on a given date.
     */
    public function bulkLinks(Request $request): JsonResponse
    {
        $request->validate(['date' => ['required', 'date']]);

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');
        $trainerUserId = $workspaceRole === 'owner_admin' ? null : (int) $request->user()->id;

        $links = $this->whatsAppLinkService->bulkLinks($workspaceId, $trainerUserId, $request->query('date'));

        return $this->sendResponse($links, __('api.whatsapp.bulk_links_generated'));
    }
}
