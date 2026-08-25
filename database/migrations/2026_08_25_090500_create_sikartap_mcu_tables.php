<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — rewrite form_asesmen_perawat.php/form_hasil_laboratorium.php/
 * form_hasil_radiologi.php/form_pemeriksaan_dokter.php legacy. 4 tabel terpisah (1:1
 * per pengajuan), sesuai skema mysqli asli - field-nya persis, cuma FK ulid & disk
 * public (bukan folder uploads/ terpisah tanpa auth check kayak legacy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sikartap', function (Blueprint $table) {
            $table->text('catatan_mcu')->nullable()->after('status_mcu');
        });

        Schema::create('sikartap_mcu_nurse_assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sikartap_id')->unique()->constrained('sikartap')->cascadeOnDelete();

            $table->decimal('tinggi_badan', 5, 1);
            $table->decimal('berat_badan', 5, 1);
            $table->string('tekanan_darah');
            $table->unsignedSmallInteger('nadi');
            $table->decimal('suhu', 4, 1);
            $table->unsignedSmallInteger('pernafasan');

            $table->text('keluhan_kesehatan')->nullable();

            $table->boolean('rpk_hipertensi')->default(false);
            $table->boolean('rpk_stroke')->default(false);
            $table->boolean('rpk_jantung')->default(false);
            $table->boolean('rpk_diabetes')->default(false);
            $table->string('rpk_lainnya')->nullable();

            $table->text('riwayat_penyakit_dahulu')->nullable();
            $table->text('riwayat_operasi')->nullable();

            $table->boolean('alergi_makanan')->default(false);
            $table->boolean('alergi_obat')->default(false);
            $table->boolean('alergi_debu')->default(false);
            $table->string('alergi_lainnya')->nullable();

            $table->boolean('kebiasaan_olahraga')->default(false);
            $table->boolean('kebiasaan_merokok')->default(false);
            $table->boolean('kebiasaan_alkohol')->default(false);
            $table->boolean('kebiasaan_obat')->default(false);
            $table->string('kebiasaan_lainnya')->nullable();

            $table->string('hasil_ekg')->nullable();
            $table->text('keterangan_ekg')->nullable();
            $table->string('file_ekg')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();
        });

        Schema::create('sikartap_mcu_lab_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sikartap_id')->unique()->constrained('sikartap')->cascadeOnDelete();

            foreach (['hemoglobin', 'leukosit', 'eritrosit', 'trombosit', 'hematokrit', 'basofil', 'eosinofil', 'stab', 'segmen', 'limfosit', 'monosit', 'mcv', 'mch', 'mchc', 'gds', 'gda'] as $field) {
                $table->string($field)->nullable();
            }

            $table->string('hbsag')->nullable();
            $table->string('hiv')->nullable();
            $table->text('kesimpulan')->nullable();
            $table->string('file_path')->nullable();

            $table->timestamps();
        });

        Schema::create('sikartap_mcu_radiology_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sikartap_id')->unique()->constrained('sikartap')->cascadeOnDelete();

            $table->string('jenis_pemeriksaan')->nullable();
            $table->string('jantung')->nullable();
            $table->string('paru_kanan')->nullable();
            $table->string('paru_kiri')->nullable();
            $table->string('diafragma')->nullable();
            $table->string('tulang')->nullable();
            $table->text('kesimpulan')->nullable();
            $table->string('file_path')->nullable();

            $table->timestamps();
        });

        Schema::create('sikartap_mcu_doctor_examinations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sikartap_id')->unique()->constrained('sikartap')->cascadeOnDelete();

            foreach ([
                'kulit', 'rambut', 'visus_mata', 'buta_warna', 'lapang_pandang',
                'hidung', 'telinga', 'tonsil', 'faring', 'gigi',
                'kelenjar_gondok',
                'suara_pernafasan', 'ronchi', 'wheezing', 'lainnya_paru', 'jantung',
                'hati', 'limpa', 'epigastrium', 'ginjal', 'lainnya_abdomen',
                'ekstremitas', 'neurologi',
            ] as $field) {
                $table->string($field)->nullable();
            }

            $table->text('diagnosa')->nullable();
            $table->text('rekomendasi')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikartap_mcu_doctor_examinations');
        Schema::dropIfExists('sikartap_mcu_radiology_results');
        Schema::dropIfExists('sikartap_mcu_lab_results');
        Schema::dropIfExists('sikartap_mcu_nurse_assessments');

        Schema::table('sikartap', function (Blueprint $table) {
            $table->dropColumn('catatan_mcu');
        });
    }
};
