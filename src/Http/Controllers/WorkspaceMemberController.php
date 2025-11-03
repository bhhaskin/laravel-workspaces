<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Controllers;

use Bhhaskin\LaravelWorkspaces\Http\Requests\StoreWorkspaceMemberRequest;
use Bhhaskin\LaravelWorkspaces\Http\Requests\UpdateWorkspaceMemberRequest;
use Bhhaskin\LaravelWorkspaces\Http\Resources\WorkspaceMemberCollection;
use Bhhaskin\LaravelWorkspaces\Http\Resources\WorkspaceMemberResource;
use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class WorkspaceMemberController extends Controller
{
    public function index(Request $request, Workspace $workspace): WorkspaceMemberCollection
    {
        $this->authorize('viewMembers', $workspace);

        $workspace->load(['members']);

        return new WorkspaceMemberCollection($workspace->members, $workspace);
    }

    public function store(StoreWorkspaceMemberRequest $request, Workspace $workspace): Response
    {
        $this->authorize('manageMembers', $workspace);

        $data = $request->validated();
        $user = $this->resolveUserByKey($data['user_id']);

        if ($workspace->isOwner($user)) {
            throw ValidationException::withMessages([
                'user_id' => ['The workspace owner is already a member.'],
            ]);
        }

        $workspace->addMember($user, $data['role'] ?? null);

        $member = $workspace->membersIncludingRemoved()->whereKey($user->getKey())->firstOrFail();

        return (new WorkspaceMemberResource($member, $workspace))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateWorkspaceMemberRequest $request, Workspace $workspace, string $member): WorkspaceMemberResource
    {
        $this->authorize('manageMembers', $workspace);

        $user = $this->resolveUserByKey($member);
        $data = $request->validated();

        $workspace->updateMemberRole($user, $data['role']);

        $record = $workspace->membersIncludingRemoved()->whereKey($user->getKey())->firstOrFail();

        return new WorkspaceMemberResource($record, $workspace);
    }

    public function destroy(Request $request, Workspace $workspace, string $member): Response
    {
        $this->authorize('manageMembers', $workspace);

        $user = $this->resolveUserByKey($member);

        if ($workspace->isOwner($user)) {
            throw ValidationException::withMessages([
                'user_id' => ['The workspace owner cannot be removed.'],
            ]);
        }

        $workspace->removeMember($user);

        return response()->noContent();
    }

    protected function resolveUserByKey(string|int $key): Model
    {
        $userModel = WorkspaceConfig::userModel();

        /** @var Model $model */
        $model = $userModel::query()->findOrFail($key);

        return $model;
    }
}
