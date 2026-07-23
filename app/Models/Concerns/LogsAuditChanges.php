<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Nempel di model config (Setting, Komponen Gaji, Mapping Komponen Gaji dkk) — otomatis catat
 * `created`/`updated`/`deleted` ke audit_logs, apapun jalur tulisnya (Livewire manual maupun
 * Filament Action), karena hook-nya di level Eloquent bukan di level method halaman.
 */
trait LogsAuditChanges
{
    protected static function bootLogsAuditChanges(): void
    {
        static::created(fn ($model) => $model->recordAuditLog(null, $model->auditValue()));

        static::updated(function ($model) {
            if ($model->wasChanged($model->auditWatchedAttribute())) {
                $model->recordAuditLog($model->getOriginal($model->auditWatchedAttribute()), $model->auditValue());
            }
        });

        static::deleted(fn ($model) => $model->recordAuditLog($model->auditValue(), null));
    }

    protected function recordAuditLog(mixed $old, mixed $new): void
    {
        AuditLog::record(
            $this->auditModule(),
            $this->auditCompanyId(),
            $this->auditKey(),
            $this->auditLabel(),
            $old === null ? null : (string) $old,
            $new === null ? null : (string) $new,
        );
    }

    protected function auditValue(): mixed
    {
        return $this->{$this->auditWatchedAttribute()};
    }

    abstract protected function auditModule(): string;

    abstract protected function auditCompanyId(): ?string;

    abstract protected function auditKey(): string;

    abstract protected function auditLabel(): ?string;

    abstract protected function auditWatchedAttribute(): string;
}
