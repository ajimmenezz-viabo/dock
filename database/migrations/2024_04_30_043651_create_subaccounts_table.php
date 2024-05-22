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
        Schema::create('subaccounts', function (Blueprint $table) {
            $table->id('Id');
            $table->unsignedBigInteger('AccountId');
            $table->string('UUID', 36)->unique();
            $table->string('ExternalId')->nullable();
            $table->string('Description', 120);
            $table->foreign('AccountId')->references('Id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subaccounts');
    }
};
