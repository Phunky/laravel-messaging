<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(messaging_table('participants'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained(messaging_table('conversations'))
                ->cascadeOnDelete();
            $table->morphs('messageable');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'messageable_type', 'messageable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(messaging_table('participants'));
    }
};
