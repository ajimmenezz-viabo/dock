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
        Schema::table('cards', function (Blueprint $table) {
            $table->bigInteger('BatchId')->unsigned()->nullable()->after('Id');
            $table->string('Balance')->nullable()->after('CVV');
            $table->foreign('BatchId')->references('Id')->on('embossing_batches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['BatchId']);
            $table->dropColumn('BatchId');
            $table->dropColumn('Balance');
        });
    }
};
