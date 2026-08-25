<?php

namespace App\Models\Sarpras;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SarprasRequest extends Model
{
    use HasUlids;

    protected $table = 'sarpras_requests';

    protected $fillable = [
        'user_id',
        'kode',
        'unit',
        'jenis',
        'prioritas',
        'perihal',
        'uraian',
        'status',
        'catatan_kabid',
        'kabid_id',
        'kabid_at',
        'catatan_direktur',
        'direktur_id',
        'direktur_at',
        'selesai_at',
    ];

    protected function casts(): array
    {
        return [
            'kabid_at' => 'datetime',
            'direktur_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kabid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kabid_id');
    }

    public function direktur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direktur_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SarprasRequestItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SarprasRequestAttachment::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(SarprasRequestSignature::class);
    }

    public function approvalSnapshot(): HasOne
    {
        return $this->hasOne(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::SARPRAS_REQUEST->value);
    }

    public function approvalHistories(): HasMany
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id');
    }

    /**
     * Hitung status kepala dokumen dari status seluruh item — porting segarkan_status() dari
     * another-app/sarpras (includes/functions.php). Pure function, tidak menulis DB; caller
     * (service layer, belum dibangun) yang tentukan kapan dipanggil & disimpan.
     *
     * Selama masih ada item `menunggu`, dokumen tetap jadi PR Direktur (menunggu_direktur kalau
     * belum ada yang diputuskan sama sekali, diputuskan_sebagian kalau sudah ada tapi belum
     * semua). Begitu semua item final, status akhir tergantung campuran disetujui/ditolak.
     */
    public function computeStatus(): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $menunggu = $items->where('status', 'menunggu')->count();
        $disetujui = $items->where('status', 'disetujui')->count();
        $ditolak = $items->where('status', 'ditolak')->count();

        if ($menunggu > 0) {
            return ($disetujui + $ditolak) > 0 ? 'diputuskan_sebagian' : 'menunggu_direktur';
        }

        if ($disetujui > 0 && $ditolak > 0) {
            return 'disetujui_sebagian';
        }

        if ($disetujui > 0) {
            return 'disetujui';
        }

        return 'ditolak';
    }
}
