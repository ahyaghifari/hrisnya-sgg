<?php

namespace App\Filament\Resources\ApprovalFlows\Schemas;

use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
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
                        Hidden::make('company_id')
                            ->default(fn () => Auth::user()->company_id),

                        TextInput::make('name')
                            ->label('Nama Alur Persetujuan')
                            ->required()
                            ->maxLength(255),

                        Select::make('module')
                            ->label('Modul')
                            ->helperText('Pilih modul bisnis yang akan menggunakan alur persetujuan ini.')
                            ->options([
                                'attendance_request' => 'Permintaan Izin, Cuti, Lembur',
                                'task_request' => 'Permintaan Penugasan Individu',
                                'jobposition_request' => 'Penambahan/Perubahan Jabatan',
                                'approval_candidate_applicant' => 'Persetujuan Pelamar Kerja',
                                'user_transition' => 'Perubahan Status Karyawan',
                                'swap_shift_request' => 'Permintaan Tukar Shift',
                                'additional_employee_request' => 'Permintaan Penambahan Karyawan',
                                'employee_replacement_request' => 'Permintaan Penggantian Karyawan',
                            ])
                            ->rule(function ($get, $record) {
                                return function ($attribute, $value, $fail) use ($get, $record) {
                                    $flowType = $get('flow_type');

                                    if ($flowType === 'default') {
                                        // Hanya boleh 1 alur Default per modul per perusahaan
                                        $exists = MasterApprovalFlows::query()
                                            ->where('module', $value)
                                            ->where('company_id', $get('company_id'))
                                            ->where('flow_type', 'default')
                                            ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                            ->exists();

                                        if ($exists) {
                                            $fail('Alur Persetujuan Default untuk Modul ini sudah ada. Silakan edit data yang ada atau gunakan tipe Spesifik.');
                                        }
                                    }

                                    if ($flowType === 'specific') {
                                        // Cek alur spesifik dengan jabatan yang sama pada modul ini
                                        $assignedIds = $get('assignment_job_positions') ?? [];

                                        if (!empty($assignedIds)) {
                                            $exists = MasterApprovalFlows::query()
                                                ->where('module', $value)
                                                ->where('company_id', $get('company_id'))
                                                ->where('flow_type', 'specific')
                                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                                ->whereHas('assignments', function ($q) use ($assignedIds) {
                                                    $q->where('assignable_type', JobPositions::class)
                                                      ->whereIn('assignable_id', $assignedIds);
                                                })
                                                ->exists();

                                            if ($exists) {
                                                $fail('Alur Persetujuan Spesifik untuk Jabatan yang dipilih pada Modul ini sudah ada. Silakan edit data yang ada atau buat dengan jabatan lain.');
                                            }
                                        }
                                    }
                                };
                            })
                            ->live()
                            ->reactive()
                            ->required(),

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
                        ->schema([
                            ...self::inputStepSchema(),  
                        ])
                        ->action(function (array $data, $livewire) {
                            // Validasi data sebelum menyimpan
                            if (empty($data['approver_ref_id']) || empty($data['approver_type'])) {
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
                            if (!isset($state['approver_type']) || !isset($state['approver_ref_id'])) {
                                return 'Step Baru';
                            }

                            $companyId = Auth::user()->company_id;
                            $approverName = '';

                            switch ($state['approver_type']) {
                                case 'role':
                                    $approverName = Role::where('company_id', $companyId)
                                        ->where('id', $state['approver_ref_id'])->value('name') ?? 'Role tidak ditemukan';
                                    break;
                                case 'user':
                                    $approverName = \App\Models\User::where('company_id', $companyId)
                                        ->where('id', $state['approver_ref_id'])->value('name') ?? 'User tidak ditemukan';
                                    break;
                                case 'job_position':
                                    $approverName = JobPositions::where('company_id', $companyId)
                                        ->where('id', $state['approver_ref_id'])->value('name') ?? 'Jabatan tidak ditemukan';
                                    break;
                            }

                            return "Step {$state['step_order']}: {$approverName}";
                        })
                        ->schema([
                            Hidden::make('step_order'),
                            Hidden::make('approver_type'),
                            Hidden::make('approver_ref_id'),
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
                                                default => $state,
                                            };
                                        }),

                                    TextEntry::make('approver_ref_id')
                                        ->label('Approver')
                                        ->formatStateUsing(function ($state, $record, callable $get) {
                                            if (!$state) return '-';

                                            $companyId = Auth::user()->company_id;
                                            $approverType = $get('approver_type') ?? $record?->approver_type ?? null;

                                            return match ($approverType) {
                                                'role' => Role::where('company_id', $companyId)
                                                    ->where('id', $state)->value('name') ?? 'Role tidak ditemukan',
                                                'user' => \App\Models\User::where('company_id', $companyId)
                                                    ->where('id', $state)->value('name') ?? 'User tidak ditemukan',
                                                'job_position' => JobPositions::where('company_id', $companyId)
                                                    ->where('id', $state)->value('name') ?? 'Jabatan tidak ditemukan',
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

                                    if ($itemKey === null || !isset($items[$itemKey])) {
                                        return [];
                                    }

                                    return $items[$itemKey];
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
                                            $stepModel->update([
                                                'approver_type'  => $data['approver_type'],
                                                'approver_ref_id' => $data['approver_ref_id'],
                                                'scope_type'     => $data['scope_type'],
                                                'scope_ref_id'   => $data['scope_ref_id'],
                                                'is_final'       => $data['is_final'],
                                                'approval_mode'  => $data['approval_mode'],
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
            Select::make('approver_type')
                ->label('Sumber')
                ->options([
                    'role' => 'Role',
                    'job_position' => 'Jabatan',
                    'user' => 'Karyawan Spesiifik',
                ])
                ->live()
                ->required()
                ->afterStateUpdated(function (callable $set) {
                    $set('approver_ref_id', null);
                }),

            Select::make('source_company')
                ->label('Perusahaan')
                ->helperText('Data Perusahaan hanya digunakan untuk filter data approver yang ditampilkan.')
                ->options([
                    'same_company' => 'Perusahaan yang Sama',
                    'holding_company' => 'Perusahaan Induk / Syifa Global Group',
                ])
                ->live()
                ->default('same_company'),

            Select::make('approver_ref_id')
                ->label('Approver')
                ->options(function (callable $get) {
                    $approverType = $get('approver_type');
                    $sourceCompany = $get('source_company') ?? 'same_company';
                    if (!$approverType) return [];

                    $companyId = Auth::user()->company_id;

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
                ->required()
                ->disabled(fn (callable $get) => !$get('approver_type'))
                ->rules(['required']),

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

                    $companyId = Auth::user()->company_id;
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
