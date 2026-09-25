<?php

namespace App\Filament\Resources\ApprovalFlows\Schemas;

use App\Helpers\PermissionHelper;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\Role;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ApprovalFlowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Manajemen Alur Persetujuan')
                    ->description('Atur alur persetujuan untuk berbagai proses bisnis di sistem Anda.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        // Super Admin (holding, PermissionHelper::hasGlobalRole) bisa pilih mau
                        // bikin alur buat perusahaan mana - dulu selalu Hidden ke company_id
                        // Auth::user() sendiri, jadi Super Admin TIDAK PERNAH bisa bikin alur buat
                        // company lain sama sekali (kekurangan yang dilaporkan user 2026-08-16).
                        // Admin biasa (company-scoped) tetap Hidden ke company sendiri, tidak
                        // pernah bisa pilih company lain (sengaja, mereka memang cuma admin 1
                        // company).
                        Select::make('company_id')
                            ->label('Perusahaan')
                            ->options(fn () => Companies::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->default(fn () => Auth::user()->company_id)
                            ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),

                        Hidden::make('company_id')
                            ->default(fn () => Auth::user()->company_id)
                            ->visible(fn () => ! PermissionHelper::hasGlobalRole(Auth::user())),

                        TextInput::make('name')
                            ->label('Nama Alur Persetujuan')
                            ->required()
                            ->maxLength(255),

                        Select::make('module')
                            ->label('Modul')
                            ->helperText('Pilih modul bisnis yang akan menggunakan alur persetujuan ini.')
                            ->options(function (callable $get) {
                                $options = \App\Enum\Approval\ApprovalModuleEnum::options();

                                // Task punya alur review sendiri (reviewer_id/needs_review di
                                // TaskService, bukan lewat Approval Engine ini) - modulnya
                                // dihapus dari pilihan alur BARU, cuma tetap tampil kalau alur
                                // yang lagi diedit kebetulan sudah terlanjur pakai modul ini
                                // (jangan sampai dropdown-nya nge-blank pas dibuka).
                                if ($get('module') !== \App\Enum\Approval\ApprovalModuleEnum::TASK_REQUEST->value) {
                                    unset($options[\App\Enum\Approval\ApprovalModuleEnum::TASK_REQUEST->value]);
                                }

                                return $options;
                            })
                            ->rule(function ($get, $record) {
                                return function ($attribute, $value, $fail) use ($get, $record) {
                                    $flowType = $get('flow_type');

                                    // Ember jenis pengajuan yang dipilih alur ini ([] = "semua jenis").
                                    // Bentrok kalau ada alur lain (modul + flow_type sama) yang mengklaim
                                    // salah satu ember yang sama — atau dua-duanya "semua jenis".
                                    $selected = collect($get('sub_module') ?? [])->filter()->values()->all();
                                    $hasOverlap = function ($others) use ($selected) {
                                        return $others->contains(function ($f) use ($selected) {
                                            $theirs = filled($f->sub_module) ? explode(',', $f->sub_module) : [];
                                            if ($selected === [] || $theirs === []) {
                                                return $selected === [] && $theirs === [];
                                            }

                                            return array_intersect($selected, $theirs) !== [];
                                        });
                                    };

                                    if ($flowType === 'default') {
                                        $others = MasterApprovalFlows::query()
                                            ->where('module', $value)
                                            ->where('company_id', $get('company_id'))
                                            ->where('flow_type', 'default')
                                            ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                            ->get(['id', 'sub_module']);

                                        if ($hasOverlap($others)) {
                                            $fail('Alur Persetujuan Default untuk Modul & jenis pengajuan ini sudah ada. Pisahkan jenis pengajuannya, edit alur yang ada, atau gunakan tipe Spesifik.');
                                        }
                                    }

                                    if ($flowType === 'specific') {
                                        // Cek alur spesifik dengan jabatan yang sama pada modul ini
                                        $assignedIds = $get('assignment_job_positions') ?? [];

                                        if (!empty($assignedIds)) {
                                            $others = MasterApprovalFlows::query()
                                                ->where('module', $value)
                                                ->where('company_id', $get('company_id'))
                                                ->where('flow_type', 'specific')
                                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                                ->whereHas('assignments', function ($q) use ($assignedIds) {
                                                    $q->where('assignable_type', JobPositions::class)
                                                      ->whereIn('assignable_id', $assignedIds);
                                                })
                                                ->get(['id', 'sub_module']);

                                            if ($hasOverlap($others)) {
                                                $fail('Alur Persetujuan Spesifik untuk Jabatan & jenis pengajuan ini sudah ada. Silakan edit data yang ada, pilih jabatan lain, atau pisahkan jenis pengajuannya.');
                                            }
                                        }
                                    }
                                };
                            })
                            ->live()
                            ->reactive()
                            ->required(),

                        Select::make('sub_module')
                            ->label('Jenis Pengajuan')
                            ->helperText('Khusus modul Izin/Cuti/Lembur — batasi alur ini ke satu atau beberapa jenis pengajuan. Kosongkan = berlaku untuk semua jenis yang tidak punya alur khusus.')
                            ->multiple()
                            ->options(\App\Enum\Approval\AttendanceApprovalBucketEnum::options())
                            ->visible(fn (callable $get) => $get('module') === \App\Enum\Approval\ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
                            ->formatStateUsing(fn ($state) => filled($state) ? explode(',', $state) : [])
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? implode(',', (array) $state) : null)
                            ->columnSpanFull()
                            ->live(),

                        Select::make('flow_type')
                            ->label('Tipe Alur Persetujuan')
                            ->helperText(Html::make('
                                <ul class="ml-4 list-disc">
                                    <li><strong>Default</strong>: Digunakan sebagai alur persetujuan utama dan berlaku untuk semua pengajuan pada modul ini, kecuali terdapat flow spesifik yang sesuai dengan kondisi tertentu.</li>
                                    </br>
                                    <li><strong>Spesifik</strong>: Digunakan hanya untuk jabatan tertentu dari pengaju. Jika pengaju memiliki jabatan yang ditentukan pada flow ini, maka flow ini akan diprioritaskan dibandingkan Default.</li>
                                </ul>
                            '))
                            ->options([
                                'default' => 'Default',
                                'specific' => 'Spesifik',
                            ])
                            ->default('default')
                            ->live()
                            ->columnSpanFull()
                            ->reactive()
                            ->required(),

                        Hidden::make('updated_at')
                            ->dehydrated()
                            ->default(fn () => now()),

                        Select::make('assignment_job_positions')
                            ->label('Berlaku untuk Jabatan')
                            ->helperText('Alur ini akan otomatis diprioritaskan ketika pengaju memiliki salah satu jabatan berikut.')
                            ->multiple()
                            ->options(function () {
                                $companyId = Auth::user()->company_id;
                                return JobPositions::where('company_id', $companyId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->preload()
                            ->searchable()
                            ->columnSpanFull()
                            ->visible(fn (callable $get) => $get('flow_type') === 'specific')
                            ->required(fn (callable $get) => $get('flow_type') === 'specific'),
                    ]),
                ...self::StepSchema(),
            ]);
    }

    private static function StepSchema(): array
    {
        return [
            Section::make('Step Alur Persetujuan')
                ->headerActions([
                    Action::make('add_step')
                        ->label('Tambah Langkah Persetujuan')
                        ->slideOver()
                        ->modalWidth('md')
                        // Modal slide-over ini form TERPISAH (state sendiri, $get di dalamnya
                        // tidak bisa menjangkau company_id di form utama) - company_id dioper
                        // manual lewat fillForm supaya dropdown Approver/Departemen di dalam
                        // modal ini ikut ke-scope ke company yang lagi diedit di form utama,
                        // bukan selalu company Auth::user() sendiri (kekurangan yang sama
                        // dilaporkan user 2026-08-16).
                        ->fillForm(fn ($livewire) => [
                            'company_id' => $livewire->data['company_id'] ?? Auth::user()->company_id,
                            'flow_type' => $livewire->data['flow_type'] ?? null,
                        ])
                        ->schema([
                            ...self::inputStepSchema(),
                        ])
                        ->action(function (array $data, $livewire) {
                            // Validasi data sebelum menyimpan. Step 'supervisor' (atasan langsung
                            // pengaju) tidak punya approver_ref_id — di-resolve saat snapshot.
                            $isSupervisor = ($data['approver_type'] ?? null) === 'supervisor';
                            if (empty($data['approver_type']) || (empty($data['approver_ref_id']) && ! $isSupervisor)) {
                                Notification::make()
                                    ->title('Error')
                                    ->body('Approver Type dan Approver harus diisi!')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $currentSteps = $livewire->data['steps'] ?? [];
                            $maxOrder = 0;

                            if (!empty($currentSteps)) {
                                $maxOrder = max(array_column($currentSteps, 'step_order'));
                            }

                            $data['step_order'] = $maxOrder + 1;

                            // Pastikan scope_ref_id di-set dengan benar
                            if ($data['scope_type'] !== 'specific_department') {
                                $data['scope_ref_id'] = null;
                            }

                            $steps = $livewire->data['steps'] ?? [];
                            $steps[] = $data;
                            $livewire->data['steps'] = $steps;

                            // Refresh repeater
                            $livewire->mountAction('');
                            $livewire->dispatch('close-modal', id: 'add_step');
                        })
                ])
                ->description('Definisikan langkah-langkah dalam alur persetujuan ini. Urutan langkah langsung ke step pertama akan menjadi approver pertama')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('steps')
                        ->hiddenLabel()
                        ->addable(false)
                        ->orderColumn('step_order')
                        ->reorderable(true)
                        ->reorderableWithDragAndDrop(true)
                        ->defaultItems(0)
                        ->deletable(false)
                        ->itemLabel(function (array $state): ?string {
                            if (!isset($state['approver_type'])) {
                                return 'Step Baru';
                            }

                            if ($state['approver_type'] !== 'supervisor' && !isset($state['approver_ref_id'])) {
                                return 'Step Baru';
                            }

                            // Lookup langsung by ID (ULID unik global, tidak butuh scope
                            // company_id buat display) - dulu di-scope ke Auth::user()->
                            // company_id, jadi salah/kosong kalau alur ini punya company LAIN
                            // dari admin yang lagi login (kekurangan yang sama, 2026-08-16).
                            $approverName = match ($state['approver_type']) {
                                'role' => Role::find($state['approver_ref_id'])?->name ?? 'Role tidak ditemukan',
                                'user' => \App\Models\User::find($state['approver_ref_id'])?->name ?? 'User tidak ditemukan',
                                'job_position' => JobPositions::find($state['approver_ref_id'])?->name ?? 'Jabatan tidak ditemukan',
                                'supervisor' => 'Atasan Pengaju (Level ' . ((int) ($state['supervisor_level'] ?? 1)) . ')',
                                default => '',
                            };

                            return "Step {$state['step_order']}: {$approverName}";
                        })
                        ->schema([
                            Hidden::make('step_order'),
                            Hidden::make('approver_type'),
                            Hidden::make('approver_ref_id'),
                            Hidden::make('supervisor_level'),
                            Hidden::make('scope_type'),
                            Hidden::make('scope_ref_id'),
                            Hidden::make('is_final'),
                            Hidden::make('approval_mode'),

                            Grid::make(4)
                                ->schema([
                                    TextEntry::make('approver_type')
                                        ->label('Sumber')
                                        ->formatStateUsing(function ($state) {
                                            return match ($state) {
                                                'role' => 'Role',
                                                'user' => 'Karyawan Tertentu',
                                                'job_position' => 'Jabatan Tertentu',
                                                'supervisor' => 'Atasan Pengaju',
                                                default => $state,
                                            };
                                        }),

                                    TextEntry::make('approver_ref_id')
                                        ->label('Approver')
                                        ->formatStateUsing(function ($state, $record, callable $get) {
                                            $approverType = $get('approver_type') ?? $record?->approver_type ?? null;

                                            if ($approverType === 'supervisor') {
                                                $lvl = (int) ($get('supervisor_level') ?? $record?->supervisor_level ?? 1);

                                                return "Atasan pengaju, naik {$lvl} level (otomatis saat pengajuan)";
                                            }

                                            if (!$state) return '-';

                                            // Lookup langsung by ID, sama alasan dengan itemLabel
                                            // di atas - jangan scope ke Auth::user()->company_id.
                                            return match ($approverType) {
                                                'role' => Role::find($state)?->name ?? 'Role tidak ditemukan',
                                                'user' => \App\Models\User::find($state)?->name ?? 'User tidak ditemukan',
                                                'job_position' => JobPositions::find($state)?->name ?? 'Jabatan tidak ditemukan',
                                                default => '-',
                                            };
                                        }),

                                    TextEntry::make('scope_type')
                                        ->label('Lingkup')
                                        ->formatStateUsing(function ($state) {
                                            return match ($state) {
                                                'same_department' => 'Satu Departemen',
                                                'specific_department' => 'Departemen Tertentu',
                                                default => $state,
                                            };
                                        }),

                                    TextEntry::make('is_final')
                                        ->label('Final')
                                        ->badge()
                                        ->formatStateUsing(function ($state) {
                                            return $state ? 'Ya' : 'Tidak';
                                        })
                                        ->color(fn ($state) => $state ? 'success' : 'gray'),
                                ])
                        ])
                        ->extraItemActions([
                            Action::make('edit_step')
                                ->label('Edit Langkah')
                                ->icon(Heroicon::PencilSquare)
                                ->color('primary')
                                ->slideOver()
                                ->modalWidth('md')
                                ->schema([
                                    ...self::inputStepSchema(),
                                ])
                                ->modalHeading('Edit Langkah Persetujuan')
                                ->modalDescription('Setelah submit, perubahan akan langsung disimpan ke database.')
                                ->fillForm(function ($arguments, $component): array {
                                    $items = $component->getState() ?? [];
                                    $itemKey = $arguments['item'] ?? null;

                                    // company_id dioper juga (sama alasan dengan add_step) -
                                    // modal edit ini form terpisah juga, tanpa ini dropdown
                                    // Approver/Departemen balik hardcode ke company Auth::user().
                                    $companyId = $component->getLivewire()->data['company_id'] ?? Auth::user()->company_id;
                                    $flowType = $component->getLivewire()->data['flow_type'] ?? null;

                                    if ($itemKey === null || !isset($items[$itemKey])) {
                                        return ['company_id' => $companyId, 'flow_type' => $flowType];
                                    }

                                    return array_merge($items[$itemKey], ['company_id' => $companyId, 'flow_type' => $flowType]);
                                })
                                ->action(function (array $data, $arguments, $component) {
                                    try {
                                        $items = $component->getState() ?? [];
                                        if (!is_array($items)) {
                                            return;
                                        }

                                        $itemKey = $arguments['item'] ?? null;
                                        if ($itemKey === null || !isset($items[$itemKey])) {
                                            return;
                                        }

                                        $step     = $items[$itemKey];
                                        $stepOrder = $step['step_order'] ?? null;

                                        if ($stepOrder === null) {
                                            return;
                                        }

                                        if (($data['scope_type'] ?? null) !== 'specific_department') {
                                            $data['scope_ref_id'] = null;
                                        }

                                        // Pertahankan step_order yang sudah ada
                                        $data['step_order'] = $stepOrder;

                                        // Update ke database jika record sudah ada
                                        $stepModel = MasterApprovalFlowSteps::where('flow_id', $component->getLivewire()->data['id'] ?? null)
                                            ->where('step_order', $stepOrder)
                                            ->first();

                                        if ($stepModel) {
                                            // Step 'supervisor' tidak punya approver_ref_id (field disembunyikan).
                                            $isSupervisor = ($data['approver_type'] ?? null) === 'supervisor';
                                            $stepModel->update([
                                                'approver_type'  => $data['approver_type'],
                                                'approver_ref_id' => $isSupervisor ? null : ($data['approver_ref_id'] ?? null),
                                                'supervisor_level' => $isSupervisor ? (int) ($data['supervisor_level'] ?? 1) : null,
                                                'scope_type'     => $data['scope_type'] ?? 'same_department',
                                                'scope_ref_id'   => $data['scope_ref_id'] ?? null,
                                                'is_final'       => $data['is_final'] ?? false,
                                                'approval_mode'  => $data['approval_mode'] ?? 'any',
                                            ]);
                                        }

                                        $items[$itemKey] = array_merge($items[$itemKey], $data);
                                        $component->state(array_values($items));

                                        Notification::make()
                                            ->title('Sukses')
                                            ->body('Langkah persetujuan berhasil diperbarui.')
                                            ->success()
                                            ->send();
                                    } catch (\Throwable $e) {
                                        Notification::make()
                                            ->title('Error')
                                            ->body('Gagal mengupdate langkah: ' . $e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),

                            Action::make('delete_step')
                                ->label('Hapus Langkah')
                                ->icon(Heroicon::Trash)
                                ->color('danger')
                                ->requiresConfirmation()
                                ->modalHeading('Konfirmasi Hapus')
                                ->modalDescription('Apakah Anda yakin ingin menghapus langkah ini? Tindakan ini tidak dapat dibatalkan dan data akan langsung terhapus.')
                                ->action(function ($arguments, $component) {
                                    try {
                                        $items = $component->getState() ?? [];
                                        if (!is_array($items)) {
                                            return;
                                        }

                                        $itemKey = $arguments['item'] ?? null;
                                        if ($itemKey === null || !isset($items[$itemKey])) {
                                            return;
                                        }

                                        $step = $items[$itemKey];
                                        $stepOrder = $step['step_order'] ?? null;
                                        if ($stepOrder === null) {
                                            return;
                                        }

                                        $stepModel = MasterApprovalFlowSteps::where('flow_id', $component->getLivewire()->data['id'] ?? null)
                                            ->where('step_order', $stepOrder)
                                            ->first();

                                        if ($stepModel) {
                                            $stepModel->delete();

                                            unset($items[$itemKey]);
                                            $items = array_values($items);
                                            $component->state($items);
                                        }

                                        Notification::make()
                                            ->title('Sukses')
                                            ->body('Langkah persetujuan berhasil dihapus.')
                                            ->success()
                                            ->send();
                                    } catch (\Throwable $e) {
                                        Notification::make()
                                            ->title('Error')
                                            ->body('Gagal menghapus langkah: ' . $e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),
                        ])
                        ->collapsed(false)
                ]),
        ];
    }

    private static function inputStepSchema(): array
    {
        return [
            // Dioper dari fillForm() add_step/edit_step (lihat StepSchema()) - company_id
            // ASLI dari form utama (bukan Auth::user()->company_id), field terpisah di sini
            // karena modal add_step/edit_step form-nya sendiri, $get tidak bisa lewat batas
            // form ke form utama (2026-08-16).
            Hidden::make('company_id'),
            Hidden::make('flow_type'),

            Select::make('approver_type')
                ->label('Sumber')
                ->options([
                    'role' => 'Role',
                    'job_position' => 'Jabatan',
                    'user' => 'Karyawan Spesiifik',
                    // Atasan pengaju = naik N level dari jabatan pengaju, di-resolve saat pengajuan
                    // (App\Repositories\Approval\ApprovalSnapshotRepository::resolveSupervisorPositionId())
                    // — berlaku di alur Default maupun Spesifik, resolusinya generik per pengaju.
                    'supervisor' => 'Atasan Pengaju',
                ])
                ->live()
                ->required()
                ->afterStateUpdated(function (callable $set) {
                    $set('approver_ref_id', null);
                }),

            Select::make('supervisor_level')
                ->label('Level Atasan')
                ->helperText('1 = atasan langsung, 2 = atasan-nya atasan, dst. Dihitung dari jabatan pengaju.')
                ->options(array_combine(range(1, 10), range(1, 10)))
                ->default(1)
                ->native(false)
                ->visible(fn (callable $get) => $get('approver_type') === 'supervisor')
                ->required(fn (callable $get) => $get('approver_type') === 'supervisor'),

            Select::make('source_company')
                ->label('Perusahaan')
                ->helperText('Data Perusahaan hanya digunakan untuk filter data approver yang ditampilkan.')
                ->options([
                    'same_company' => 'Perusahaan yang Sama',
                    'holding_company' => 'Perusahaan Induk / Syifa Global Group',
                ])
                ->live()
                ->default('same_company')
                ->visible(fn (callable $get) => $get('approver_type') !== 'supervisor'),

            Select::make('approver_ref_id')
                ->label('Approver')
                ->visible(fn (callable $get) => $get('approver_type') !== 'supervisor')
                ->options(function (callable $get) {
                    $approverType = $get('approver_type');
                    $sourceCompany = $get('source_company') ?? 'same_company';
                    if (!$approverType) return [];

                    // company_id ASLI dari form utama (dioper via fillForm - lihat Hidden di
                    // atas), bukan Auth::user()->company_id - dulu selalu company admin yang
                    // login, jadi Super Admin bikin alur buat company lain tetap lihat daftar
                    // approver company-nya sendiri, bukan company yang lagi diedit.
                    $companyId = $get('company_id') ?? Auth::user()->company_id;

                    if ($sourceCompany === 'holding_company') {
                        $parentCompanyId = '01J2F0C8KQ9M7D4S8RBX6E1ZVP';
                        if (!$parentCompanyId) return [];
                        $companyId = $parentCompanyId;
                    }

                    return match ($approverType) {
                        'role' => Role::where('company_id', $companyId)
                            ->pluck('name', 'id'),
                        'user' => \App\Models\User::where('company_id', $companyId)
                            ->pluck('name', 'id'),
                        'job_position' => JobPositions::where('company_id', $companyId)
                            ->pluck('name', 'id'),
                        default => [],
                    };
                })
                ->preload()
                ->searchable()
                ->required(fn (callable $get) => $get('approver_type') !== 'supervisor')
                ->disabled(fn (callable $get) => !$get('approver_type')),

            Select::make('scope_type')
                ->label('Lingkup')
                ->helperText('Tentukan lingkup dari approver (khusus untuk approver dengan sumber role), apakah berlaku di satu departemen dengan data pengaju atau approver role di departemen tertentu')
                ->options([
                    'same_department' => 'Satu Departemen',
                    'specific_department' => 'Departemen Tertentu',
                ])
                ->live()
                ->required()
                ->afterStateUpdated(function (callable $set) {
                    $set('scope_ref_id', null);
                }),

            Select::make('scope_ref_id')
                ->label('Departemen')
                ->options(function (callable $get) {
                    $scopeType = $get('scope_type');
                    if ($scopeType !== 'specific_department') return [];

                    $companyId = $get('company_id') ?? Auth::user()->company_id;
                    return OrgStructure::where('company_id', $companyId)
                        ->pluck('name', 'id');
                })
                ->visible(fn (callable $get) => $get('scope_type') === 'specific_department')
                ->required(fn (callable $get) => $get('scope_type') === 'specific_department')
                ->nullable(),

            Toggle::make('is_final')
                ->label('Langkah Terakhir')
                ->default(false),

            Hidden::make('approval_mode')
                ->default(function (callable $get) {
                    return $get('approver_type') === 'job_position' ? 'all' : 'any';
                }),

            Hidden::make('step_order'),
        ];
    }
}
