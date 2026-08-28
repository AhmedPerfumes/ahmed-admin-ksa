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
        Schema::table('tracker_events', function (Blueprint $table) {
            // Covers: KPIs, funnel steps, distinct visitor queries with date range
            $table->index(['event_name', 'created_at', 'visitor_id'], 'idx_event_date_visitor');

            // Covers: Revenue aggregations and time-series revenue trend queries
            $table->index(['event_name', 'created_at', 'revenue'], 'idx_event_date_revenue');

            // Covers: Traffic sources, devices, active carts, and session grouping
            $table->index(['created_at', 'event_name', 'session_id'], 'idx_date_event_session');

            // Covers: Attribution first-touch lookups and visitor journey chronological retrieval
            $table->index(['visitor_id', 'created_at', 'id'], 'idx_visitor_date_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracker_events', function (Blueprint $table) {
            $table->dropIndex('idx_event_date_visitor');
            $table->dropIndex('idx_event_date_revenue');
            $table->dropIndex('idx_date_event_session');
            $table->dropIndex('idx_visitor_date_id');
        });
    }
};
