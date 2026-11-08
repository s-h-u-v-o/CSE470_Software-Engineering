<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F25: findings from the gap detector. A finding is a record problem, not proof of a physical fault. */
    public function up(): void
    {
        Schema::create('gap_findings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4);
            $table->char('fingerprint', 64)->unique();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->text('message');
            $table->json('records');
            $table->text('next_step');
            $table->timestamp('first_detected_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['code', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gap_findings');
    }
};
