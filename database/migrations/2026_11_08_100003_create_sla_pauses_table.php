<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section 9: authorized, audited SLA pauses. A pause may only start while
     * the ticket is awaiting_parts / awaiting_vendor and the policy permits it.
     */
    public function up(): void
    {
        Schema::create('sla_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('reason_code', 40);
            $table->text('note')->nullable();
            $table->string('ticket_state', 40); // state when the pause began
            $table->timestamp('paused_at');
            $table->foreignId('paused_by')->constrained('users');
            $table->string('paused_by_role', 40);
            $table->timestamp('resumed_at')->nullable();
            $table->foreignId('resumed_by')->nullable()->constrained('users');
            $table->string('resumed_by_role', 40)->nullable();
            $table->text('resume_reason')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'resumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_pauses');
    }
};
