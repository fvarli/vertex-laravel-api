<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_template_id',
        'day_of_week',
        'order_no',
        'exercise',
        'sets',
        'reps',
        'rest_seconds',
        'notes',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProgramTemplate::class, 'program_template_id');
    }
}
