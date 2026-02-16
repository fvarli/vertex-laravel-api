<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'trainer_user_id',
        'name',
        'title',
        'goal',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProgramTemplateItem::class)->orderBy('day_of_week')->orderBy('order_no');
    }
}
