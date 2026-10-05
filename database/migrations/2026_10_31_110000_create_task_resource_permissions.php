<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permission gaya Shield untuk resource admin Tugas Individu (Task) dan Board (TaskProject).
 * Dikelola per role lewat Role Access; Super Admin otomatis punya semua.
 */
return new class extends Migration
{
    private const SUBJECTS = ['Task', 'TaskProject'];

    private const VERBS = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete'];

    public function up(): void
    {
        foreach (self::SUBJECTS as $subject) {
            foreach (self::VERBS as $verb) {
                Permission::firstOrCreate(['name' => "{$verb}:{$subject}", 'guard_name' => 'web']);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Sengaja tidak dihapus - permission mungkin sudah dibagikan ke role.
    }
};
