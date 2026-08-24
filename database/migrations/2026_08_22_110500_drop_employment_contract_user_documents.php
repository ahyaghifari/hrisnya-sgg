<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * UserDocumentType::EMPLOYMENT_CONTRACT dihapus (lihat App\Enum\UserDocumentType) - surat
 * kontrak sekarang nempel di user_contracts.file_path, bukan lagi arsip generik di sini.
 * Baris lama dihapus (dikonfirmasi user 2026-08-22: "dihapus aja nggpp yang sudah ada") -
 * fiturnya belum dipakai serius, tidak perlu dipindah manual ke user_contracts.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('user_documents')->where('document_type', 'employment_contract')->get();

        foreach ($rows as $row) {
            if ($row->file_path) {
                Storage::disk('public')->delete($row->file_path);
            }
        }

        DB::table('user_documents')->where('document_type', 'employment_contract')->delete();
    }

    public function down(): void
    {
        // Tidak bisa dipulihkan - baris & file-nya sudah dihapus permanen di up().
    }
};
