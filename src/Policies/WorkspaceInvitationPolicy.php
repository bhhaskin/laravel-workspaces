<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Policies;

use Bhhaskin\LaravelWorkspaces\Models\WorkspaceInvitation;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceAuthorization;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkspaceInvitationPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $this->resolveUserModel($user) !== null;
    }

    public function view(Authenticatable $user, WorkspaceInvitation $invitation): bool
    {
        return $this->canManage($user, $invitation);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->resolveUserModel($user) !== null;
    }

    public function delete(Authenticatable $user, WorkspaceInvitation $invitation): bool
    {
        return $this->canManage($user, $invitation);
    }

    public function respond(Authenticatable $user, WorkspaceInvitation $invitation): bool
    {
        $model = $this->resolveUserModel($user);

        if (! $model) {
            return false;
        }

        $email = Str::lower((string) $model->getAttribute('email'));

        return $email !== '' && $email === $invitation->email;
    }

    protected function canManage(Authenticatable $user, WorkspaceInvitation $invitation): bool
    {
        $model = $this->resolveUserModel($user);

        if (! $model) {
            return false;
        }

        $workspace = $invitation->workspace;

        if ($workspace === null) {
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
