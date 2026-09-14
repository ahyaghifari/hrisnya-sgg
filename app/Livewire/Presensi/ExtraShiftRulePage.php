<?php

namespace App\Livewire\Presensi;

use App\Enum\ShiftType;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftRule;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Aturan transisi extra shift mana yang boleh (mis. Pagi->Siang boleh, Malam->Pagi tidak
 * boleh - baris tidak ada = tidak boleh) — cuma kelola PASANGAN di sini (tambah/hapus), TIDAK
 * bisa isi/ubah nominal (`amount`) — itu diisi belakangan di Payroll (MappingKomponenGajiPage
 * tab Extra Shift), dua permukaan edit satu tabel, sesuai kesepakatan desain 2026-08-17.
 */
#[Layout('components.layout', ['title' => 'Aturan Extra Shift'])]
class ExtraShiftRulePage extends Component
{
    /** Kategori `from` khusus: roster shift kosong / off_day - yang lagi libur boleh diajukan
     * extra shift (lihat ResolvesExtraShiftOptions::resolveNormalShift). Bukan case ShiftType
     * karena bukan kategori shift beneran, cuma dipakai sebagai basis aturan `from_shift_type`. */
    public const LIBUR = 'libur';

    public ?string $companyId = null;

    public string $fromShiftType = '';

    public string $toShiftType = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', ExtraShiftRule::class), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->companyId = $companyId;
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    /** Include-list, BUKAN exclude-list (instruksi user 2026-09-14) - cuma 3 kategori shift
     * UTAMA (Pagi/Siang/Malam) yang boleh terlibat Extra Shift sebagai `from` maupun `to`, biar
     * kategori baru yang mungkin ditambah nanti otomatis TIDAK ikut Extra Shift kecuali sengaja
     * ditambahkan ke sini. Oncall (assignable roster biasa sejak 2026-09-14) dan Middle (enum
     * dihapus, shift_type=null sejak migration clear_middle_shift_type_value) makanya otomatis
     * tidak pernah muncul di sini. `from=libur` (karyawan lagi libur) TETAP bisa - itu konstanta
     * terpisah (self::LIBUR), tidak lewat properti ini sama sekali, lihat addRule(). */
    public function getShiftTypeOptionsProperty(): array
    {
        return [ShiftType::PAGI, ShiftType::SIANG, ShiftType::MALAM];
    }

    public function getRulesProperty()
    {
        return ExtraShiftRule::where('company_id', $this->companyId)
            ->orderBy('from_shift_type')
            ->orderBy('to_shift_type')
            ->get();
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('create', ExtraShiftRule::class);
    }

    public function getCanDeleteProperty(): bool
    {
        return Auth::user()->can('Delete:ExtraShiftRule');
    }

    public function addRule(): void
    {
        abort_unless(Auth::user()->can('create', ExtraShiftRule::class), 403);

        $allowedValues = array_column($this->shiftTypeOptions, 'value');
        $fromValues = array_merge($allowedValues, [self::LIBUR]);

        $this->validate([
            'fromShiftType' => ['required', 'in:' . implode(',', $fromValues)],
            'toShiftType' => ['required', 'in:' . implode(',', $allowedValues), 'different:fromShiftType'],
        ], [], [
            'fromShiftType' => 'dari shift',
            'toShiftType' => 'ke shift',
        ]);

        $exists = ExtraShiftRule::where('company_id', $this->companyId)
            ->where('from_shift_type', $this->fromShiftType)
            ->where('to_shift_type', $this->toShiftType)
            ->exists();

        if ($exists) {
            $this->addError('toShiftType', 'Pasangan transisi ini sudah ada.');

            return;
        }

        ExtraShiftRule::create([
            'company_id' => $this->companyId,
            'from_shift_type' => $this->fromShiftType,
            'to_shift_type' => $this->toShiftType,
        ]);

        $this->reset(['fromShiftType', 'toShiftType']);

        Notification::make()->title('Pasangan transisi berhasil ditambahkan')->success()->send();
    }

    public function removeRule(string $id): void
    {
        $rule = ExtraShiftRule::where('company_id', $this->companyId)->findOrFail($id);

        abort_unless(Auth::user()->can('delete', $rule), 403);

        $rule->delete();

        Notification::make()->title('Pasangan transisi dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.presensi.extra-shift-rule-page');
    }
}
