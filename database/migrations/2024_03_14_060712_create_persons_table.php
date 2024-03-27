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
        Schema::create('persons', function (Blueprint $table) {
            $table->id('Id');
            $table->string('UUID')->unique();
            $table->bigInteger('UserId')->unsigned();
            $table->integer('PersonType')->unsigned()->default(1); // 1: Legal, 2: Natural
            $table->bigInteger('CountryId')->unsigned();
            /**Legal Person Fields**/
            $table->string('LegalName')->nullable();
            $table->string('TradeName')->nullable();
            $table->string('RFC')->nullable();

            /**Natural Person Fields**/
            $table->integer('GenderId')->unsigned()->nullable();
            $table->integer('MaritalStatusId')->unsigned()->nullable();
            $table->string('FullName')->nullable();
            $table->string('PreferredName')->nullable();
            $table->string('MotherName')->nullable();
            $table->string('FatherName')->nullable();
            $table->date('BirthDate')->nullable();
            $table->boolean(('IsEmancipated'))->default(false);
            $table->integer('NationalityId')->unsigned()->nullable();

            $table->string('ExternalId')->nullable();
            $table->boolean('Active')->default(true);

            $table->foreign('CountryId')->references('Id')->on('countries');
            $table->foreign('UserId')->references('Id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
