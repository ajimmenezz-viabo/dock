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
        Schema::create('card_setup_changes', function (Blueprint $table) {
            $table->id('Id');
            $table->bigInteger('UserId')->unsigned();
            $table->bigInteger('CardId')->unsigned();
            $table->string('Field');
            $table->string('OldValue');
            $table->string('NewValue');
            $table->string('Reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_setup_changes');
    }
};
