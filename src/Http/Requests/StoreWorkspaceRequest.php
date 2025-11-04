<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Requests;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', Workspace::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $table = config('workspaces.tables.workspaces', 'workspaces');

        return [
            'name' => ['required', 'string', 'min:1', 'max:255', 'regex:/^(?!\s*$).+/'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique($table, 'slug'),
            ],
            'meta' => ['nullable', 'array', 'max:50'],
        ];
    }
}
