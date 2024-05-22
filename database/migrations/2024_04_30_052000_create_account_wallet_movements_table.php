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
        Schema::create('account_wallet_movements', function (Blueprint $table) {
            $table->id('Id');
            $table->unsignedBigInteger('WalletId');
            $table->string('Type');
            $table->string('Description');
            $table->decimal('Amount', 10, 2);
            $table->decimal('Balance', 10, 2);
            $table->foreign('WalletId')->references('Id')->on('account_wallets');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_wallet_movements');
    }
};
