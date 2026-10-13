<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // STR/SIP nakes: punya nomor & masa berlaku (berlaku_sampai NULL = seumur hidup), satu
        // dokumen per tipe per karyawan. file_path = file terenkripsi di disk privat 'local'.
        Schema::create('employee_nakes_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type'); // STR | SIP
            $table->string('nomor')->nullable();
            $table->string('file_path');
            $table->date('berlaku_sampai')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'type']);
            $table->index('berlaku_sampai');
        });

        // Pindahkan STR/SIP dari employee_documents (file saja; nomor & tanggal diisi ulang karyawan).
        // File terenkripsi tidak dipindah, file_path sama.
        $rows = DB::table('employee_documents')->whereIn('type', ['STR', 'SIP'])->get();

        foreach ($rows as $r) {
            DB::table('employee_nakes_documents')->insertOrIgnore([
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'employee_id' => $r->employee_id,
                'type' => $r->type,
                'file_path' => $r->file_path,
                'created_at' => $r->created_at,
                'updated_at' => now(),
            ]);
        }

        // Hapus salinan lama HANYA yang sudah pindah (user_profile_documents = sumber asal salinan itu).
        DB::table('user_profile_documents as d')
            ->join('employees as e', 'e.user_id', '=', 'd.user_id')
            ->whereIn('d.label', ['STR', 'SIP'])
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('employee_nakes_documents as n')
                ->whereColumn('n.employee_id', 'e.id')->whereColumn('n.type', 'd.label'))
            ->delete();
        DB::table('employee_documents')->whereIn('type', ['STR', 'SIP'])->delete();
    }
    public function down(): void
    {
        Schema::dropIfExists('employee_nakes_documents');
    }
};
