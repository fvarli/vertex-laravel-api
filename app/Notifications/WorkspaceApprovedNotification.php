<?php

namespace App\Notifications;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Workspace $workspace,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'workspace.approved',
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'approval_status' => $this->workspace->approval_status,
            'approval_note' => $this->workspace->approval_note,
            'action_url' => '/workspaces',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Workspace approved')
            ->line('Your workspace has been approved.')
            ->line("Workspace: {$this->workspace->name}")
            ->action('Open workspace', rtrim((string) config('app.frontend_url'), '/').'/workspaces');
    }
}
