<?php

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
        Schema::create('ai_conversation_contexts', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('context_data');
            $table->text('last_query')->nullable();
            $table->string('last_intent', 50)->nullable();
            $table->integer('message_count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index('conversation_id');
            $table->index('user_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_contexts');
    }
};
