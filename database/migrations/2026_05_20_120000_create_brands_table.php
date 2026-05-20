<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('logo_url')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('api_config')->nullable(); // Store brand-specific API settings
            $table->timestamps();
        });

        Schema::create('denominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->onDelete('cascade');
            $table->string('provider_denom_id'); // ID from Karti API
            $table->string('name');
            $table->string('value'); // e.g., "1 Month", "10 USD"
            $table->decimal('price', 10, 2);
            $table->string('currency', 3);
            $table->string('description')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_available')->default(true);
            $table->integer('stock_quantity')->default(0); // Track stock
            $table->timestamps();
            
            $table->unique(['brand_id', 'provider_denom_id']);
        });

        Schema::create('customer_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('transaction_id')->constrained();
            $table->foreignId('denomination_id')->constrained('denominations');
            $table->string('card_code')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('face_value');
            $table->string('currency', 3);
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->json('provider_response')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('card_code');
        });

        Schema::create('balance_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->enum('type', ['credit', 'debit']);
            $table->string('description');
            $table->foreignId('transaction_id')->nullable()->constrained();
            $table->timestamps();
        });

        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users');
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('balance_history');
        Schema::dropIfExists('customer_purchases');
        Schema::dropIfExists('denominations');
        Schema::dropIfExists('brands');
    }
};
