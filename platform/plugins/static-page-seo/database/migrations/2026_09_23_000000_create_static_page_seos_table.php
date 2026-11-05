<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('static_page_seos', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('page_key', 120)->unique();
            $table->string('url_path', 255)->nullable();
            $table->string('status', 60)->default('published');
            $table->timestamps();
        });

        $defaultPages = [
            ['name' => 'Homepage', 'page_key' => 'home', 'url_path' => '/'],
            ['name' => 'About Us', 'page_key' => 'about', 'url_path' => '/about'],
            ['name' => 'Contact Us', 'page_key' => 'contact', 'url_path' => '/contact'],
            ['name' => 'Shipping & Delivery', 'page_key' => 'shipping-and-delivery', 'url_path' => '/shipping-and-delivery'],
            ['name' => 'Terms & Conditions', 'page_key' => 'terms', 'url_path' => '/terms'],
            ['name' => 'Privacy Policy', 'page_key' => 'privacy', 'url_path' => '/privacy'],
            ['name' => 'Refund Policy', 'page_key' => 'refund-policy', 'url_path' => '/refund-policy'],
            ['name' => 'Store Locator', 'page_key' => 'store-locator', 'url_path' => '/store-locator'],
            ['name' => 'Sale', 'page_key' => 'sale', 'url_path' => '/sale'],
            ['name' => 'Export', 'page_key' => 'export', 'url_path' => '/export'],
            ['name' => 'Lookbook', 'page_key' => 'lookbook', 'url_path' => '/lookbook'],
            ['name' => 'Shop', 'page_key' => 'shop', 'url_path' => '/shop'],
        ];

        $now = now();
        foreach ($defaultPages as $page) {
            DB::table('static_page_seos')->insert([
                'name' => $page['name'],
                'page_key' => $page['page_key'],
                'url_path' => $page['url_path'],
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('static_page_seos');
    }
};
