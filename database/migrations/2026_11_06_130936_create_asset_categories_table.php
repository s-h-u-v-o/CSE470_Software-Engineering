<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., X-ray, Ultrasound
            $table->text('description')->nullable();
            $table->boolean('requires_calibration')->default(false); // Section 6C mentions calibration checks
            $table->timestamps(); // Automatically creates created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
