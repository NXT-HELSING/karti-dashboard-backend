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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('total_paid', 10, 2)->default(0)->after('email');
            $table->decimal('total_spent', 10, 2)->default(0)->after('total_paid');
            $table->decimal('credit_balance', 10, 2)->default(0)->after('total_spent');
        });

        // Initialize credit_balance and total_paid from existing balance for smoother transition
        \Illuminate\Support\Facades\DB::statement('UPDATE users SET credit_balance = balance, total_paid = balance');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_paid', 'total_spent', 'credit_balance']);
        });
    }
};
