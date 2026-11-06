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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            
            // Link to the AssetCategory table
            $table->foreignId('asset_category_id')->constrained('asset_categories')->cascadeOnDelete();
            
            $table->string('name'); // e.g., "X-Ray Machine 01"
            $table->string('serial_number')->unique(); // Duplicate serial rejected
            
            // Lifecycle state (Section 6A of your brief)
            $table->enum('lifecycle_state', [
                'received_crated', 
                'installed', 
                'commissioned', 
                'retired'
            ])->default('received_crated');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
