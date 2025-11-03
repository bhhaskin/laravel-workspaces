<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * @mixin \Bhhaskin\LaravelWorkspaces\Models\Workspace
 */
class WorkspaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $members = $this->whenLoaded('members');
        $invitations = $this->whenLoaded('invitations');

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'meta' => $this->meta ?? [],
            'owner' => $this->whenLoaded('owner', function () {
                $owner = $this->owner;

                return [
                    'id' => $owner->getKey(),
                    'name' => $owner->name ?? null,
                    'email' => $owner->email ?? null,
                ];
            }, [
                'id' => $this->owner_id,
            ]),
            'members_count' => $this->when(isset($this->members_count), fn () => (int) $this->members_count),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'members' => $members instanceof MissingValue
                ? $members
                : (new WorkspaceMemberCollection($this->members, $this->resource))->toArray($request),
            'invitations' => $invitations instanceof MissingValue
                ? $invitations
                : WorkspaceInvitationResource::collection($this->invitations)->toArray($request),
        ];
    }
}
