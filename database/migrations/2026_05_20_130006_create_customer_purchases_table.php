<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customer_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->foreignId('denomination_id')->constrained()->onDelete('cascade');
            $table->text('card_code');
            $table->string('serial_number')->nullable();
            $table->string('face_value');
            $table->string('currency', 3);
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('completed');
            $table->text('provider_response')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_purchases');
    }
};
