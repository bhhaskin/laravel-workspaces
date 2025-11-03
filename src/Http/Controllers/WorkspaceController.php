<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Controllers;

use Bhhaskin\LaravelWorkspaces\Http\Requests\StoreWorkspaceRequest;
use Bhhaskin\LaravelWorkspaces\Http\Requests\UpdateWorkspaceRequest;
use Bhhaskin\LaravelWorkspaces\Http\Resources\WorkspaceResource;
use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Workspace::class);

        $user = $this->resolveUserModel($request->user());
        $includes = $this->resolveIncludes($request);

        $query = Workspace::query()
            ->with('owner')
            ->withCount('members')
            ->orderByDesc('created_at');

        if (in_array('members', $includes, true)) {
            $query->with(['members']);
        }

        if (in_array('invitations', $includes, true)) {
            $query->with(['invitations' => fn ($relation) => $relation->latest()]);
        }

        if ($user) {
            $query->where(function (Builder $inner) use ($user): void {
                $inner->where('owner_id', $user->getKey())
                    ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->getKey()));
            });
        }

        $workspaces = $query->get();

        return WorkspaceResource::collection($workspaces);
    }

    public function store(StoreWorkspaceRequest $request): WorkspaceResource
    {
        $this->authorize('create', Workspace::class);

        $user = $this->resolveUserModel($request->user());
        $data = $request->validated();

        $workspace = Workspace::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'meta' => $data['meta'] ?? null,
            'owner_id' => $user?->getKey(),
        ]);

        if ($user) {
            $workspace->addMember($user);
        }

        $workspace->load('owner');

        return WorkspaceResource::make($workspace);
    }

    public function show(Request $request, Workspace $workspace): WorkspaceResource
    {
        $this->authorize('view', $workspace);

        $includes = $this->resolveIncludes($request);

        $workspace->loadMissing('owner');

        if (in_array('members', $includes, true)) {
            $workspace->load(['members']);
        }

        if (in_array('invitations', $includes, true)) {
            $workspace->load(['invitations' => fn ($relation) => $relation->latest()]);
        }

        return WorkspaceResource::make($workspace);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): WorkspaceResource
    {
        $this->authorize('update', $workspace);

        $data = $request->validated();

        $workspace->fill($data);
        $workspace->save();
        $workspace->load('owner');

        return WorkspaceResource::make($workspace);
    }

    public function destroy(Request $request, Workspace $workspace): Response
    {
        $this->authorize('delete', $workspace);

        $workspace->delete();

        return response()->noContent();
    }

    /**
     * @return array<int, string>
     */
    protected function resolveIncludes(Request $request): array
    {
        $requested = array_filter(array_map('trim', explode(',', (string) $request->query('include', ''))));
        $allowed = ['members', 'invitations'];

        return array_values(array_intersect($requested, $allowed));
    }

    protected function resolveUserModel(?Authenticatable $user): ?Model
    {
        if (! $user instanceof Model) {
            return null;
        }

        $expected = WorkspaceConfig::userModel();

        return $user instanceof $expected ? $user : null;
    }
}
