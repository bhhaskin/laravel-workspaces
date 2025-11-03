<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Resources;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WorkspaceMemberCollection extends ResourceCollection
{
    public function __construct($resource, protected Workspace $workspace)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn ($member) => (new WorkspaceMemberResource($member, $this->workspace))->toArray($request))
            ->all();
    }
}
