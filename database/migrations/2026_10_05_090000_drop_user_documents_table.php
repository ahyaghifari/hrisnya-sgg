<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Modul user_documents (arsip surat HR upload manual: resignation, retirement, assignment, dst)
 * dihapus - 0 baris di production, digantikan modul generate-engine per jenis surat (EmployeeSp
 * utk SP, rencana serupa utk Surat Tugas). UserDocuments model, UserDocumentType enum,
 * UserDocumentsPolicy, UserDocumentResource (Filament), DocumentsPage/DocumentFormPage (portal)
 * dihapus dari kode di commit yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_documents');

        $names = ['ViewAny:UserDocuments', 'View:UserDocuments', 'Create:UserDocuments', 'Update:UserDocuments', 'Delete:UserDocuments', 'Restore:UserDocuments'];
        Permission::whereIn('name', $names)->get()->each(fn (Permission $permission) => $permission->delete());

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - tabel kosong di production, dan permission Shield hilang
        // akan dibangkitkan ulang otomatis lewat php artisan shield:generate kalau modul ini
        // pernah dipasang balik.
    }
};
