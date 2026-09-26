<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Varian multi-field dari LogsAuditChanges — dipakai model yang punya BANYAK kolom yang
 * masing-masing perlu jejak sendiri (User, UserProfiles: 1 baris audit_logs per field yang
 * berubah), bukan cuma 1 nilai (LogsAuditChanges lama tetap dipakai model config seperti
 * Setting/PositionAllowance). Ke-trigger di updated() Eloquent, APA PUN jalur tulisnya
 * (self-service Livewire Data Diri, Filament EmployeeForm admin, Livewire Kepegawaian
 * Create/EditKaryawan) — karena hook-nya di level model, bukan di level controller/halaman,
 * jadi otomatis menjawab "siapa saja yang mengubah" (AuditLog::record() pakai Auth::id() si
 * aktor, bukan pemilik data) baik yang mengubah itu karyawan sendiri maupun admin/HC.
 *
 * Sengaja TIDAK mencatat created()/deleted() — provisioning awal/hapus akun bukan "histori
 * perubahan data" yang dimaksud di sini.
 */
trait LogsFieldAuditChanges
{
    protected static function bootLogsFieldAuditChanges(): void
    {
        static::updated(function ($model) {
            foreach ($model->auditFieldLabels() as $attribute => $label) {
                if (! $model->wasChanged($attribute)) {
                    continue;
                }

                $old = $model->getOriginal($attribute);
                $new = $model->{$attribute};

                if ((string) $old === (string) $new) {
                    continue;
                }

                AuditLog::record(
                    $model->auditModule(),
                    $model->auditCompanyId(),
                    $model->auditRecordKey() . ':' . $attribute,
                    $label,
                    $old === null ? null : (string) $old,
                    $new === null ? null : (string) $new,
                );
            }
        });
    }

    abstract protected function auditModule(): string;

    abstract protected function auditCompanyId(): ?string;

    /** ID entitas yang di-audit (biasanya user_id) — dipakai sebagai prefix `key` supaya
     * riwayat 1 orang gampang di-query: AuditLog::where('key', 'like', "{$userId}:%"). */
    abstract protected function auditRecordKey(): string;

    /** @return array<string,string> attribute => label Indonesia buat ditampilkan */
    abstract protected function auditFieldLabels(): array;
}
