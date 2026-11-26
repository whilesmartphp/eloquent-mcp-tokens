<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('mcp-tokens.tokens_table', 'mcp_tokens'), function (Blueprint $table) {
            $table->id();
            match (config('mcp-tokens.owner_key_type', 'int')) {
                'uuid' => $table->uuidMorphs('owner'),
                'ulid' => $table->ulidMorphs('owner'),
                default => $table->morphs('owner'),
            };
            $table->nullableMorphs('creator');
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->unique();
            $table->string('name');
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_type', 'owner_id', 'revoked_at']);
        });

        Schema::create(config('mcp-tokens.events_table', 'mcp_token_events'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('mcp_token_id')->constrained(config('mcp-tokens.tokens_table', 'mcp_tokens'))->cascadeOnDelete();
            $table->string('action');
            $table->string('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['mcp_token_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('mcp-tokens.events_table', 'mcp_token_events'));
        Schema::dropIfExists(config('mcp-tokens.tokens_table', 'mcp_tokens'));
    }
};
