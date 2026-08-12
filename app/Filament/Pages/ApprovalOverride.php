<?php

namespace App\Filament\Pages;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalActivities;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Job\ManpowerRequests;
use App\Models\LMS\TrainingRequests;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserTransition;
use App\Services\Approval\ApprovalOverrideService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * "Approve Paksa" - Admin/Super Admin bisa langsung menyetujui pengajuan yang masih pending di
 * step approver manapun, TANPA harus jadi approver yang sah (instruksi user 2026-08-10: approver
 * asli/Direktur kadang lambat merespons, admin butuh jalan pintas). Sengaja dibatasi role literal
 * Admin/Super Admin (canAccess()), bukan permission granular yang bisa didelegasikan - ini
 * kekuatan bypass approval chain, bukan aksi rutin.
 *
 * Cuma menampilkan modul yang beneran didukung ApprovalOverrideService::supportedModules() -
 * lihat komentar di service itu kenapa Task/Swap Shift/Candidate Applicant tidak masuk.
 */
class ApprovalOverride extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.pages.approval-override.approval-override';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen Pengguna';

    protected static ?string $navigationLabel = 'Approve Persetujuan';

    protected static ?string $title = 'Approve Persetujuan';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['Admin', 'Super Admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ApprovalActivities::query()
                    ->where('status', 'pending')
                    ->whereHas('snapshot', fn ($q) => $q->whereIn('module_type', app(ApprovalOverrideService::class)->supportedModules()))
                    ->with('snapshot')
            )
            ->columns([
                TextColumn::make('snapshot.module_type')
                    ->label('Modul')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ApprovalModuleEnum::from($state)->label()),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->getStateUsing(fn (ApprovalActivities $record) => $this->describe($record))
                    ->wrap(),

                TextColumn::make('pemohon')
                    ->label('Pemohon')
                    ->getStateUsing(fn (ApprovalActivities $record) => User::find($record->snapshot->created_by)?->name ?? '-'),

                TextColumn::make('current_step_order')
                    ->label('Step Saat Ini')
                    ->formatStateUsing(fn (ApprovalActivities $record) => $record->current_step_order . ' / ' . count($record->snapshot->steps_snapshot ?? [])),

                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->recordActions([
                Action::make('force_approve')
                    ->label('Approve Persetujuan')
                    ->color('warning')
                    ->icon('heroicon-o-bolt')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Persetujuan')
                    ->modalDescription('Ini akan menyetujui step approval saat ini tanpa melalui approver yang sah. Kalau masih ada step berikutnya, pengajuan tetap butuh persetujuan lanjutan.')
                    ->schema([
                        Textarea::make('note')
                            ->label('Catatan (wajib)')
                            ->required()
                            ->rows(3)
                            ->placeholder('Alasan approve persetujuan, mis. approver asli tidak kunjung merespons'),
                    ])
                    ->action(function (ApprovalActivities $record, array $data) {
                        try {
                            app(ApprovalOverrideService::class)->approve(
                                moduleType: $record->snapshot->module_type,
                                moduleId: $record->snapshot->module_id,
                                actorId: Auth::id(),
                                note: $data['note'],
                            );

                            Notification::make()->title('Berhasil di-approve paksa')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal approve paksa: ' . $e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    private function describe(ApprovalActivities $record): string
    {
        $moduleType = $record->snapshot->module_type;
        $moduleId = $record->snapshot->module_id;

        return match ($moduleType) {
            ApprovalModuleEnum::ATTENDANCE_REQUEST->value => (function () use ($moduleId) {
                $r = AttendanceRequests::find($moduleId);
                return $r ? match ($r->type) {
                    'leave' => 'Cuti',
                    'permission' => 'Izin',
                    'overtime' => 'Lembur',
                    default => $r->type,
                } . ' - ' . ($r->user?->name ?? '-') : 'Data tidak ditemukan';
            })(),
            ApprovalModuleEnum::TRAINING_REQUEST->value => TrainingRequests::find($moduleId)?->title ?? 'Data tidak ditemukan',
            ApprovalModuleEnum::USER_TRANSITION->value => (function () use ($moduleId) {
                $t = UserTransition::find($moduleId);
                return $t ? $t->number . ' - ' . ($t->user?->name ?? '-') : 'Data tidak ditemukan';
            })(),
            ApprovalModuleEnum::JOBPOSITION_REQUEST->value => 'Pengajuan Jabatan - ' . (JobPositions::find($moduleId)?->name ?? 'Data tidak ditemukan'),
            ApprovalModuleEnum::ADDITIONAL_EMPLOYEE_REQUEST->value,
            ApprovalModuleEnum::EMPLOYEE_REPLACEMENT_REQUEST->value,
            ApprovalModuleEnum::ADDITIONAL_QUOTA_REQUEST->value => (function () use ($moduleId) {
                $m = ManpowerRequests::find($moduleId);
                return $m ? $m->quantity . ' - ' . ($m->jobPosition?->name ?? '-') : 'Data tidak ditemukan';
            })(),
            default => '-',
        };
    }
}
