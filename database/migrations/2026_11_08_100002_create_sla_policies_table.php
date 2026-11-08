<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section 9: one SLA policy per severity for the single facility.
     * Targets are integer minutes (no business-hours calendar).
     */
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->enum('severity', ['critical', 'major', 'minor'])->unique();
            $table->unsignedInteger('response_minutes');   // reported -> assigned
            $table->unsignedInteger('resolution_minutes'); // reported -> verified
            $table->boolean('pause_allowed')->default(false);
            $table->timestamps();
        });

        // Facility defaults; adjust per approved policy.
        $now = now();
        DB::table('sla_policies')->insert([
            ['severity' => 'critical', 'response_minutes' => 30, 'resolution_minutes' => 480, 'pause_allowed' => true, 'created_at' => $now, 'updated_at' => $now],
            ['severity' => 'major', 'response_minutes' => 120, 'resolution_minutes' => 1440, 'pause_allowed' => true, 'created_at' => $now, 'updated_at' => $now],
            ['severity' => 'minor', 'response_minutes' => 480, 'resolution_minutes' => 4320, 'pause_allowed' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
