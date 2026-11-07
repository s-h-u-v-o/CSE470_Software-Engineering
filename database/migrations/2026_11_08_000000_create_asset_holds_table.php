<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['quarantine', 'out_of_service']);
            $table->text('reason');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('actor_id')->constrained('users');
            $table->timestamps();
            $table->index(['asset_id', 'type', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_holds');
    }
};
