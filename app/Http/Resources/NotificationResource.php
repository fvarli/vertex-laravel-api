<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? $this->type,
            'title' => $this->resolveTitle($data),
            'body' => $this->resolveBody($data),
            'action_url' => $data['action_url'] ?? null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'data' => $data,
        ];
    }

    private function resolveTitle(array $data): string
    {
        return match ($data['type'] ?? '') {
            'workspace.approval_requested' => 'Workspace approval requested',
            'workspace.approved' => 'Workspace approved',
            'workspace.rejected' => 'Workspace rejected',
            default => 'Notification',
        };
    }

    private function resolveBody(array $data): string
    {
        $workspace = (string) ($data['workspace_name'] ?? 'Workspace');
        $type = (string) ($data['type'] ?? '');

        if ($type === 'workspace.approval_requested') {
            return "{$workspace} is waiting for platform approval.";
        }

        if ($type === 'workspace.approved') {
            return "{$workspace} has been approved.";
        }

        if ($type === 'workspace.rejected') {
            $note = isset($data['approval_note']) ? trim((string) $data['approval_note']) : '';

            return $note !== ''
                ? "{$workspace} was rejected. Reason: {$note}"
                : "{$workspace} was rejected.";
        }

        return 'You have a new notification.';
    }
}
