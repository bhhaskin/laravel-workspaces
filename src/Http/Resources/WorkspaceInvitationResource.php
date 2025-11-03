<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Bhhaskin\LaravelWorkspaces\Models\WorkspaceInvitation
 */
class WorkspaceInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'email' => $this->email,
            'role' => $this->when(
                $this->relationLoaded('role') || $this->role_id !== null,
                fn () => $this->role
                    ? [
                        'id' => $this->role->getKey(),
                        'name' => $this->role->name,
                        'slug' => $this->role->slug,
                    ]
                    : [
                        'id' => $this->role_id,
                        'name' => null,
                        'slug' => null,
                    ]
            ),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'accepted_at' => optional($this->accepted_at)->toIso8601String(),
            'declined_at' => optional($this->declined_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
