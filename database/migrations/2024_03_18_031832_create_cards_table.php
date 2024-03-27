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
        Schema::create('cards', function (Blueprint $table) {
            $table->id('Id');
            $table->string('UUID')->unique();
            $table->bigInteger('CreatorId')->unsigned();
            $table->bigInteger('PersonId')->unsigned();
            $table->string('Type')->default('virtual');
            $table->string('ActiveFunction')->default('credit');
            $table->string('ExternalId')->nullable();
            $table->string('Brand')->nullable();
            $table->string('MaskedPan')->nullable();
            $table->string('Pan')->nullable();
            $table->string('ExpirationDate')->nullable();
            $table->string('CVV')->nullable();
            $table->timestamps();

            $table->foreign('CreatorId')->references('Id')->on('users');
            $table->foreign('PersonId')->references('Id')->on('persons');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
