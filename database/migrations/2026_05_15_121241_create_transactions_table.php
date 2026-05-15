<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // 'karti', later others
            $table->string('denom_id');
            $table->string('brand_id')->nullable();
            $table->decimal('amount_paid', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status'); // 'pending', 'completed', 'failed'
            $table->string('reserve_id')->nullable(); // from provider
            $table->string('partner_transaction_id')->nullable(); // our UUID
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
