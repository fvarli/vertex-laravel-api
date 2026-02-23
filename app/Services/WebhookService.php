<?php

namespace App\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookEndpoint;

class WebhookService
{
    /**
     * Available webhook events.
     */
    public const EVENTS = [
        'appointment.created',
        'appointment.updated',
        'appointment.status_changed',
        'student.created',
        'student.updated',
        'student.status_changed',
        'workspace.updated',
        'reminder.created',
        'reminder.sent',
    ];

    /**
     * Dispatch a webhook event to all subscribed endpoints for a workspace.
     */
    public function dispatch(int $workspaceId, string $event, array $payload): int
    {
        $endpoints = WebhookEndpoint::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->get();

        $dispatched = 0;

        foreach ($endpoints as $endpoint) {
            if ($endpoint->subscribesTo($event)) {
                DispatchWebhookJob::dispatch($endpoint->id, $event, $payload);
                $dispatched++;
            }
        }

        return $dispatched;
    }
}
