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
        if (Schema::hasTable('ec_order_addresses') && !Schema::hasColumn('ec_order_addresses', 'building_name')) {
            Schema::table('ec_order_addresses', function (Blueprint $table) {
                $table->string('building_name', 255)->nullable()->after('area');
            });
        }

        if (Schema::hasTable('ec_customer_addresses') && !Schema::hasColumn('ec_customer_addresses', 'building_name')) {
            Schema::table('ec_customer_addresses', function (Blueprint $table) {
                $table->string('building_name', 255)->nullable()->after('area');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ec_order_addresses') && Schema::hasColumn('ec_order_addresses', 'building_name')) {
            Schema::table('ec_order_addresses', function (Blueprint $table) {
                $table->dropColumn('building_name');
            });
        }

        if (Schema::hasTable('ec_customer_addresses') && Schema::hasColumn('ec_customer_addresses', 'building_name')) {
            Schema::table('ec_customer_addresses', function (Blueprint $table) {
                $table->dropColumn('building_name');
            });
        }
    }
};
