<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'url',
        'secret',
        'events',
        'is_active',
        'failure_count',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true) || in_array('*', $this->events ?? [], true);
    }
}
