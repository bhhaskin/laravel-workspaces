<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Routes;

use Bhhaskin\LaravelWorkspaces\Http\Controllers\WorkspaceController;
use Bhhaskin\LaravelWorkspaces\Http\Controllers\WorkspaceInvitationController;
use Bhhaskin\LaravelWorkspaces\Http\Controllers\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

final class Workspaces
{
    /**
     * Register the API routes provided by the package.
     *
     * @param  array{
     *     prefix?: string|null,
     *     name?: string|null,
     *     middleware?: array|string|null,
     *     invitation_prefix?: string|null,
     *     invitation_name?: string|null,
     *     invitation_middleware?: array|string|null,
     * }  $options
     */
    public static function register(array $options = []): void
    {
        $workspaceAttributes = self::workspaceGroupAttributes($options);

        Route::group($workspaceAttributes, function (): void {
            Route::get('/', [WorkspaceController::class, 'index'])->name('index');
            Route::post('/', [WorkspaceController::class, 'store'])->name('store');
            Route::get('{workspace}', [WorkspaceController::class, 'show'])->name('show');
            Route::patch('{workspace}', [WorkspaceController::class, 'update'])->name('update');
            Route::delete('{workspace}', [WorkspaceController::class, 'destroy'])->name('destroy');

            Route::get('{workspace}/members', [WorkspaceMemberController::class, 'index'])->name('members.index');
            Route::post('{workspace}/members', [WorkspaceMemberController::class, 'store'])->name('members.store');
            Route::patch('{workspace}/members/{member}', [WorkspaceMemberController::class, 'update'])->name('members.update');
            Route::delete('{workspace}/members/{member}', [WorkspaceMemberController::class, 'destroy'])->name('members.destroy');

            Route::get('{workspace}/invitations', [WorkspaceInvitationController::class, 'index'])->name('invitations.index');
            Route::post('{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])->name('invitations.store');
            Route::delete('{workspace}/invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])->name('invitations.destroy');
        });

        $invitationAttributes = self::invitationGroupAttributes($options);

        Route::group($invitationAttributes, function (): void {
            Route::post('{invitation}/accept', [WorkspaceInvitationController::class, 'accept'])->name('accept');
            Route::post('{invitation}/decline', [WorkspaceInvitationController::class, 'decline'])->name('decline');
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private static function workspaceGroupAttributes(array $options): array
    {
        $attributes = [
            'prefix' => $options['prefix'] ?? 'workspaces',
            'middleware' => $options['middleware'] ?? ['auth:sanctum', 'throttle:api'],
            'as' => $options['name'] ?? 'workspaces.',
            'scope_bindings' => true,
        ];

        return array_filter($attributes, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private static function invitationGroupAttributes(array $options): array
    {
        $attributes = [
            'prefix' => $options['invitation_prefix'] ?? 'workspace-invitations',
            'middleware' => $options['invitation_middleware'] ?? ($options['middleware'] ?? ['auth:sanctum', 'throttle:invitations']),
            'as' => $options['invitation_name'] ?? 'workspace-invitations.',
        ];

        return array_filter($attributes, fn ($value) => $value !== null && $value !== '');
    }
}
