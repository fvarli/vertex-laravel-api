<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use HasFactory;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    public const WHATSAPP_STATUS_SENT = 'sent';

    public const WHATSAPP_STATUS_NOT_SENT = 'not_sent';

    protected $fillable = [
        'series_id',
        'series_occurrence_date',
        'is_series_exception',
        'series_edit_scope_applied',
        'workspace_id',
        'trainer_user_id',
        'student_id',
        'starts_at',
        'ends_at',
        'status',
        'whatsapp_status',
        'whatsapp_marked_at',
        'whatsapp_marked_by_user_id',
        'location',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'whatsapp_marked_at' => 'datetime',
            'series_occurrence_date' => 'date',
            'is_series_exception' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(AppointmentSeries::class, 'series_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function whatsappMarkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'whatsapp_marked_by_user_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }
}
