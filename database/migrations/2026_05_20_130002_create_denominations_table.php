<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('denominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->onDelete('cascade');
            $table->string('provider_denom_id');
            $table->string('name');
            $table->string('value');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3);
            $table->string('description')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_available')->default(true);
            $table->integer('stock_quantity')->default(-1); // -1 = unlimited
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->unique(['brand_id', 'provider_denom_id']);
            $table->index('is_available');
        });
    }

    public function down()
    {
        Schema::dropIfExists('denominations');
    }
};
