<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceApprovalRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Workspace $workspace,
        private readonly User $owner,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if (FcmChannel::shouldSend($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'workspace.approval_requested',
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'owner_user_id' => $this->owner->id,
            'owner_name' => trim($this->owner->name.' '.$this->owner->surname),
            'approval_status' => $this->workspace->approval_status,
            'action_url' => '/admin/workspaces',
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'New workspace approval request',
            'body' => "Workspace \"{$this->workspace->name}\" by {$this->owner->email} needs approval.",
            'data' => [
                'type' => 'workspace.approval_requested',
                'workspace_id' => (string) $this->workspace->id,
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Workspace approval request')
            ->line('A new workspace is waiting for platform approval.')
            ->line("Workspace: {$this->workspace->name} (#{$this->workspace->id})")
            ->line("Owner: {$this->owner->email}")
            ->action('Review requests', rtrim((string) config('app.frontend_url'), '/').'/admin/workspaces');
    }
}
