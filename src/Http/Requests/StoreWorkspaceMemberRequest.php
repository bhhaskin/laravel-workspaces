<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Requests;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');
        $user = $this->user();

        return $workspace instanceof Workspace
            && $user !== null
            && $user->can('manageMembers', $workspace);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userModel = WorkspaceConfig::userModel();

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $userModel();

        $rolesTable = config('roles-permissions.tables.roles', 'roles');
        $scope = config('workspaces.roles.scope', 'workspace');

        return [
            'user_id' => [
                'required',
                Rule::exists($model->getTable(), $model->getKeyName()),
            ],
            'role' => [
                'nullable',
                'string',
                Rule::exists($rolesTable, 'slug')
                    ->where(function ($query) use ($scope) {
                        if ($scope !== null) {
                            $query->where('scope', $scope);
                        } else {
                            $query->whereNull('scope');
                        }
                    }),
            ],
        ];
    }
}
