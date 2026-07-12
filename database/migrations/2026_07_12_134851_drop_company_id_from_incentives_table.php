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
        Schema::table('incentives', function (Blueprint $table) {
            // Drop by column (bukan nama constraint) — portable ke SQLite (dipakai test),
            // Laravel otomatis resolve ke nama constraint fisik yang benar di MySQL.
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incentives', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('id')->constrained('m_companies')->cascadeOnDelete();
        });
    }
};
