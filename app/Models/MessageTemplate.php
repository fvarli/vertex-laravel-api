<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'channel',
        'body',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function render(array $variables): string
    {
        $body = $this->body;

        foreach ($variables as $key => $value) {
            $body = str_replace('{'.$key.'}', (string) $value, $body);
        }

        return $body;
    }
}
