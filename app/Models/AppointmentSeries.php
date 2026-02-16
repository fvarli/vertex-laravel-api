<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentSeries extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'workspace_id',
        'trainer_user_id',
        'student_id',
        'title',
        'location',
        'recurrence_rule',
        'start_date',
        'starts_at_time',
        'ends_at_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_rule' => 'array',
            'start_date' => 'date',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'series_id');
    }
}
