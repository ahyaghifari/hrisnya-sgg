<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar kode shift yang BOLEH dipakai 1 regu (mis. Regu A cuma boleh Pagi/Siang, tidak boleh
     * Malam yang jatah Regu B) — pivot many-to-many, bukan kolom di schedule_shift_teams karena 1
     * regu bisa punya beberapa kode shift sekaligus. Tabel ini KOSONG oleh default (regu lama/baru
     * yang belum diisi = TIDAK dibatasi, lihat ScheduleShiftTeamMember::allowedShiftTypeIdsFor()) -
     * biar jadwal existing tidak mendadak semua sel dianggap "tidak boleh" begitu migration jalan.
     */
    public function up(): void
    {
        Schema::create('schedule_shift_team_shift_types', function (Blueprint $table) {
            // bigIncrements, BUKAN ulid - tabel ini murni pivot polos ditulis lewat sync()
            // (Model::belongsToMany), tidak pernah di-create() lewat Eloquent jadi tidak butuh
            // HasUlids buat isi id-nya.
            $table->id();
            $table->foreignUlid('team_id')->constrained('schedule_shift_teams')->cascadeOnDelete();
            $table->foreignUlid('shift_type_id')->constrained('schedule_shift_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'shift_type_id'], 'sstst_team_shift_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shift_team_shift_types');
    }
};
