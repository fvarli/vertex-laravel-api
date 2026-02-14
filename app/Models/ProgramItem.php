<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'day_of_week',
        'order_no',
        'exercise',
        'sets',
        'reps',
        'rest_seconds',
        'notes',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
