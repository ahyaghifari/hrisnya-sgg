<?php

namespace App\Models\Sp;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Master pelanggaran (instruksi user 2026-10-06) - dipilih sebagai Dasar Klarifikasi Berita Acara
 * dan poin Surat Peringatan. company_id NULL = bawaan untuk semua perusahaan.
 */
class SpReason extends Model
{
    use HasUlids;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Aktif dan berlaku untuk perusahaan ini (milik sendiri + bawaan). */
    public function scopeAvailableFor($query, ?string $companyId)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }

    /**
     * Satu poin (dasar klarifikasi / poin SP) -> kolom siap simpan. Teks final disimpan sebagai
     * snapshot di `text` (dokumen tertandatangani tidak ikut berubah kalau master diganti nama).
     * Input string = poin lama tanpa master (teks apa adanya).
     *
     * @param  string|array{reason_id?:?string, repeat_sp_id?:?string, note?:?string}  $item
     * @return array{text:string, reason_id:?string, note:?string, repeat_sp_id:?string}
     */
    public static function composeItem(string|array $item): array
    {
        if (is_string($item)) {
            return ['text' => $item, 'reason_id' => null, 'note' => $item, 'repeat_sp_id' => null];
        }

        $note = trim((string) ($item['note'] ?? '')) ?: null;
        $reasonId = $item['reason_id'] ?? null;
        $repeatId = $item['repeat_sp_id'] ?? null;
        $text = e(static::find($reasonId)?->name ?? '');

        if ($repeatId && $previous = EmployeeSp::find($repeatId)) {
            $text .= ' (pelanggaran berulang, sebelumnya ' . $previous->sp_level->getLabel() . ')';
        }

        if ($note) {
            $text .= ($text !== '' ? ' — ' : '') . e($note);
        }

        return ['text' => $text, 'reason_id' => $reasonId, 'note' => $note, 'repeat_sp_id' => $repeatId];
    }

    /**
     * Nilai pilihan di form: "<reason_id>" biasa, atau "repeat:<sp_id>:<reason_id>" untuk pelanggaran
     * berulang dari SP sebelumnya.
     *
     * @return array{reason_id:?string, repeat_sp_id:?string}
     */
    public static function parseChoice(?string $choice): array
    {
        if ($choice && str_starts_with($choice, 'repeat:')) {
            [, $spId, $reasonId] = explode(':', $choice, 3) + [null, null, null];

            return ['reason_id' => $reasonId, 'repeat_sp_id' => $spId];
        }

        return ['reason_id' => $choice ?: null, 'repeat_sp_id' => null];
    }
}
