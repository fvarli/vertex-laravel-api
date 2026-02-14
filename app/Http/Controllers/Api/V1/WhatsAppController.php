<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Models\Appointment;
use App\Models\Student;
use App\Services\WhatsAppLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends BaseController
{
    public function __construct(private readonly WhatsAppLinkService $whatsAppLinkService) {}

    /**
     * Build WhatsApp deep-link URL for a student and optional appointment template.
     */
    public function studentLink(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $appointment = null;
        $appointmentId = $request->query('appointment_id');

        if ($appointmentId) {
            $appointment = Appointment::query()
                ->where('workspace_id', $student->workspace_id)
                ->where('student_id', $student->id)
                ->find($appointmentId);
        }

        $link = $this->whatsAppLinkService->build($student, $appointment, $request->query('template'));

        return $this->sendResponse(['url' => $link]);
    }
}
