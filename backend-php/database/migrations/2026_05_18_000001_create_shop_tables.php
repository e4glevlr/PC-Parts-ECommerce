<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('password');
            $table->string('full_name', 100);
            $table->string('phone', 20)->unique();
            $table->text('address')->nullable();
            $table->foreignId('role_id')->constrained('roles');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 500)->unique();
            $table->string('token_type', 50)->default('ACCESS_TOKEN');
            $table->dateTime('expiration_date');
            $table->boolean('revoked')->default(false);
            $table->boolean('expired')->default(false);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->foreignId('parent_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2);
            $table->integer('quantity')->default(0);
            $table->integer('low_stock_threshold')->default(10);
            $table->foreignId('category_id')->constrained('categories');
            $table->json('specifications')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('image_url', 500);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('attribute_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('display_name', 200);
            $table->string('data_type', 20);
            $table->string('input_type', 30);
            $table->string('unit', 50)->nullable();
            $table->integer('sort_order')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['category_id', 'code'], 'uq_attrdef_category_code');
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 15, 2);
            $table->decimal('minimum_order_amount', 15, 2)->default(0);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 50)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('customer_name', 100);
            $table->string('customer_email', 100);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2);
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->string('status', 20)->default('PENDING');
            $table->string('payment_method', 20)->default('COD');
            $table->text('shipping_address');
            $table->string('shipping_phone', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('product_name', 200);
            $table->integer('quantity');
            $table->decimal('price', 15, 2);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('parent_comment_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_staff_reply')->default(false);
            $table->timestamps();
        });

        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->string('change_type', 10);
            $table->integer('quantity_change');
            $table->string('reason', 200)->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('attribute_definitions');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
