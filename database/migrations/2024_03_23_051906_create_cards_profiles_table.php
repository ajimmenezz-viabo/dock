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
        Schema::create('card_profiles', function (Blueprint $table) {
            $table->id('Id');
            $table->string('ExternalId')->unique();
            $table->string('Profile');
            $table->string('Brand');
            $table->string('ProductType');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_profiles');
    }
};
