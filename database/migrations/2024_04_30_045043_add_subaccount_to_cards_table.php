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
            $table->unsignedBigInteger('SubAccountId')->nullable()->after('CreatorId');
            $table->string('STPAccount')->nullable()->after('Balance');
            $table->foreign('SubAccountId')->references('Id')->on('subaccounts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['SubAccountId']);
            $table->dropColumn('SubAccountId');
            $table->dropColumn('STPAccount');
        });
    }
};
