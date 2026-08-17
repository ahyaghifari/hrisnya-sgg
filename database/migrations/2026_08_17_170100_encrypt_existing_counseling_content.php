<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Counseling::issue/response/solution baru dikasih cast 'encrypted' (APP_KEY) di migration
 * sebelumnya - baris LAMA masih plaintext mentah di DB, kalau dibiarkan bakal DecryptException
 * begitu dibaca lewat model (cast 'encrypted' expect payload sudah terenkripsi). Enkripsi
 * ulang isi baris existing di sini SEBELUM ada yang baca lewat model.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('counselings')->orderBy('id')->each(function ($row) {
            DB::table('counselings')->where('id', $row->id)->update([
                'issue' => $row->issue !== null ? Crypt::encryptString($row->issue) : null,
                'response' => $row->response !== null ? Crypt::encryptString($row->response) : null,
                'solution' => $row->solution !== null ? Crypt::encryptString($row->solution) : null,
            ]);
        });
    }

    public function down(): void
    {
        DB::table('counselings')->orderBy('id')->each(function ($row) {
            DB::table('counselings')->where('id', $row->id)->update([
                'issue' => $row->issue !== null ? Crypt::decryptString($row->issue) : null,
                'response' => $row->response !== null ? Crypt::decryptString($row->response) : null,
                'solution' => $row->solution !== null ? Crypt::decryptString($row->solution) : null,
            ]);
        });
    }
};
