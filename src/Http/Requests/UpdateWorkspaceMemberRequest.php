<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Requests;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceMemberRequest extends FormRequest
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
        $rolesTable = config('roles-permissions.tables.roles', 'roles');
        $scope = config('workspaces.roles.scope', 'workspace');

        return [
            'role' => [
                'required',
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
