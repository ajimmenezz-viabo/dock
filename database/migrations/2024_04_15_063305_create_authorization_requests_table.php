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
        Schema::create('authorization_requests', function (Blueprint $table) {
            $table->id('Id');
            $table->string('UUID');
            $table->string('ExternalId');
            $table->string('AuthorizationCode');
            $table->text('Endpoint');
            $table->text('Headers');
            $table->text('Body')->nullable();
            $table->text('Response');
            $table->text('Error')->nullable();
            $table->integer('Code');
            $table->string('CardExternalId')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorization_requests');
    }
};
