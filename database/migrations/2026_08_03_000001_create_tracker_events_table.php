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
        Schema::create('tracker_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 50);
            $table->json('event_data')->nullable();
            $table->string('session_id', 64)->index();
            $table->string('visitor_id', 64)->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('page_url', 500);
            $table->string('page_title', 300)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('utm_source', 255)->nullable();
            $table->string('utm_medium', 255)->nullable();
            $table->string('utm_campaign', 255)->nullable();
            $table->string('utm_term', 255)->nullable();
            $table->string('utm_content', 255)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('os', 100)->nullable();
            $table->integer('screen_width')->nullable();
            $table->integer('screen_height')->nullable();
            $table->string('language', 50)->nullable();
            $table->string('country', 10)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->decimal('revenue', 10, 2)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['event_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracker_events');
    }
};
