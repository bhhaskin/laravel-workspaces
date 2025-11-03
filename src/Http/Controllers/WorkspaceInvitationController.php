<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Controllers;

use Bhhaskin\LaravelWorkspaces\Http\Requests\StoreWorkspaceInvitationRequest;
use Bhhaskin\LaravelWorkspaces\Http\Resources\WorkspaceInvitationResource;
use Bhhaskin\LaravelWorkspaces\Http\Resources\WorkspaceResource;
use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Models\WorkspaceInvitation;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class WorkspaceInvitationController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('manageInvitations', $workspace);

        $invitations = $workspace->invitations()
            ->with('role')
            ->latest()
            ->get();

        return WorkspaceInvitationResource::collection($invitations);
    }

    public function store(StoreWorkspaceInvitationRequest $request, Workspace $workspace): WorkspaceInvitationResource
    {
        $this->authorize('manageInvitations', $workspace);

        $data = $request->validated();

        $expires = isset($data['expires_at'])
            ? CarbonImmutable::parse($data['expires_at'])
            : null;

        /** @var WorkspaceInvitation $invitation */
        $invitation = $workspace->invite($data['email'], $data['role'] ?? null, $expires);
        $invitation->load('role');

        return WorkspaceInvitationResource::make($invitation);
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceInvitation $invitation): Response
    {
        $this->authorize('manageInvitations', $workspace);

        if (! $invitation->workspace || ! $invitation->workspace->is($workspace)) {
            abort(404);
        }

        $invitation->delete();

        return response()->noContent();
    }

    public function accept(Request $request, WorkspaceInvitation $invitation): WorkspaceResource
    {
        $this->authorize('respond', $invitation);

        $user = $this->resolveUserModel($request->user());

        if (! $user) {
            abort(403);
        }

        $invitation->accept($user);

        $workspace = $invitation->workspace->fresh(['owner']);

        return WorkspaceResource::make($workspace);
    }

    public function decline(Request $request, WorkspaceInvitation $invitation): WorkspaceInvitationResource
    {
        $this->authorize('respond', $invitation);

        $invitation->decline();

        return WorkspaceInvitationResource::make($invitation->fresh(['role']));
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
