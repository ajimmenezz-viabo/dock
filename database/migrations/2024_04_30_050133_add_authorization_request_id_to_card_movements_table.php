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
        Schema::table('card_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('AuthorizationRequestId')->nullable()->after('CardId');
            $table->string('Description')->nullable()->after('Type');
            $table->foreign('AuthorizationRequestId')->references('Id')->on('authorization_requests');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_movements', function (Blueprint $table) {
            $table->dropForeign(['AuthorizationRequestId']);
            $table->dropColumn('AuthorizationRequestId');
            $table->dropColumn('Description');

        });
    }
};
