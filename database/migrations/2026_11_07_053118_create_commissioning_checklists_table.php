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
        Schema::create('commissioning_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            
            // What is the check? (e.g., "Electrical safety", "Site readiness")
            $table->string('item_name'); 
            
            // Is this check absolutely required?
            $table->boolean('is_mandatory')->default(true); 
            
            // Did it pass?
            $table->boolean('is_passed')->default(false); 
            
            // Who checked it?
            $table->foreignId('checked_by')->nullable()->constrained('users');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissioning_checklists');
    }
};
