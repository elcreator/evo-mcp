<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('emcp_tokens')) {
            return;
        }

        Schema::create('emcp_tokens', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('name', 100);
            // First characters of the plaintext token, shown in lists so a user can tell tokens apart.
            $table->string('token_prefix', 16);
            $table->string('token_hash', 64)->unique();
            $table->text('scopes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emcp_tokens');
    }
};
