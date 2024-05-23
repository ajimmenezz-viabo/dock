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
        Schema::table('account_wallet_movements', function (Blueprint $table) {
            $table->string('UUID')->after('Id');
            $table->unsignedBigInteger('CardId')->nullable()->after('WalletId');
            $table->foreign('CardId')->references('Id')->on('cards');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_wallet_movements', function (Blueprint $table) {
            $table->dropColumn('UUID');
            $table->dropForeign(['CardId']);
            $table->dropColumn('CardId');
        });
    }
};
