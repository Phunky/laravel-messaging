<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(messaging_table('events'), function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('participant_id')
                ->constrained(messaging_table('participants'))
                ->cascadeOnDelete();
            $table->string('event');
            $table->timestamp('recorded_at');
            $table->json('meta')->nullable();

            $table->unique(['subject_type', 'subject_id', 'participant_id', 'event'], 'events_subject_participant_event_unique');
            $table->index(['participant_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(messaging_table('events'));
    }
};
