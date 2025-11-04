<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Tests\Fixtures;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Traits\WorkspaceBillable;

class BillableWorkspace extends Workspace
{
    use WorkspaceBillable;

    /**
     * For testing purposes, allow owner_id to be mass-assigned.
     * In production, owner_id should be set explicitly for security.
     */
    protected $fillable = [
        'name',
        'slug',
        'meta',
        'owner_id', // Added for test convenience
    ];

    /**
     * Get the default foreign key name for the model.
     */
    public function getForeignKey(): string
    {
        return 'workspace_id';
    }
}
