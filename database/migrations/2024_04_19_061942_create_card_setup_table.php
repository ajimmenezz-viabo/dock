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
        Schema::create('card_setup', function (Blueprint $table) {
            $table->id('Id');
            $table->bigInteger('CardId')->unsigned();
            $table->string('Status', 50);
            $table->string('StatusReason');
            $table->boolean('Ecommerce')->default(false);
            $table->boolean('International')->default(false);
            $table->boolean('Stripe')->default(false);
            $table->boolean('Wallet')->default(false);
            $table->boolean('Withdrawal')->default(false);
            $table->boolean('Contactless')->default(false);
            
            $table->boolean('PinOffline')->default(false);
            $table->boolean('PinOnUs')->default(false);

            $table->foreign('CardId')->references('Id')->on('cards');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_setup');
    }
};
