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
        Schema::create('person_setup', function (Blueprint $table) {
            $table->id('Id');
            $table->integer('ExternalId');
            $table->string('Category');
            $table->string('Description');
            $table->dateTime('ExternalCreatedAt');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_setup');
    }
};
