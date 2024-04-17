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
        Schema::create('embossing_batches', function (Blueprint $table) {
            $table->id('Id');
            $table->bigInteger('UserId')->unsigned();
            $table->string('ExternalId')->unique();
            $table->integer('TotalCards');
            $table->string('Status');
            $table->foreign('UserId')->references('Id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('embossing_batches');
    }
};
