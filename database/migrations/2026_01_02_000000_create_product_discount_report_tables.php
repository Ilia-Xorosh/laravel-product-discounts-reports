<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pd_report_baskets', function (Blueprint $table) {
            $table->id();
            $table->string('basket_guid')->unique();
            $table->string('order_from', 100)->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->dateTime('paid_at')->nullable()->index();
            $table->dateTime('calculated_at')->index();
            $table->bigInteger('start_price');
            $table->bigInteger('final_price');
            $table->bigInteger('discount');
            $table->json('items');
            $table->string('applied_promocode')->nullable();
            $table->timestamps();
        });
        Schema::create('pd_report_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basket_id')->constrained('pd_report_baskets')->cascadeOnDelete();
            $table->string('name')->index();
            $table->json('snapshot');
            $table->bigInteger('start_price'); $table->bigInteger('final_price'); $table->bigInteger('discount');
        });
        Schema::create('pd_report_doings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained('pd_report_actions')->cascadeOnDelete();
            $table->string('name'); $table->string('type'); $table->string('applie');
            $table->string('type_discount'); $table->unsignedSmallInteger('condition_id');
            $table->json('snapshot');
            $table->bigInteger('start_price'); $table->bigInteger('final_price'); $table->bigInteger('discount');
        });
        Schema::create('pd_report_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doing_id')->constrained('pd_report_doings')->cascadeOnDelete();
            $table->string('product_id'); $table->string('product_name'); $table->unsignedInteger('quantity');
            $table->bigInteger('start_subtotal'); $table->bigInteger('final_subtotal'); $table->bigInteger('discount');
        });
    }

    public function down(): void
    {
        foreach (['pd_report_products', 'pd_report_doings', 'pd_report_actions', 'pd_report_baskets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
