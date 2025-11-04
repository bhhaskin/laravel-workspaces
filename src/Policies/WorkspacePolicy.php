<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Policies;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceAuthorization;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class WorkspacePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $this->resolveUserModel($user) !== null;
    }

    public function view(Authenticatable $user, Workspace $workspace): bool
    {
        $model = $this->resolveUserModel($user);

        if (! $model) {
            return false;
        }

        return WorkspaceAuthorization::allows($model, $workspace, 'view')
            || $workspace->isOwner($model)
            || $workspace->isMember($model);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->resolveUserModel($user) !== null;
    }

    public function update(Authenticatable $user, Workspace $workspace): bool
    {
        $model = $this->resolveUserModel($user);

        if (! $model) {
            return false;
        }

        if ($workspace->isOwner($model)) {
            return true;
        }

        return WorkspaceAuthorization::allows($model, $workspace, 'update');
    }

    public function delete(Authenticatable $user, Workspace $workspace): bool
    {
        $model = $this->resolveUserModel($user);

        return $model !== null && $workspace->isOwner($model);
    }

    public function viewMembers(Authenticatable $user, Workspace $workspace): bool
    {
        return $this->view($user, $workspace);
    }

    public function manageMembers(Authenticatable $user, Workspace $workspace): bool
    {
        $model = $this->resolveUserModel($user);

        if (! $model) {
            return false;
        }

        if ($workspace->isOwner($model)) {
            return true;
        }

        return WorkspaceAuthorization::allows($model, $workspace, 'manage-members');
    }

    public function manageInvitations(Authenticatable $user, Workspace $workspace): bool
    {
        $model = $this->resolveUserModel($user);

        if (! $model) {
            return false;
        }

        if ($workspace->isOwner($model)) {
            return true;
        }

        return WorkspaceAuthorization::allows($model, $workspace, 'manage-invitations');
    }

    protected function resolveUserModel(Authenticatable $user): ?Model
    {
        if (! $user instanceof Model) {
            return null;
        }

        $expected = WorkspaceConfig::userModel();

        return $user instanceof $expected ? $user : null;
    }
}
