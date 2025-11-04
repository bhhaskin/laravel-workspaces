<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Requests;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');
        $user = $this->user();

        return $workspace instanceof Workspace
            && $user !== null
            && $user->can('update', $workspace);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspace = $this->route('workspace');
        $table = config('workspaces.tables.workspaces', 'workspaces');

        return [
            'name' => ['sometimes', 'required', 'string', 'min:1', 'max:255', 'regex:/^(?!\s*$).+/'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique($table, 'slug')->ignore($workspace instanceof Workspace ? $workspace->id : null),
            ],
            'meta' => ['sometimes', 'nullable', 'array', 'max:50'],
        ];
    }
}
