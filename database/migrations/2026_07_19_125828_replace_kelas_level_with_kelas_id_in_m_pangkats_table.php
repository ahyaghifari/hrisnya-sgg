<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ganti `start_kelas_level`/`max_kelas_level` (integer level lepas, gampang salah cocok
     * kalau 2 Kelas beda punya level sama) jadi FK sungguhan `start_kelas_id`/`max_kelas_id`
     * ke `m_kelas.id` — request user setelah tahu kolom lama itu manual/tidak relational.
     * Backfill dicocokkan lewat (company_id, level) — ambil baris Kelas pertama yang match,
     * baris yang levelnya tidak match Kelas mana pun di company itu dibiarkan NULL (lebih
     * aman daripada nebak).
     */
    public function up(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->foreignUlid('start_kelas_id')->nullable()->after('start_kelas_level')->constrained('m_kelas')->nullOnDelete();
            $table->foreignUlid('max_kelas_id')->nullable()->after('max_kelas_level')->constrained('m_kelas')->nullOnDelete();
        });

        $pangkats = DB::table('m_pangkats')
            ->whereNotNull('start_kelas_level')
            ->orWhereNotNull('max_kelas_level')
            ->get(['id', 'company_id', 'start_kelas_level', 'max_kelas_level']);

        foreach ($pangkats as $pangkat) {
            $startKelasId = $pangkat->start_kelas_level === null ? null : DB::table('m_kelas')
                ->where('company_id', $pangkat->company_id)
                ->where('level', $pangkat->start_kelas_level)
                ->value('id');

            $maxKelasId = $pangkat->max_kelas_level === null ? null : DB::table('m_kelas')
                ->where('company_id', $pangkat->company_id)
                ->where('level', $pangkat->max_kelas_level)
                ->value('id');

            DB::table('m_pangkats')
                ->where('id', $pangkat->id)
                ->update(['start_kelas_id' => $startKelasId, 'max_kelas_id' => $maxKelasId]);
        }

        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->dropColumn(['start_kelas_level', 'max_kelas_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->unsignedInteger('start_kelas_level')->nullable()->after('company_id');
            $table->unsignedInteger('max_kelas_level')->nullable()->after('start_kelas_level');
        });

        $pangkats = DB::table('m_pangkats')
            ->whereNotNull('start_kelas_id')
            ->orWhereNotNull('max_kelas_id')
            ->get(['id', 'start_kelas_id', 'max_kelas_id']);

        foreach ($pangkats as $pangkat) {
            $startLevel = $pangkat->start_kelas_id === null ? null : DB::table('m_kelas')->where('id', $pangkat->start_kelas_id)->value('level');
            $maxLevel = $pangkat->max_kelas_id === null ? null : DB::table('m_kelas')->where('id', $pangkat->max_kelas_id)->value('level');

            DB::table('m_pangkats')
                ->where('id', $pangkat->id)
                ->update(['start_kelas_level' => $startLevel, 'max_kelas_level' => $maxLevel]);
        }

        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('start_kelas_id');
            $table->dropConstrainedForeignId('max_kelas_id');
        });
    }
};
