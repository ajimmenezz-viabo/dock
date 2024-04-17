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
        Schema::create('authorization_profile_card', function (Blueprint $table) {
            $table->id('Id');
            $table->bigInteger('AuthorizationProfileId')->unsigned();
            $table->bigInteger('CardId')->unsigned();
            $table->foreign('AuthorizationProfileId')->references('Id')->on('authorization_profiles');
            $table->foreign('CardId')->references('Id')->on('cards');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorization_profile_card');
    }
};
