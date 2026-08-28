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
            if (!Schema::hasColumn('tracker_events', 'city')) {
                $table->string('city', 100)->nullable()->after('country');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracker_events', function (Blueprint $table) {
            if (Schema::hasColumn('tracker_events', 'city')) {
                $table->dropColumn('city');
            }
        });
    }
};
