<?php

namespace App\Livewire\LMS;

use App\Enum\QuestionType;
use App\Models\LMS\LmsCategories;
use App\Services\LMS\QuestionBankService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Tambah Bank Soal" — multi-soal dalam satu form (repeater). Berbeda dari Filament
 * QuestionBanksResource yang satu-satu per record. Di sini user bisa ketik banyak soal sekaligus
 * (plus opsi masing-masing) dan tekan satu tombol Simpan Semua.
 *
 * Pola repeater array ikut MappingKomponenGajiPage (tenureRows): property `array $soalRows`,
 * addSoalRow() push default, removeSoalRow(int $i) guard + unset + array_values (reindex wajib
 * supaya wire:key Livewire stabil). Opsi per-soal pakai sub-array `options` dengan add/remove
 * sendiri (addOption/removeOption menerima index row).
 *
 * Kategori: shared 1 kategori untuk semua soal di batch ini (`$categoryId` di atas form) —
 * keputusan simpel dari rencana awal. Kalau perlu per-soal, ubah jadi field per-row nanti.
 *
 * Otorisasi: Create:LmsQuestionBanks (Policy Shield).
 * Tipe soal per-row pakai wire:model.live supaya show/hide opsi vs skor otomatis (pola Filament live()).
 */
#[Layout('components.layout', ['title' => 'Tambah Bank Soal'])]
class CreateBankSoal extends Component
{
    public ?string $categoryId = null;

    /** @var array<int, array{question:string,type:string,min_score:string,max_score:string,options:array<int,array{text:string,is_correct:bool}>}> */
    public array $soalRows = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can('Create:LmsQuestionBanks'), 403);

        // Mulai dengan 1 row kosong biar form tidak benar-benar kosong saat pertama buka
        $this->soalRows = [$this->emptyRow()];
    }

    public function getCategoryOptionsProperty()
    {
        return LmsCategories::orderBy('name')->pluck('name', 'id');
    }

    protected function emptyRow(): array
    {
        return [
            'question' => '',
            'type' => QuestionType::MultipleChoice->value,
            'min_score' => '',
            'max_score' => '',
            'options' => [
                ['text' => '', 'is_correct' => false],
                ['text' => '', 'is_correct' => false],
            ],
        ];
    }

    public function addSoalRow(): void
    {
        $this->soalRows[] = $this->emptyRow();
    }

    public function removeSoalRow(int $index): void
    {
        if (count($this->soalRows) <= 1) {
            return;
        }
        unset($this->soalRows[$index]);
        $this->soalRows = array_values($this->soalRows);
    }

    public function addOption(int $rowIndex): void
    {
        if (! isset($this->soalRows[$rowIndex])) {
            return;
        }
        if (count($this->soalRows[$rowIndex]['options']) >= 10) {
            return;
        }
        $this->soalRows[$rowIndex]['options'][] = ['text' => '', 'is_correct' => false];
    }

    public function removeOption(int $rowIndex, int $optionIndex): void
    {
        if (! isset($this->soalRows[$rowIndex]['options'][$optionIndex])) {
            return;
        }
        if (count($this->soalRows[$rowIndex]['options']) <= 2) {
            return;
        }
        unset($this->soalRows[$rowIndex]['options'][$optionIndex]);
        $this->soalRows[$rowIndex]['options'] = array_values($this->soalRows[$rowIndex]['options']);
    }

    /**
     * Tandai satu opsi sebagai jawaban benar DAN uncheck opsi lain di row yang sama — radio
     * behavior untuk multiple_choice (satu jawaban benar per soal). Input di blade sudah
     * type="radio" (native, di-group per row via `name`) supaya browser sendiri yang menjamin
     * cuma 1 opsi ter-check, tidak lagi bergantung pada morph Livewire.
     */
    public function setCorrectOption(int $rowIndex, int $optionIndex): void
    {
        if (! isset($this->soalRows[$rowIndex]['options'][$optionIndex])) {
            return;
        }
        foreach ($this->soalRows[$rowIndex]['options'] as $i => $opt) {
            $this->soalRows[$rowIndex]['options'][$i]['is_correct'] = false;
        }
        $this->soalRows[$rowIndex]['options'][$optionIndex]['is_correct'] = true;
    }

    /**
     * Switch tipe soal per-row: reset field yang tidak relevan.
     * Dipanggil otomatis oleh wire:model.live="soalRows.{i}.type" — Livewire hook updated().
     */
    public function updatedSoalRows($value, string $key): void
    {
        // Format key: "0.type", "1.options.2.text", dst. Cuma tangani perubahan type.
        if (! str_ends_with($key, '.type')) {
            return;
        }
        $parts = explode('.', $key);
        $rowIndex = (int) $parts[0];
        if (! isset($this->soalRows[$rowIndex])) {
            return;
        }
        $newType = $value;
        if ($newType === QuestionType::MultipleChoice->value) {
            $this->soalRows[$rowIndex]['min_score'] = '';
            $this->soalRows[$rowIndex]['max_score'] = '';
            // Pastikan minimal 2 opsi
            if (count($this->soalRows[$rowIndex]['options']) < 2) {
                while (count($this->soalRows[$rowIndex]['options']) < 2) {
                    $this->soalRows[$rowIndex]['options'][] = ['text' => '', 'is_correct' => false];
                }
            }
        } else {
            // Essay: kosongkan opsi (tidak dipakai) — tapi tetap simpan array kosong biar binding
            // Livewire tidak error kalau user toggle balik ke MC.
            $this->soalRows[$rowIndex]['options'] = [];
        }
    }

    protected function rules(): array
    {
        return [
            'categoryId' => 'required|exists:lms_categories,id',
            'soalRows' => ['required', 'array', 'min:1'],
            'soalRows.*.question' => 'required|string',
            'soalRows.*.type' => 'required|in:' . implode(',', array_column(QuestionType::cases(), 'value')),
            'soalRows.*.min_score' => 'nullable|integer|min:0|max:100',
            'soalRows.*.max_score' => 'nullable|integer|min:0|max:100',
            'soalRows.*.options' => ['array'],
            'soalRows.*.options.*.text' => 'required|string',
            'soalRows.*.options.*.is_correct' => 'boolean',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'categoryId' => 'kategori',
            'soalRows' => 'daftar soal',
            'soalRows.*.question' => 'pertanyaan',
            'soalRows.*.type' => 'tipe soal',
            'soalRows.*.min_score' => 'skor minimal',
            'soalRows.*.max_score' => 'skor maksimal',
            'soalRows.*.options' => 'opsi jawaban',
            'soalRows.*.options.*.text' => 'teks opsi',
        ];
    }

    public function save(QuestionBankService $service): void
    {
        abort_unless(Auth::user()->can('Create:LmsQuestionBanks'), 403);

        $validated = $this->validate();

        // Validasi tambahan: essay wajib max_score; multiple_choice wajib minimal 1 opsi benar.
        // Pakai addError (bukan rule) karena cross-field dan kondisional per-tipe.
        $hasError = false;
        foreach ($validated['soalRows'] as $i => $row) {
            if ($row['type'] === QuestionType::Essay->value) {
                if (empty($row['max_score']) || (int) $row['max_score'] < 1) {
                    $this->addError("soalRows.{$i}.max_score", 'Skor maksimal wajib diisi untuk soal esai (minimal 1).');
                    $hasError = true;
                }
            } else {
                // multiple_choice
                $correctCount = collect($row['options'])->filter(fn ($o) => ! empty($o['is_correct']))->count();
                if ($correctCount < 1) {
                    $this->addError("soalRows.{$i}.options", 'Minimal 1 opsi harus ditandai sebagai jawaban benar.');
                    $hasError = true;
                }
            }
        }

        if ($hasError) {
            return;
        }

        // categoryId shared di atas form (1 kategori untuk semua soal di batch ini) — inject ke
        // tiap row sebelum pass ke service, supaya QuestionBankService::createOne bisa baca
        // $item['category_id'] tanpa peduli dari mana asalnya.
        $questions = array_map(
            fn ($row) => array_merge($row, ['category_id' => $validated['categoryId'] ?? null]),
            $validated['soalRows']
        );

        $service->createMany($questions);

        Notification::make()
            ->title(count($validated['soalRows']) . ' soal berhasil ditambahkan ke Bank Soal')
            ->success()
            ->send();

        $this->redirect(route('pelatihan.bank-soal'), navigate: true);
    }

    public function render()
    {
        return view('livewire.lms.create-bank-soal');
    }
}
