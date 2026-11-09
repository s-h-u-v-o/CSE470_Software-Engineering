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
        Schema::create('status_events', function (Blueprint $table) {
            $table->id();
            
            // Which asset is this event about?
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            
            // The state the asset moved into (e.g., 'blocked_by_ticket', 'technically_ready')
            $table->string('next_state');
            
            // The state it was in before (can be null for the very first event)
            $table->string('previous_state')->nullable();
            
            // Section 8: Time and Recording Policy
            $table->timestamp('occurred_at')->useCurrent(); // When the event actually happened in the real world
            $table->timestamp('recorded_at')->useCurrent(); // When it was entered into the system
            
            // Section 10: planned or unplanned downtime
            $table->enum('cause_type', ['planned', 'unplanned'])->nullable();
            
            // Optional ticket link (if this downtime was caused by a repair ticket)
            $table->foreignId('ticket_id')->nullable(); 
            
            // Who recorded this?
            $table->foreignId('actor_id')->constrained('users');
            
            $table->text('reason')->nullable(); // Why the status changed
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_events');
    }
};
