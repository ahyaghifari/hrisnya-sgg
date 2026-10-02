<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengisi Jadwal tambahan selain Kepala Ruangan (schedules.head_user_id) - hak di roster
     * jadwal shift sama persis dengan Kepala Ruangan (isi roster via approval, ganti tim, urutkan).
     */
    public function up(): void
    {
        Schema::create('schedule_editors', function (Blueprint $table) {
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['schedule_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_editors');
    }
};
