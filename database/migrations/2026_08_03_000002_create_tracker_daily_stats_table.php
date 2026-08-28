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
        Schema::create('tracker_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('event_name', 50);
            $table->unsignedInteger('event_count')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('unique_sessions')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['stat_date', 'event_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracker_daily_stats');
    }
};
