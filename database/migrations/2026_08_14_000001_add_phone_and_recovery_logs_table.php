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
        if (Schema::hasTable('tracker_events') && !Schema::hasColumn('tracker_events', 'customer_phone')) {
            Schema::table('tracker_events', function (Blueprint $table) {
                $table->string('customer_phone', 20)->nullable()->index()->after('customer_id');
            });
        }

        if (!Schema::hasTable('tracker_recovery_logs')) {
            Schema::create('tracker_recovery_logs', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 64)->index();
                $table->string('phone', 20)->index();
                $table->string('channel', 20)->default('sms'); // sms, whatsapp, email
                $table->string('status', 20)->default('sent'); // sent, failed
                $table->text('message_content')->nullable();
                $table->text('api_response')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tracker_events') && Schema::hasColumn('tracker_events', 'customer_phone')) {
            Schema::table('tracker_events', function (Blueprint $table) {
                $table->dropColumn('customer_phone');
            });
        }

        Schema::dropIfExists('tracker_recovery_logs');
    }
};
