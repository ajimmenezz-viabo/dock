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
        Schema::create('authorization_profiles', function (Blueprint $table) {
            $table->id('Id');
            $table->decimal('MaxDailyAmountTPV', 10, 2)->default(0);
            $table->decimal('MaxDailyAmountATM', 10, 2)->default(0);
            $table->integer('MaxDailyOperationsTPV')->default(0);
            $table->decimal('MaxAmountTPV', 10, 2)->default(0);
            $table->decimal('MaxAmountATM', 10, 2)->default(0);
            $table->decimal('MaxAmountMonthlyTPV', 10, 2)->default(0);
            $table->decimal('MaxAmountMonthlyATM', 10, 2)->default(0);
            $table->integer('MaxOperationsMonthlyTPV')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorization_profiles');
    }
};
