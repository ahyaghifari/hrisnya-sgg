<?php

namespace App\Filament\Resources\ShiftTemplates\Pages;

use App\Filament\Resources\ShiftTemplates\ShiftTemplateResource;
use App\Models\Shift\ShiftScheduleAssignments;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateScheduleFromTemplate extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ShiftTemplateResource::class;

    protected string $view = 'filament.shift-templates.generate-schedule';

    public ?array $data = [];

    public function mount(string|int $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->form->fill([
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(max(0, $this->record->cycle_length - 1))->toDateString(),
            'exclude_weekends'  => false,
            'override_existing' => true,
        ]);
    }

    public function getTitle(): string
    {
        return 'Generate Jadwal: ' . $this->record->name;
    }

    public function getBreadcrumbs(): array
    {
        return [
            ShiftTemplateResource::getUrl('index') => 'Template Shift',
            '#' => 'Generate Jadwal',
        ];
    }

    public function form(Schema $schema): Schema
    {
        $record = $this->record;

        return $schema
            ->statePath('data')
            ->components([
                // Info banner template
                Section::make("Template: {$record->name}")
                    ->description("Siklus {$record->cycle_length} hari Â· " . ($record->description ?? 'Tidak ada deskripsi'))
                    ->icon('heroicon-o-rectangle-stack')
                    ->columnSpanFull()
                    ->collapsed(false)
                    ->schema([]),

                Section::make('Pilih Karyawan')
                    ->description('Pilih karyawan yang akan mendapat jadwal shift berdasarkan template ini. Gunakan pencarian atau centang semua untuk memilih lebih cepat.')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('user_ids')
                            ->label(false)
                            ->options(function () {
                                return User::where('company_id', Auth::user()->company_id)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn ($u) => [$u->id => $u->name])
                                    ->toArray();
                            })
                            ->descriptions(function () {
                                return User::where('company_id', Auth::user()->company_id)
                                    ->with('profile.orgStructure')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn ($u) => [
                                        $u->id => $u->profile?->orgStructure?->name ?? 'Tanpa Divisi',
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->gridDirection('row')
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Rentang Tanggal')
                    ->description('Template akan berulang (loop) sesuai panjang siklus hingga tanggal akhir.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->native(false)
                            ->displayFormat('d F Y'),

                        DatePicker::make('end_date')
                            ->label('Tanggal Akhir')
                            ->required()
                            ->native(false)
                            ->displayFormat('d F Y'),
                    ]),

                Section::make('Pengaturan')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('exclude_weekends')
                            ->label('Lewati Sabtu & Minggu')
                            ->helperText('Hari Sabtu & Minggu dilewati, tidak mendapat jadwal apapun.')
                            ->inline(false),

                        Toggle::make('override_existing')
                            ->label('Timpa Jadwal yang Ada')
                            ->helperText('Aktif: hari kerja di-upsert, hari libur di template â†’ jadwal yang sudah ada DIHAPUS. Nonaktif: hanya mengisi yang belum ada jadwal.')
                            ->inline(false),
                    ]),
            ]);
    }

    public function generate(): void
    {
        $data = $this->form->getState();

        $template    = $this->record;
        $cycleLength = $template->cycle_length;

        // Load template days keyed by day_number (1-based)
        $days = $template->days()
            ->orderBy('day_number')
            ->get()
            ->keyBy('day_number');

        $startDate = Carbon::parse($data['start_date']);
        $endDate   = Carbon::parse($data['end_date']);
        $userIds   = (array) ($data['user_ids'] ?? []);
        $companyId = Auth::user()->company_id;
        $createdBy = Auth::id();
        $now       = now();

        $workRows = [];  // [key => row] to upsert/insert
        $offKeys  = [];  // [[userId, dateStr]] to delete when override

        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            if (!empty($data['exclude_weekends']) && $current->isWeekend()) {
                $current->addDay();
                continue;
            }

            $diffDays    = (int) $startDate->diffInDays($current);
            $dayNumber   = ($diffDays % $cycleLength) + 1;
            $templateDay = $days->get($dayNumber);
            $dateStr     = $current->format('Y-m-d');

            foreach ($userIds as $userId) {
                $key = "{$userId}|{$dateStr}";

                if ($templateDay && $templateDay->is_off_day) {
                    // Off day in template â†’ mark for deletion (only if override is active)
                    $offKeys[] = [$userId, $dateStr];
                } elseif ($templateDay && !$templateDay->is_off_day && $templateDay->shift_id) {
                    // Work day â†’ upsert/insert
                    $workRows[$key] = [
                        'id'         => (string) Str::ulid(),
                        'company_id' => $companyId,
                        'user_id'    => $userId,
                        'shift_id'   => $templateDay->shift_id,
                        'date'       => $dateStr,
                        'notes'      => null,
                        'created_by' => $createdBy,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            $current->addDay();
        }

        $workRows = array_values($workRows);

        if (empty($workRows) && empty($offKeys)) {
            Notification::make()
                ->warning()
                ->title('Tidak ada perubahan')
                ->body('Tidak ada hari kerja valid dalam rentang tanggal yang dipilih.')
                ->persistent()
                ->send();

            return;
        }

        $deletedCount = 0;
        $savedCount   = count($workRows);

        DB::transaction(function () use ($workRows, $offKeys, $data, &$deletedCount): void {
            // Delete off-day assignments when override is active
            if (!empty($offKeys) && $data['override_existing']) {
                foreach ($offKeys as [$userId, $dateStr]) {
                    $deletedCount += ShiftScheduleAssignments::where('user_id', $userId)
                        ->where('date', $dateStr)
                        ->delete();
                }
            }

            if (!empty($workRows)) {
                if ($data['override_existing']) {
                    ShiftScheduleAssignments::upsert(
                        $workRows,
                        ['user_id', 'date'],
                        ['shift_id', 'notes', 'updated_at']
                    );
                } else {
                    ShiftScheduleAssignments::insertOrIgnore($workRows);
                }
            }
        });

        $parts = [];
        if ($savedCount > 0) {
            $parts[] = "{$savedCount} entri jadwal disimpan";
        }
        if ($deletedCount > 0) {
            $parts[] = "{$deletedCount} jadwal hari libur dihapus";
        }

        Notification::make()
            ->success()
            ->title('Generate berhasil!')
            ->body(implode(', ', $parts) . ' untuk ' . count($userIds) . ' karyawan.')
            ->send();

        $this->redirect(ShiftTemplateResource::getUrl('index'));
    }
}
