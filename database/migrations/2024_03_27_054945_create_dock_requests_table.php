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
        Schema::create('dock_requests', function (Blueprint $table) {
            $table->id('Id');
            $table->string('Endpoint');
            $table->string('Method');
            $table->string('AuthType');
            $table->json('Body');
            $table->json('Headers');
            $table->json('Response')->nullable();
            $table->json('Error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dock_requests');
    }
};
