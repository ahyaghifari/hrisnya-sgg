<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cabut users.name, users.nip, users.company_id (roadmap_migrasi_bertahap_identity_hris.md).
 * Sumber kini employees (nama_lengkap, nip, company_id); employees.nip dijadikan UNIQUE menggantikan
 * users.nip. WAJIB dijalankan BERSAMAAN dengan IDENTITY_LEGACY_USER_COLUMNS=false (config/identity.php).
 *
 * Pengaman: up() MENOLAK berjalan kalau ada user tanpa baris employees atau nilainya berbeda dari
 * kolom users (jalankan `identity:backfill` lalu `identity:verify` sampai bersih). down() memulihkan
 * kolom dari employees, jadi pencabutan ini bisa dibatalkan tanpa kehilangan data.
 */
return new class extends Migration
{
    public function up(): void
    {
        $missing = DB::table('users')->leftJoin('employees', 'employees.user_id', '=', 'users.id')->whereNull('employees.id')->count();

        if ($missing > 0) {
            throw new RuntimeException("{$missing} user belum punya baris employees. Jalankan `php artisan identity:backfill` dulu.");
        }

        $diff = DB::table('users')->join('employees', 'employees.user_id', '=', 'users.id')
            ->where(function ($q) {
                $q->whereRaw('users.nip <> employees.nip')
                    ->orWhereRaw('COALESCE(users.name, \'\') <> COALESCE(employees.nama_lengkap, \'\')')
                    ->orWhereRaw('COALESCE(users.company_id, \'\') <> COALESCE(employees.company_id, \'\')');
            })->count();

        if ($diff > 0) {
            throw new RuntimeException("{$diff} user nilainya berbeda dari employees (name/nip/company_id). Jalankan `identity:backfill` lalu `identity:verify` sampai bersih.");
        }

        // NIP unik kini dijaga employees (menggantikan users_nip_unique).
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['nip']);
            $table->unique('nip');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nip']);
            $table->dropForeign(['company_id']);
            $table->dropColumn(['name', 'nip', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('nip')->nullable()->after('name');
            $table->foreignUlid('company_id')->nullable()->after('nip')->constrained('m_companies')->nullOnDelete();
        });

        DB::table('employees')->orderBy('id')->each(function ($e) {
            DB::table('users')->where('id', $e->user_id)->update([
                'name' => $e->nama_lengkap,
                'nip' => $e->nip,
                'company_id' => $e->company_id,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('nip');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['nip']);
            $table->index('nip');
        });
    }
};
