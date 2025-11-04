<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $workspacesTable = config('workspaces.tables.workspaces', 'workspaces');
        $usersTable = config('workspaces.tables.users', 'users');

        Schema::table($workspacesTable, function (Blueprint $table) use ($usersTable) {
            $table->foreignId('billing_contact_id')
                ->nullable()
                ->after('owner_id')
                ->constrained($usersTable)
                ->nullOnDelete()
                ->comment('User responsible for billing; falls back to owner if null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $workspacesTable = config('workspaces.tables.workspaces', 'workspaces');

        Schema::table($workspacesTable, function (Blueprint $table) {
            $table->dropForeign(['billing_contact_id']);
            $table->dropColumn('billing_contact_id');
        });
    }
};
