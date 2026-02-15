<?php

namespace App\Http\Resources;

use App\Services\AccessContextService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function toArray(Request $request): array
    {
        $accessContext = app(AccessContextService::class)->build($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar ? Storage::disk('public')->url($this->avatar) : null,
            'is_active' => $this->is_active,
            'system_role' => $accessContext['system_role'],
            'active_workspace_role' => $accessContext['active_workspace_role'],
            'permissions' => $accessContext['permissions'],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
