<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Resources;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceRoles;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class WorkspaceMemberResource extends JsonResource
{
    public function __construct(Model $resource, protected Workspace $workspace)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $pivot = $user->getRelation('pivot');

        if (! is_object($pivot)) {
            $pivot = (object) [];
        }

        $roles = collect(WorkspaceRoles::memberRoles($user, $this->workspace))
            ->map(fn ($role) => [
                'id' => $role->getKey(),
                'name' => $role->name,
                'slug' => $role->slug,
            ])
            ->values()
            ->all();

        return [
            'id' => $user->getKey(),
            'uuid' => $pivot->uuid ?? null,
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'roles' => $roles,
            'last_joined_at' => $this->formatDate($pivot->last_joined_at ?? null),
            'removed_at' => $this->formatDate($pivot->removed_at ?? null),
            'created_at' => $this->formatDate($pivot->created_at ?? null),
            'updated_at' => $this->formatDate($pivot->updated_at ?? null),
        ];
    }

    protected function formatDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        if (is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value)->toIso8601String();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
