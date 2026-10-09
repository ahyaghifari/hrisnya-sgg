<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pengaturan per kartu Board: anggota Board selain pelaksana/pembuat boleh menambah lampiran (di luar komentar). */
    public function up(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->boolean('members_can_attach')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropColumn('members_can_attach');
        });
    }
};
