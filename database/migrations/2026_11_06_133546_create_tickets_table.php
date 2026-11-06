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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            
            // Link to the asset that is broken
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            
            // The person who reported the fault
            $table->foreignId('reported_by')->constrained('users');
            
            // The engineer assigned to fix it (can be null initially)
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            
            // Ticket Workflow (Section 6B of brief)
            $table->enum('state', [
                'reported', 
                'triaged', 
                'assigned', 
                'awaiting_parts', 
                'awaiting_vendor', 
                'repaired', 
                'verified', 
                'closed'
            ])->default('reported');
            
            // Severity determines if it blocks readiness
            $table->enum('severity', ['critical', 'major', 'minor'])->nullable();
            
            $table->text('description'); // What is wrong with the machine
            
            // Section 8: Time and Recording Policy
            $table->timestamp('occurred_at'); // When the machine broke
            $table->timestamp('recorded_at'); // When it was logged in the system
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
