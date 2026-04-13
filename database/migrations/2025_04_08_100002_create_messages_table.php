<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(messaging_table('messages'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained(messaging_table('conversations'))
                ->cascadeOnDelete();
            $table->morphs('messageable');
            $table->longText('body');
            $table->json('meta')->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['conversation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(messaging_table('messages'));
    }
};
