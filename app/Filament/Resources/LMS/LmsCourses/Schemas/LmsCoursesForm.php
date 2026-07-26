<?php

namespace App\Filament\Resources\LMS\LmsCourses\Schemas;

use App\Enum\CourseUserStatus;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsQuizzes;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\Trainer\Trainers;
use App\Models\User;
use App\Services\LMS\QrCodeService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

use function Symfony\Component\Translation\t;

class LmsCoursesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Tabs')
                ->columnSpanFull()
                ->tabs([
                    self::detailSchema(),
                    self::jadwalSchema(),
                    self::pertemuanSchema(),
                    self::participantsSchema(),
                    self::feedbacksSchema(),
                ])
        ]);
    }

    private static function detailSchema(): Tab
    {
        return Tab::make('Detail Pelatihan')
            ->schema([
                Section::make('Informasi Umum Pelatihan')
                    ->description('Form untuk mengelola informasi umum Pelatihan LMS.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Hidden::make('company_id')
                                ->default(fn () => Auth::user()->company_id)
                                ->dehydrated(),
                                
                            Select::make('category_id')
                                ->label('Kategori')
                                ->relationship('category', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    Section::make('Form Kategori')
                                        ->schema([
                                            TextInput::make('name')
                                                ->label('Nama Kategori')
                                                ->required()
                                                ->maxLength(100),
                                        ])
                                ])
                                ->createOptionModalHeading('Tambah Kategori Baru'),

                            TextInput::make('code')
                                ->label('Kode Pelatihan')
                                ->required()
                                ->maxLength(100),
                        ]),

                        TextInput::make('title')
                            ->label('Judul Pelatihan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            Select::make('type_nakes')
                                ->label('Tipe Peserta')
                                ->options([
                                    'nakes' => 'Nakes',
                                    'non-nakes' => 'Non-Nakes',
                                    'all' => 'Nakes & Non-Nakes',
                                ])
                                ->default('all')
                                ->required(),

                            Select::make('source_type')
                                ->label('Sumber Pelatihan')
                                ->options([
                                    'internal' => 'Internal',
                                    'external' => 'External',
                                ])
                                ->default('internal')
                                ->required(),

                            TextInput::make('minimum_score')
                                ->label('Skor Minimum Kelulusan')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->helperText('Masukkan skor minimum yang harus dicapai peserta untuk lulus pelatihan.'),

                            DatePicker::make('due_date')
                                ->label('Batas Waktu Penyelesaian')
                                ->helperText('Opsional. Peserta yang lewat tanggal ini belum selesai akan ditandai "Terlambat" — tidak diblokir, cuma penanda.'),

                            Select::make('competencies')
                                ->label('Kompetensi Terkait')
                                ->multiple()
                                ->relationship('competencies', 'name')
                                ->saveRelationshipsUsing(function ($record, $state) {
                                    $record->competencies()->sync($state);
                                })
                                ->searchable()
                                ->required()
                                ->preload(),
                        ]),

                        FileUpload::make('thumbnail')
                            ->label('Thumbnail Pelatihan (JPG, PNG)')
                            ->image()
                            ->directory('lms/thumbnails')
                            ->disk('public')
                            ->maxSize(10240)
                            ->columnSpanFull()
                            ->deleteUploadedFileUsing(function ($file) {
                                if ($file && Storage::disk('public')->exists($file)) {
                                    Storage::disk('public')->delete($file);
                                }
                            }),

                        Hidden::make('status'),
                    ])
            ]);
    }

    private static function jadwalSchema(): Tab
    {
        return Tab::make('Jadwal Pelatihan')
            ->schema([
                Section::make('Jadwal Pelatihan')
                    ->description('Atur jadwal pelaksanaan pelatihan LMS.')
                    ->columnSpanFull()
                    ->schema([
                        // Tipe "Terjadwal" (scheduler) dihapus 2026-07-14 — tidak pernah punya job
                        // consumer, diganti fitur Duplikat Course. Course selalu seasonal sekarang;
                        // course lama yang kadung type=scheduler dibiarkan (tidak dipaksa migrasi),
                        // field ini cukup jadi hidden default supaya tidak menimpa data lama itu.
                        Hidden::make('type')->default('seasonal'),

                        Grid::make(2)->schema([
                            DatePicker::make('start_date')
                                ->label('Tanggal Mulai Pelatihan')
                                ->disabled()
                                ->helperText('Tanggal mulai pelatihan diatur berdasarkan jadwal pertemuan.'),

                            DatePicker::make('end_date')
                                ->label('Tanggal Selesai Pelatihan')
                                ->disabled()
                                ->helperText('Tanggal selesai pelatihan diatur berdasarkan jadwal pertemuan.'),
                        ])
                    ])
            ]);
    }

    private static function pertemuanSchema(): Tab
    {
        return Tab::make('Pertemuan')
            ->schema([
                Repeater::make('meetings')
                    ->relationship('meetings')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->itemLabel(fn ($state): ?string => is_array($state) ? ($state['title'] ?? 'Pelatihan / Sesi') : 'Pelatihan / Sesi')
                    ->addActionLabel('Tambah Pertemuan Baru')
                    ->schema([
                        Tabs::make('Tabs')->tabs([
                            Tab::make('Detail Pertemuan')->schema([
                                TextInput::make('title')
                                    ->label('Judul Pertemuan / Sesi')
                                    ->required(),
                                Grid::make(2)->schema([
                                    DatePicker::make('start_date')
                                        ->locale('id')
                                        ->label('Tanggal Mulai')
                                        ->required(),
                                    DatePicker::make('end_date')
                                        ->locale('id')
                                        ->label('Tanggal Selesai')
                                        ->required(),
                                    TimePicker::make('start_time')
                                        ->locale('id')
                                        ->label('Waktu Mulai')
                                        ->required(),
                                    TimePicker::make('end_time')
                                        ->locale('id')
                                        ->label('Waktu Selesai')
                                        ->required(),

                                    Select::make('attendance_type')
                                        ->label('Tipe Absensi')
                                        ->options([
                                            'online' => 'Online',
                                            'offline' => 'Offline',
                                        ])
                                        ->default('online')
                                        ->required(),
                                ]),

                                Select::make('trainer_id')
                                    ->label('Pilih Pemateri')
                                    ->relationship('trainer', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Pilih pemateri dari daftar')
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set(
                                            'type_trainer',
                                            $state
                                                ? Trainers::query()->whereKey($state)->value('type')
                                                : null
                                        );
                                    }),

                                Hidden::make('type_trainer')
                                    ->dehydrated(true),

                                RichEditor::make('description')
                                    ->label('Deskripsi / Materi Pertemuan')
                                    ->columnSpanFull()
                                    ->dehydrateStateUsing(function ($state) {
                                        return is_string($state) ? $state : '';
                                    })
                                    ->afterStateHydrated(function (RichEditor $component, $state) {
                                        if (is_array($state)) {
                                            $component->state('');
                                        } elseif (is_string($state)) {
                                            $component->state($state);
                                        } else {
                                            $component->state('');
                                        }
                                    }),
                            ]),

                            Tab::make('Materi Pertemuan')->schema([
                                Repeater::make('materials')
                                    ->label('Materi Pertemuan')
                                    ->relationship('materials')
                                    ->hiddenLabel()
                                    ->itemLabel(fn ($state): ?string => is_array($state) ? ($state['title'] ?? 'Materi') : 'Materi')
                                    ->addActionLabel('Tambah Materi Baru')
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Judul Materi')
                                            ->required(),
                                        FileUpload::make('file_path')
                                            ->label('File Materi (PDF, DOCX, PPTX)')
                                            ->required()
                                            ->maxSize(20480)    // 20 MB
                                            ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'])
                                            ->openable(true)
                                            ->directory('lms/materials')
                                            ->disk('public')
                                            ->deleteUploadedFileUsing(function ($file) {
                                                if ($file && Storage::disk('public')->exists($file)) {
                                                    Storage::disk('public')->delete($file);
                                                }
                                            }),
                                    ])
                            ]),

                            Tab::make('Quiz')->schema([
                                Repeater::make('quizzes')
                                    ->relationship('quizzes')
                                    ->hiddenLabel()
                                    ->itemLabel(fn ($state): ?string => is_array($state) ? ($state['title'] ?? 'Kuis') : 'Kuis')
                                    ->addActionLabel('Tambah Quiz Baru')
                                    ->schema([
                                        Select::make('category_id')
                                            ->label('Filter Kategori')
                                            ->options(function () {
                                                return LmsCategories::orderBy('name')->pluck('name', 'id')->toArray();
                                            })
                                            // Default ke kategori course-nya sendiri (paling sering sama),
                                            // tapi tetap bisa diganti manual kalau memang mau pakai quiz
                                            // dari kategori lain (quiz bank memang didesain reusable).
                                            ->default(fn ($livewire) => data_get($livewire, 'data.category_id'))
                                            ->searchable()
                                            ->preload()
                                            ->columnSpanFull()
                                            ->placeholder('Pilih kategori')
                                            ->live()
                                            ->reactive(),

                                        Select::make('quiz_id')
                                            ->label('Pilih Kuis')
                                            ->options(function ($get, $record) {
                                                $categoryId = $get('category_id');
                                                $meetingId = $record ? $record->id : null;
                                                $usedQuizIds = $meetingId
                                                    ? LmsMeetingQuizzes::where('meeting_id', $meetingId)->pluck('quiz_id')->toArray()
                                                    : [];

                                                $query = LmsQuizzes::query();

                                                if ($categoryId) {
                                                    $query->where('category_id', $categoryId);
                                                }

                                                if (!empty($usedQuizIds)) {
                                                    $query->whereNotIn('id', $usedQuizIds);
                                                }

                                                return $query->orderBy('title')->pluck('title', 'id')->toArray();
                                            })
                                            ->searchable()
                                            ->columnSpanFull()
                                            ->required()
                                            ->reactive(),

                                        TextInput::make('duration')
                                            ->label('Durasi (menit)')
                                            ->numeric()
                                            ->required()
                                            ->minValue(1)
                                    ])
                            ]),
                        ]),
                    ])
                    ->extraItemActions([
                        Action::make('view_qr_code')
                            ->label('Lihat QR Code Absensi')
                            ->icon('heroicon-o-qr-code')
                            ->tooltip('Lihat QR Code untuk absensi peserta pada pertemuan ini.')
                            ->visible(function (array $arguments, array $state) {
                                $itemKey = $arguments['item'] ?? null;
                                $meeting = $state[$itemKey] ?? null;

                                if (!$meeting) {
                                    return false;
                                }

                                return $meeting['attendance_type'] === 'offline';
                            })
                            ->modalHeading(function (array $arguments, $record): string {
                                $itemKey = $arguments['item'] ?? null;
                                if (! $record || $itemKey === null) {
                                    return 'QR Code Absensi Pertemuan';
                                }

                                $meeting = $record->meetings()->find($itemKey);
                                if (! $meeting) {
                                    return 'QR Code Absensi Pertemuan';
                                }

                                return 'QR Code Absensi - ' . ($meeting->title ?? 'Pertemuan');
                            })
                            ->modalDescription('Scan QR Code ini untuk melakukan absensi.')
                            ->modalWidth('md')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Tutup')
                            ->modalContent(function (array $arguments, array $state) {
                                $itemKey = $arguments['item'] ?? null;
                                $meeting = $state[$itemKey] ?? null;

                                $qrUrl = app(QrCodeService::class)->getQrUrl($meeting['id'] ?? '');

                                return view('filament.infolists.components.qr-attendance-meeting', [
                                    'qrUrl' => $qrUrl,
                                    'meetingTitle' => $meeting['title'] ?? 'Pertemuan',
                                ]);
                            })
                    ])
            ]);
    }

    private static function participantsSchema(): Tab
    {
        return Tab::make('Peserta')
            ->schema([
                Section::make('Daftar Peserta')
                    ->headerActions([
                        Action::make('Tambah Peserta')
                            ->slideOver()
                            ->label('Tambah Peserta')
                            ->modalHeading('Tambah Peserta ke Pelatihan')
                            ->modalDescription('Pilih peserta yang akan ditambahkan ke pelatihan ini.')
                            ->modalWidth('md')
                            ->schema([
                                CheckboxList::make('users')
                                    ->label('Pilih Peserta')
                                    ->afterStateHydrated(function (CheckboxList $component, $state, callable $get) {
                                        // Always reload from database to ensure consistency
                                        $record = $component->getRecord();
                                        if (!$record) {
                                            return;
                                        }

                                        try {
                                            $existingUserIds = $record->courseUsers()
                                                ->pluck('user_id')
                                                ->map(fn ($id) => (string) $id)
                                                ->toArray();

                                            $component->state($existingUserIds);
                                        } catch (\Throwable $e) {
                                            $component->state([]);
                                        }
                                    })
                                    ->options(fn () => User::orderBy('name')->pluck('name', 'id')->toArray())
                                    ->columns(1)
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->action(function (array $data, $component, callable $set) {
                                try {
                                    $record = $component->getRecord();
                                    if (!$record) {
                                        return;
                                    }

                                    $users = $data['users'] ?? [];
                                    if (!is_array($users)) {
                                        $users = [];
                                    }

                                    $selectedUserIds = collect($users)
                                        ->filter()
                                        ->map(fn ($id) => (string) $id)
                                        ->unique()
                                        ->values()
                                        ->toArray();

                                    $existingUserIds = $record->courseUsers()
                                        ->pluck('user_id')
                                        ->map(fn ($id) => (string) $id)
                                        ->toArray();

                                    $newUserIds = array_diff($selectedUserIds, $existingUserIds);

                                    foreach ($newUserIds as $userId) {
                                        if (!empty($userId)) {
                                            $record->courseUsers()->create([
                                                'user_id' => $userId,
                                                'is_mandatory' => true,
                                                // Admin memilih peserta langsung di sini — itu sendiri sudah tindakan
                                                // persetujuan, beda dari self-enroll API yang masih butuh direview.
                                                'status' => CourseUserStatus::APPROVED->value,
                                                'score' => 0,
                                            ]);
                                        }
                                    }

                                    $courseUsersData = self::getCourseUsersData($record);
                                    $set('courseUsersData', $courseUsersData);

                                } catch (\Throwable $e) {
                                }
                            })
                            ->requiresConfirmation()
                    ])
                    ->schema([
                        Repeater::make('courseUsersData')
                            ->hiddenLabel()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Repeater $component, $state) {
                                try {
                                    $record = $component->getRecord();
                                    if ($record instanceof LmsCourses) {
                                        $courseUsersData = self::getCourseUsersData($record);
                                        $component->state($courseUsersData);
                                    } else {
                                        $component->state([]);
                                    }
                                } catch (\Throwable $e) {
                                    $component->state([]);
                                }
                            })
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->addActionLabel('Tambah Peserta Baru')
                            ->table([
                                TableColumn::make('Nama Peserta'),
                                TableColumn::make('Nilai'),
                                TableColumn::make('Status'),
                            ])
                            ->schema([
                                TextEntry::make('user.name')
                                    ->hiddenLabel()
                                    ->columnSpanFull(),

                                TextEntry::make('score')
                                    ->hiddenLabel()
                                    ->alignCenter()
                                    ->columnSpanFull(),

                                TextEntry::make('status')
                                    ->hiddenLabel()
                                    ->badge()
                                    ->columnSpanFull()
                                    ->alignCenter()
                                    ->formatStateUsing(function (?string $state) {
                                        return $state ? CourseUserStatus::from($state)->getLabel() : 'Unknown';
                                    })
                                    ->color(function (?string $state) {
                                        if (!$state) return 'gray';

                                        return match(CourseUserStatus::from($state)) {
                                            CourseUserStatus::REQUESTED => 'gray',
                                            CourseUserStatus::APPROVED => 'primary',
                                            CourseUserStatus::IN_PROGRESS => 'warning',
                                            CourseUserStatus::COMPLETED => 'success',
                                            CourseUserStatus::CANCELED => 'danger',
                                            default => 'gray',
                                        };
                                    })
                            ])
                            ->extraItemActions([
                                Action::make('Approve')
                                    ->icon('heroicon-o-check-circle')
                                    ->color('success')
                                    ->visible(function (array $arguments, array $state): bool {
                                        $itemKey = $arguments['item'] ?? null;
                                        $user = $state[$itemKey] ?? null;

                                        $lmsUser = LmsCourseUsers::find($user['id'] ?? null);
                                        if (!$lmsUser) {
                                            return false;
                                        }

                                        return $lmsUser->status !== CourseUserStatus::APPROVED->value;
                                    })
                                    ->action(function (array $arguments, $component) {
                                        try {
                                            $items = $component->getState() ?? [];
                                            if (!is_array($items)) {
                                                return;
                                            }

                                            $itemKey = $arguments['item'] ?? null;
                                            if ($itemKey === null || !isset($items[$itemKey])) {
                                                return;
                                            }

                                            $user = $items[$itemKey];
                                            if (!is_array($user) || !isset($user['id'])) {
                                                return;
                                            }

                                            $userModel = LmsCourseUsers::find($user['id']);
                                            if ($userModel) {
                                                $userModel->status = CourseUserStatus::APPROVED->value;
                                                $userModel->enrolled_at = now();
                                                $userModel->save();

                                                $items[$itemKey]['status'] = CourseUserStatus::APPROVED->value;
                                                $component->state($items);
                                            }
                                        } catch (\Throwable $e) {
                                        }
                                    })
                                    ->requiresConfirmation(true)
                                    ->modalHeading('Konfirmasi Persetujuan')
                                    ->modalDescription('Apakah Anda yakin ingin menyetujui peserta ini untuk bergabung dalam pelatihan?'),

                                Action::make('Cancel')
                                    ->icon('heroicon-o-x-circle')
                                    ->color('danger')
                                    ->tooltip('Batalkan Keikutsertaan Peserta')
                                    ->visible(function (array $arguments, array $state): bool {
                                        $itemKey = $arguments['item'] ?? null;
                                        $user = $state[$itemKey] ?? null;

                                        return ($user['status'] ?? null) !== CourseUserStatus::CANCELED->value;
                                    })
                                    ->action(function (array $arguments, $component) {
                                        try {
                                            $items = $component->getState() ?? [];
                                            if (!is_array($items)) {
                                                return;
                                            }

                                            $itemKey = $arguments['item'] ?? null;
                                            if ($itemKey === null || !isset($items[$itemKey])) {
                                                return;
                                            }

                                            $user = $items[$itemKey];
                                            if (!is_array($user) || !isset($user['id'])) {
                                                return;
                                            }

                                            $userModel = LmsCourseUsers::find($user['id']);
                                            if ($userModel) {
                                                $userModel->status = CourseUserStatus::CANCELED->value;
                                                $userModel->save();

                                                $items[$itemKey]['status'] = CourseUserStatus::CANCELED->value;
                                                $component->state($items);
                                            }
                                        } catch (\Throwable $e) {
                                        }
                                    }),

                                Action::make('delete_user')
                                    ->icon('heroicon-o-trash')
                                    ->color('danger')
                                    ->requiresConfirmation(true)
                                    ->modalHeading('Konfirmasi Penghapusan')
                                    ->modalDescription('Apakah Anda yakin ingin menghapus peserta ini dari pelatihan? Tindakan ini tidak dapat dibatalkan.')
                                    ->tooltip('Hapus Peserta dari Pelatihan')
                                    ->action(function (array $arguments, $component, callable $set) {
                                        try {
                                            $items = $component->getState() ?? [];
                                            if (!is_array($items)) {
                                                return;
                                            }

                                            $itemKey = $arguments['item'] ?? null;
                                            if ($itemKey === null || !isset($items[$itemKey])) {
                                                return;
                                            }

                                            $user = $items[$itemKey];
                                            if (!is_array($user) || !isset($user['id'])) {
                                                return;
                                            }

                                            $userModel = LmsCourseUsers::find($user['id']);
                                            if ($userModel) {
                                                $userModel->delete();

                                                unset($items[$itemKey]);
                                                $items = array_values($items);
                                                $component->state($items);
                                            }
                                        } catch (\Throwable $e) {
                                        }
                                    })
                            ])
                    ])
            ]);
    }

    protected static function getCourseUsersData(LmsCourses $course): array
    {
        return $course->courseUsers()
            ->with('user')
            ->get()
            ->map(static function (LmsCourseUsers $courseUser) {
                return [
                    'id' => $courseUser->id,
                    'user_id' => $courseUser->user_id,
                    'user' => [
                        'name' => $courseUser->user->name ?? '',
                    ],
                    'score' => $courseUser->score,
                    'status' => $courseUser->status,
                ];
            })
            ->toArray();
    }

    private static function feedbacksSchema(): Tab
    {
        return Tab::make('Umpan Balik')
            ->schema([
                Section::make()
                    ->hiddenLabel()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('average_rating')
                                    ->beforeContent(Icon::make(Heroicon::Star))
                                    ->label('Rata-rata Penilaian')
                                    ->disabled()
                                    ->columnSpan(1)
                                    ->weight('bold')
                                    ->visible(fn ($record) => $record ? $record->feedbacks() : false)
                                    ->default(fn ($record) => $record ? $record->feedbacks()?->avg('rating') : 0)
                                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) : '0.00'),
                            ])
                    ]),

                Repeater::make('feedbacks')
                    ->relationship('feedbacks')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->addable(false)
                    ->deletable(false)
                    ->table([
                        TableColumn::make('Nama Peserta'),
                        TableColumn::make('Rating'),
                        TableColumn::make('Komentar'),
                    ])
                    ->schema([
                        TextEntry::make('user.name')
                            ->disabled()
                            ->columnSpanFull(),

                        TextEntry::make('rating')
                            ->hiddenLabel()
                            ->alignCenter()
                            ->columnSpanFull(),

                        TextEntry::make('feedback')
                            ->hiddenLabel()
                            ->columnSpanFull(),
                    ])
            ]);
    }

}
