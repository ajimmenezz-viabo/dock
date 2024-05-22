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
        Schema::create('account_wallets', function (Blueprint $table) {
            $table->id('Id');
            $table->string('UUID', 36)->unique();
            $table->unsignedBigInteger('AccountId');
            $table->unsignedBigInteger('SubAccountId')->nullable();
            $table->string('STPAccount')->nullable();
            $table->string('Balance');
            $table->foreign('AccountId')->references('Id')->on('users');
            $table->foreign('SubAccountId')->references('Id')->on('subaccounts');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_wallets');
    }
};
