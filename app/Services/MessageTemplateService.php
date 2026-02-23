<?php

namespace App\Services;

use App\Models\MessageTemplate;
use Illuminate\Support\Collection;

class MessageTemplateService
{
    public function list(int $workspaceId): Collection
    {
        return MessageTemplate::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function create(int $workspaceId, array $data): MessageTemplate
    {
        if (! empty($data['is_default'])) {
            MessageTemplate::query()
                ->where('workspace_id', $workspaceId)
                ->where('channel', $data['channel'] ?? 'whatsapp')
                ->update(['is_default' => false]);
        }

        return MessageTemplate::query()->create([
            'workspace_id' => $workspaceId,
            'name' => $data['name'],
            'channel' => $data['channel'] ?? 'whatsapp',
            'body' => $data['body'],
            'is_default' => $data['is_default'] ?? false,
        ]);
    }

    public function update(MessageTemplate $template, array $data): MessageTemplate
    {
        if (! empty($data['is_default'])) {
            MessageTemplate::query()
                ->where('workspace_id', $template->workspace_id)
                ->where('channel', $template->channel)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($data);

        return $template;
    }

    public function delete(MessageTemplate $template): void
    {
        $template->delete();
    }
}
