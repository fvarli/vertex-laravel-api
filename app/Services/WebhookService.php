<?php

namespace App\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    public function list(int $workspaceId): Collection
    {
        return WebhookEndpoint::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'url' => $w->url,
                'events' => $w->events,
                'is_active' => $w->is_active,
                'failure_count' => $w->failure_count,
                'last_triggered_at' => $w->last_triggered_at?->toIso8601String(),
                'created_at' => $w->created_at->toIso8601String(),
            ]);
    }

    public function create(int $workspaceId, string $url, array $events): WebhookEndpoint
    {
        return WebhookEndpoint::create([
            'workspace_id' => $workspaceId,
            'url' => $url,
            'events' => $events,
            'secret' => Str::random(48),
            'is_active' => true,
        ]);
    }

    public function update(WebhookEndpoint $webhook, array $data): WebhookEndpoint
    {
        $webhook->update($data);

        return $webhook;
    }

    public function delete(WebhookEndpoint $webhook): void
    {
        $webhook->delete();
    }

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
