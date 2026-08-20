<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Religions dulunya per-company (lihat 2025_11_11_103930_add_company_id_master_data_table.php),
 * padahal isinya cuma 6 agama resmi + Lainnya - global concept, tidak ada alasan dipisah per
 * company (menyebabkan baris duplikat per company, mis. "Islam" 2x). Gabungkan duplikat by name,
 * alihkan FK user_profiles.religion_id ke baris gabungan, baru hapus kolom company_id. */
return new class extends Migration
{
    public function up(): void
    {
        $groups = DB::table('m_religions')->orderBy('created_at')->get()->groupBy('name');

        foreach ($groups as $name => $rows) {
            if ($rows->count() < 2) {
                continue;
            }

            $canonicalId = $rows->first()->id;
            $duplicateIds = $rows->slice(1)->pluck('id');

            DB::table('user_profiles')->whereIn('religion_id', $duplicateIds)->update(['religion_id' => $canonicalId]);
            DB::table('m_religions')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('m_religions', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('m_religions', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('name')->constrained('m_companies')->nullOnDelete();
        });
    }
};
