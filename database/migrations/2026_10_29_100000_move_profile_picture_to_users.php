<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Foto profil pindah ke akun: users.profile_picture (json, array path file di disk public). Foto itu
 * atribut AKUN (tampil di layout/avatar untuk siapa pun yang login), bukan data kepegawaian, jadi
 * employees.profile_picture (salinan dari user_profiles) dibuang. Data diambil dari
 * user_profiles.profile_picture apa adanya (sebagian baris lama berbentuk string JSON - dibaca
 * App\Models\User::profilePhotoPath() yang menormalkan kedua bentuk). user_profiles.profile_picture
 * TIDAK dihapus (sumber lama, tidak dipakai lagi; dibuang di rilis cleanup). File foto tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('profile_picture')->nullable();
        });

        DB::table('user_profiles')->whereNotNull('profile_picture')->orderBy('id')->each(function ($profile) {
            DB::table('users')->where('id', $profile->user_id)->update(['profile_picture' => $profile->profile_picture]);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('profile_picture');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->json('profile_picture')->nullable();
        });

        DB::table('users')->whereNotNull('profile_picture')->orderBy('id')->each(function ($user) {
            DB::table('employees')->where('user_id', $user->id)->update(['profile_picture' => $user->profile_picture]);
            // Kembalikan juga ke sumber lama supaya kode sebelum perubahan ini menemukan fotonya.
            DB::table('user_profiles')->where('user_id', $user->id)->update(['profile_picture' => $user->profile_picture]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_picture');
        });
    }
};
