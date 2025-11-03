<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Models\WorkspaceInvitation;
use Bhhaskin\LaravelWorkspaces\Policies\WorkspaceInvitationPolicy;
use Bhhaskin\LaravelWorkspaces\Policies\WorkspacePolicy;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceAuthorization;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceRoles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class LaravelWorkspacesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/workspaces.php', 'workspaces');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/workspaces.php' => $this->configPublishPath(),
        ], 'workspaces-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'workspaces-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerPolicies();
        WorkspaceRoles::ensureDefaultRoles();
        WorkspaceAuthorization::registerGates();
    }

    protected function configPublishPath(): string
    {
        $configuredPath = config('workspaces.config_path');

        return is_string($configuredPath) && $configuredPath !== ''
            ? $configuredPath
            : config_path('workspaces.php');
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(WorkspaceInvitation::class, WorkspaceInvitationPolicy::class);
    }
}
