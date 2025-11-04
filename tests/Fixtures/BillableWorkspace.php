<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Tests\Fixtures;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Traits\WorkspaceBillable;

class BillableWorkspace extends Workspace
{
    use WorkspaceBillable;

    /**
     * Get the default foreign key name for the model.
     */
    public function getForeignKey(): string
    {
        return 'workspace_id';
    }
}
