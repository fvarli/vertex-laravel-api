<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DomainAuditService
{
    public function record(
        Request $request,
        string $event,
        Model $auditable,
        array $before = [],
        array $after = [],
        array $allowedFields = [],
    ): void {
        $filteredBefore = $this->filterByAllowedFields($before, $allowedFields);
        $filteredAfter = $this->filterByAllowedFields($after, $allowedFields);

        AuditLog::query()->create([
            'workspace_id' => $request->attributes->get('workspace_id'),
            'actor_user_id' => $request->user()?->id,
            'event' => $event,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'changes' => [
                'before' => $filteredBefore,
                'after' => $filteredAfter,
            ],
            'request_id' => $request->attributes->get('request_id'),
            'ip_address' => $request->ip(),
        ]);
    }

    private function filterByAllowedFields(array $payload, array $allowedFields): array
    {
        if ($allowedFields === []) {
            return $payload;
        }

        return collect($payload)
            ->only($allowedFields)
            ->all();
    }
}
