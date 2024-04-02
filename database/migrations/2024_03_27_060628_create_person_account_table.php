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
        Schema::create('person_account', function (Blueprint $table) {
            $table->id('Id');
            $table->bigInteger('PersonId')->unsigned();
            $table->string('ExternalId');
            $table->string('ClientId');
            $table->string('BookId');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_account');
    }
};
