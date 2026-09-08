<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Sarana & Prasarana dihapus total dari HRIS (instruksi user 2026-09-08) — alur
 * persetujuannya, permission (sistem_sarana_prasarana, ajukan_sarana_prasarana), dan grupnya
 * di tree Hak Akses. Kode Livewire/Model/Service Sarpras + routes/sarana-prasarana.php ikut
 * dihapus. Modul ini memang sudah tidak reachable sejak 2026-09-04.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Alur persetujuan Sarpras (kalau ada) + snapshot/histori terkait.
        $flowIds = DB::table('m_approval_flows')->where('module', 'sarpras_request')->pluck('id');
        if ($flowIds->isNotEmpty()) {
            DB::table('m_approval_flow_steps')->whereIn('flow_id', $flowIds)->delete();
            DB::table('m_approval_flow_assignments')->whereIn('flow_id', $flowIds)->delete();
            DB::table('m_approval_flows')->whereIn('id', $flowIds)->delete();
        }
        DB::table('approval_snapshots')->where('module_type', 'sarpras_request')->delete();
        DB::table('approval_histories')->where('requestable_type', 'sarpras_request')->delete();

        // Permission + pivot role/user.
        $permIds = DB::table('permissions')
            ->whereIn('name', ['sistem_sarana_prasarana', 'ajukan_sarana_prasarana'])
            ->pluck('id');
        if ($permIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }

        app('cache')->forget(config('permission.cache.key'));

        // Tabel data Sarpras.
        Schema::dropIfExists('sarpras_request_signatures');
        Schema::dropIfExists('sarpras_request_attachments');
        Schema::dropIfExists('sarpras_request_items');
        Schema::dropIfExists('sarpras_requests');
    }

    public function down(): void
    {
        // Penghapusan modul searah — migrasi create_sarpras_* lama tidak di-restore.
    }
};
