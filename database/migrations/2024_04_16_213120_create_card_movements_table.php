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
        Schema::create('card_movements', function (Blueprint $table) {
            $table->id('Id');
            $table->bigInteger('CardId')->unsigned();
            $table->string('Type');
            $table->decimal('Amount', 10, 2);
            $table->decimal('Balance', 10, 2);
            $table->foreign('CardId')->references('Id')->on('cards');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_movements');
    }
};
