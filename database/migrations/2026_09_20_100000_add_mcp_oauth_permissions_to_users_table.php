<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an OAuth-connected AI client (claude.ai, Claude Desktop) is allowed to
 * do. Laravel MCP's OAuth layer advertises exactly one scope, `mcp:use` — it
 * is a translation layer to the authenticated user, not a permission system —
 * so the read/write/delete split the Sanctum token flow gets from its own
 * abilities has to live on the account instead.
 *
 * The defaults deliberately mirror the "Neuen Token erstellen" checkboxes:
 * writing on, deleting off. Connecting Claude must not silently hand it
 * permanent-delete rights that creating a token makes you tick a box for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('mcp_oauth_write')->default(true);
            $table->boolean('mcp_oauth_delete')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['mcp_oauth_write', 'mcp_oauth_delete']);
        });
    }
};
