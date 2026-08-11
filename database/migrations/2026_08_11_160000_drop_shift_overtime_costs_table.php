<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Hapus total tabel shift_overtime_costs & Model/Policy/permission-nya (2026-08-11) - mekanisme
 * lembur berbasis Shift ini sudah tidak dipakai lagi, digantikan LemburCalculator (base rate per
 * Pangkat, lihat komentarnya). Model-nya bahkan sudah tidak sinkron sama skema tabel asli
 * (fillable maximum_overtime_hours/overtime_rate/company_id vs kolom asli day_type/hour/
 * rate_multiplier tanpa company_id) - tidak ada UI portal/admin yang pernah mengelolanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shift_overtime_costs');

        $orphanIds = Permission::query()
            ->where('name', 'like', '%:ShiftOvertimeCosts')
            ->pluck('id');

        if ($orphanIds->isNotEmpty()) {
            DB::table('model_has_permissions')->whereIn('permission_id', $orphanIds)->delete();
            Permission::query()->whereIn('id', $orphanIds)->delete();

            app('cache')
                ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
                ->forget(config('permission.cache.key'));
        }
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - fitur ini dihapus permanen, bukan rollback sementara.
        Schema::create('shift_overtime_costs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('shift_id')->constrained('m_shifts')->cascadeOnDelete();
            $table->foreignUlid('overtime_type_id')->nullable()->constrained('m_overtime_types')->nullOnDelete();
            $table->enum('day_type', ['weekday', 'weekend'])->default('weekday');
            $table->integer('hour');
            $table->decimal('rate_multiplier', 5, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
