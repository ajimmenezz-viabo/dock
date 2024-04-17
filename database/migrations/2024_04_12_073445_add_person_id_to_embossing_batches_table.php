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
        Schema::table('embossing_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('PersonId')->nullable()->after('UserId');
            $table->foreign('PersonId')->references('Id')->on('persons');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('embossing_batches', function (Blueprint $table) {
            $table->dropForeign(['PersonId']);
            $table->dropColumn('PersonId');
        });
    }
};
