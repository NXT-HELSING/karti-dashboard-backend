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
        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('type', ['payment', 'purchase', 'adjustment', 'refund']);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'tpe', 'wallet'])->nullable();
            $table->string('reference')->nullable();
            $table->string('description');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credits');
    }
};
