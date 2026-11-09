<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One fixed row (id = 1). Appenders lock it with lockForUpdate(), so competing
     * writers cannot create two chains. Lock order: the audit head is always locked LAST.
     */
    public function up(): void
    {
        Schema::create('audit_chain_heads', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->char('last_hash', 64);
            $table->timestamps();
        });

        DB::table('audit_chain_heads')->insert([
            'id' => 1,
            'last_sequence' => 0,
            'last_hash' => str_repeat('0', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_chain_heads');
    }
};
