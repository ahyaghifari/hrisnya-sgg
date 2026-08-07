<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Halaman Dokumen Kepegawaian portal (DocumentsPage/DocumentFormPage) - UserDocumentResource
 * (admin) + UserDocumentsPolicy + permission Shield sudah lama ada, tapi portalnya numpang
 * SISTEM_KEPEGAWAIAN + `canManage()` (cuma cek scope company, BUKAN permission). Diganti murni
 * ke Policy/permission Shield.
 *
 * Celah yang ketauan & dibenerin sekalian: delete()/restore() di DocumentsPage dan mode edit
 * di DocumentFormPage sebelumnya TIDAK PERNAH cek Delete/Restore/Update:UserDocuments sama
 * sekali - cuma cek company-scope (canManage()), siapa saja yang bisa buka halaman Dokumen
 * bisa hapus/pulihkan/edit dokumen apapun di company-nya.
 *
 * Grant ViewAny/View/Create/Update/Delete/Restore:UserDocuments ke role yang sudah punya
 * sistem_kepegawaian - safety net (sebelumnya semua operasi ini implisit bisa dilakukan siapa
 * saja yang buka halaman, jadi safety net-nya ikut yang sama biar tidak ada yang kehilangan
 * kemampuan yang sudah mereka pakai).
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = ['ViewAny:UserDocuments', 'View:UserDocuments', 'Create:UserDocuments', 'Update:UserDocuments', 'Delete:UserDocuments', 'Restore:UserDocuments'];
        $permissions = Permission::whereIn('name', $names)->get();

        if ($permissions->count() !== count($names)) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_kepegawaian'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari fitur Dokumen tanpa sengaja.
    }
};
