<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengganti cuti bisa lebih dari 1 orang - menggantikan kolom tunggal
     * attendance_req_leave_details.substitute_user_id. Data lama dipindah dulu ke tabel baru.
     */
    public function up(): void
    {
        Schema::create('attendance_req_leave_substitutes', function (Blueprint $table) {
            $table->foreignUlid('leave_detail_id')->constrained('attendance_req_leave_details')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['leave_detail_id', 'user_id']);
        });

        DB::table('attendance_req_leave_details')->whereNotNull('substitute_user_id')->get(['id', 'substitute_user_id'])
            ->each(fn ($row) => DB::table('attendance_req_leave_substitutes')->insert([
                'leave_detail_id' => $row->id,
                'user_id' => $row->substitute_user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        Schema::table('attendance_req_leave_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('substitute_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_req_leave_details', function (Blueprint $table) {
            $table->foreignUlid('substitute_user_id')->nullable()->after('total_days')->constrained('users')->nullOnDelete();
        });

        // Kembali ke 1 pengganti: ambil pengganti pertama saja.
        DB::table('attendance_req_leave_substitutes')->orderBy('created_at')->get()->unique('leave_detail_id')
            ->each(fn ($row) => DB::table('attendance_req_leave_details')
                ->where('id', $row->leave_detail_id)->update(['substitute_user_id' => $row->user_id]));

        Schema::dropIfExists('attendance_req_leave_substitutes');
    }
};
