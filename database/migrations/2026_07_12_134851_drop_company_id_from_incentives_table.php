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
        // Tabel ini di-rename dari program_incentives -> incentives (migrasi
        // 2026_07_12_072338), dan RENAME TABLE di MySQL TIDAK ikut me-rename nama
        // constraint FK-nya — constraint fisiknya masih program_incentives_company_id_foreign,
        // bukan incentives_company_id_foreign yang ditebak Laravel. Makanya cari nama
        // constraint aslinya dulu dari information_schema sebelum di-drop.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // information_schema nggak ada di SQLite (test DB) — dropForeign(['company_id'])
            // portable di sana karena SQLite resolve sendiri nama constraint-nya.
            Schema::table('incentives', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
            });
        } else {
            $connection = Schema::getConnection()->getDatabaseName();
            $foreignKey = Schema::getConnection()->select(
                "select CONSTRAINT_NAME from information_schema.KEY_COLUMN_USAGE
                 where TABLE_SCHEMA = ? and TABLE_NAME = 'incentives' and COLUMN_NAME = 'company_id'
                 and REFERENCED_TABLE_NAME is not null",
                [$connection]
            );

            if (! empty($foreignKey)) {
                Schema::table('incentives', function (Blueprint $table) use ($foreignKey) {
                    $table->dropForeign($foreignKey[0]->CONSTRAINT_NAME);
                });
            }
        }

        Schema::table('incentives', function (Blueprint $table) {
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
