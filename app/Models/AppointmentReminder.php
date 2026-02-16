<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentReminder extends Model
{
    use HasFactory;

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_SENT = 'sent';

    public const STATUS_MISSED = 'missed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ESCALATED = 'escalated';

    protected $fillable = [
        'workspace_id',
        'appointment_id',
        'channel',
        'scheduled_for',
        'status',
        'attempt_count',
        'last_attempted_at',
        'next_retry_at',
        'escalated_at',
        'failure_reason',
        'opened_at',
        'marked_sent_at',
        'marked_sent_by_user_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'last_attempted_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'escalated_at' => 'datetime',
            'opened_at' => 'datetime',
            'marked_sent_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function markedSentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_sent_by_user_id');
    }
}
