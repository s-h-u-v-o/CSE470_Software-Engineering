<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('status_events', function (Blueprint $table) {
            $table->enum('downtime_action', ['start', 'end'])->nullable()->after('cause_type');
            $table->uuid('cause_key')->nullable()->after('downtime_action');
            $table->index(['asset_id', 'cause_key', 'downtime_action']);
        });
    }

    public function down(): void
    {
        Schema::table('status_events', function (Blueprint $table) {
            $table->dropIndex(['asset_id', 'cause_key', 'downtime_action']);
            $table->dropColumn(['downtime_action', 'cause_key']);
        });
    }
};
