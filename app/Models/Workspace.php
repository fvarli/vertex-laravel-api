<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_user_id',
        'approval_status',
        'approval_requested_at',
        'approved_at',
        'approved_by_user_id',
        'approval_note',
        'reminder_policy',
    ];

    protected function casts(): array
    {
        return [
            'approval_requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'reminder_policy' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function appointmentSeries(): HasMany
    {
        return $this->hasMany(AppointmentSeries::class);
    }

    public function appointmentReminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }
}
