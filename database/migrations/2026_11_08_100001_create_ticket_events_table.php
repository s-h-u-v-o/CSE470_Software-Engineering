<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only ticket history (Section 6B): every transition, severity
     * reclassification and SLA pause/resume is recorded here.
     */
    public function up(): void
    {
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('from_state', 40)->nullable();
            $table->string('to_state', 40)->nullable();
            $table->string('from_severity', 20)->nullable();
            $table->string('to_severity', 20)->nullable();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('actor_role', 40);
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['ticket_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
    }
};
