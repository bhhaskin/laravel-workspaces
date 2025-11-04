<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add composite index on workspace_user for common queries
        Schema::table(Config::get('workspaces.tables.workspace_user', 'workspace_user'), function (Blueprint $table) {
            $table->index(['workspace_id', 'user_id', 'removed_at'], 'workspace_user_composite_idx');
            $table->index('removed_at', 'workspace_user_removed_at_idx');
        });

        // Add index on workspace_invitations for email lookups
        Schema::table(Config::get('workspaces.tables.workspace_invitations', 'workspace_invitations'), function (Blueprint $table) {
            $table->index(['workspace_id', 'email'], 'workspace_invitations_workspace_email_idx');
            $table->index('expires_at', 'workspace_invitations_expires_at_idx');
        });

        // Add index on workspaceables for polymorphic queries
        Schema::table(Config::get('workspaces.tables.workspaceables', 'workspaceables'), function (Blueprint $table) {
            $table->index(['workspaceable_type', 'workspaceable_id'], 'workspaceables_morph_idx');
            $table->index('workspace_id', 'workspaceables_workspace_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table(Config::get('workspaces.tables.workspace_user', 'workspace_user'), function (Blueprint $table) {
            $table->dropIndex('workspace_user_composite_idx');
            $table->dropIndex('workspace_user_removed_at_idx');
        });

        Schema::table(Config::get('workspaces.tables.workspace_invitations', 'workspace_invitations'), function (Blueprint $table) {
            $table->dropIndex('workspace_invitations_workspace_email_idx');
            $table->dropIndex('workspace_invitations_expires_at_idx');
        });

        Schema::table(Config::get('workspaces.tables.workspaceables', 'workspaceables'), function (Blueprint $table) {
            $table->dropIndex('workspaceables_morph_idx');
            $table->dropIndex('workspaceables_workspace_id_idx');
        });
    }
};
