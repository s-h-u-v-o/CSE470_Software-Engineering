<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F26: append-only, hash-chained audit trail. Tamper-evident, not tamper-proof:
     * someone who can rewrite the whole database and recompute every hash can replace the chain.
     */
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence')->unique();
            $table->string('event_type', 60);
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('actor_role', 40)->nullable();
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at')->useCurrent(); // MariaDB 10.4: non-null timestamps need a default
            $table->char('prev_hash', 64);
            $table->char('hash', 64)->unique();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};
