<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Http\Requests;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'role' => ['required', 'string'],
        ];
    }
}
