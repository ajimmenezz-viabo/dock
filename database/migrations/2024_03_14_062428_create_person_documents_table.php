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
        Schema::create('person_documents', function (Blueprint $table) {
            $table->id('Id');
            $table->bigInteger('PersonId')->unsigned();
            $table->bigInteger('CountryId')->unsigned();
            $table->integer('TypeId')->unsigned();
            $table->string('DocumentNumber');
            $table->string('ExternalId')->nullable();
            $table->boolean('Main')->default(false);
            $table->boolean('Active')->default(true);
            $table->timestamps();

            $table->foreign('PersonId')->references('Id')->on('persons');
            $table->foreign('CountryId')->references('Id')->on('countries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_documents');
    }
};
