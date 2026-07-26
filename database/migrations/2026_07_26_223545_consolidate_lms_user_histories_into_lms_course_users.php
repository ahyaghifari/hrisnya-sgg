<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * lms_user_histories nyaris seluruhnya duplikat lms_course_users (score/is_passed/passed_at
     * sama-sama disimpan di dua tempat untuk pasangan user+course yang sama — risiko data drift).
     * Satu-satunya kolom unik: certificate_url & archived_at. Konsolidasi: pindahkan 2 kolom itu
     * ke lms_course_users, lalu drop lms_user_histories. schedule_id di tabel lama tidak ikut
     * dipindah — cuma nunjuk ke lms_course_schedules yang memang sudah dipastikan kosong/mati.
     */
    public function up(): void
    {
        Schema::table('lms_course_users', function (Blueprint $table) {
            $table->string('certificate_url')->nullable()->after('passed_at');
            $table->timestamp('archived_at')->nullable()->after('certificate_url');
        });

        if (Schema::hasTable('lms_user_histories')) {
            DB::table('lms_user_histories')->orderBy('id')->chunk(200, function ($histories) {
                foreach ($histories as $history) {
                    DB::table('lms_course_users')
                        ->where('user_id', $history->user_id)
                        ->where('course_id', $history->course_id)
                        ->update([
                            'certificate_url' => $history->certificate_url,
                            'archived_at' => $history->archived_at,
                        ]);
                }
            });
        }

        Schema::dropIfExists('lms_user_histories');
    }

    /**
     * Sengaja tidak direkonstruksi — lihat catatan di migration drop_legacy_trainer_honorarium_cluster.
     */
    public function down(): void
    {
        //
    }
};
