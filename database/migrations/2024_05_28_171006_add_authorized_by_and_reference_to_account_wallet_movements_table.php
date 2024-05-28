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
            $table->unsignedBigInteger('ApprovedBy')->nullable()->after('WalletId');
            $table->string('Reference')->nullable()->after('Balance');

            $table->foreign('ApprovedBy')->references('Id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_wallet_movements', function (Blueprint $table) {
            $table->dropColumn('ApprovedBy');
            $table->dropColumn('Reference');

            $table->dropForeign(['ApprovedBy']);
        });
    }
};
