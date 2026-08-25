<?php

namespace App\Filament\Resources\Sikartap\Pages;

use App\Filament\Resources\Sikartap\SikartapResource;
use App\Models\Sikartap\Sikartap;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Sikartap\SikartapDirectorDecisionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewSikartap extends ViewRecord
{
    protected static string $resource = SikartapResource::class;

    protected static ?string $title = 'Detail Pengajuan Karyawan Tetap';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveAll')
                ->label('Setujui Semua & Lanjut Proses')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Sikartap $record): bool => $record->status_berkas !== 'approved'
                    && $record->hasAllRequiredDocumentsUploaded()
                    && Auth::user()->can('Update:Sikartap'))
                ->action(function (Sikartap $record): void {
                    $record->documents()->whereNotNull('file_path')->update([
                        'status' => 'approved',
                        'catatan_penolakan' => null,
                    ]);

                    $record->update(['status_berkas' => 'approved', 'berkas_lengkap' => true]);

                    Notification::make()->title('Berkas disetujui, lanjut ke proses tes')->success()->send();

                    $this->redirect(SikartapResource::getUrl('view', ['record' => $record]));
                }),

            Action::make('rejectApplication')
                ->label('Tolak Pengajuan')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Sikartap $record): bool => $record->status_berkas !== 'approved'
                    && Auth::user()->can('Update:Sikartap'))
                ->schema([
                    Textarea::make('catatan_penolakan')
                        ->label('Catatan Penolakan')
                        ->helperText('Semua dokumen yang sudah diupload akan ditolak dengan catatan yang sama.')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (Sikartap $record, array $data): void {
                    $record->documents()->whereNotNull('file_path')->update([
                        'status' => 'rejected',
                        'catatan_penolakan' => $data['catatan_penolakan'],
                    ]);

                    $record->update(['status_berkas' => 'rejected']);

                    Notification::make()->title('Pengajuan ditolak')->success()->send();

                    $this->redirect(SikartapResource::getUrl('view', ['record' => $record]));
                }),

            // ── Fase 2: Tes & Wawancara (rewrite detail_pengajuan.php Tahap 2/3) ──────

            Action::make('scheduleTest')
                ->label(fn (Sikartap $record) => $record->testSchedule ? 'Ubah Jadwal Tes' : 'Jadwalkan Tes')
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->visible(fn (Sikartap $record): bool => $record->status_berkas === 'approved'
                    && Auth::user()->can('Update:Sikartap'))
                ->fillForm(fn (Sikartap $record): array => $record->testSchedule?->only(['tanggal_tes', 'waktu_tes', 'tempat_tes', 'keterangan']) ?? [])
                ->schema([
                    DatePicker::make('tanggal_tes')->label('Tanggal')->required(),
                    TimePicker::make('waktu_tes')->label('Waktu')->required(),
                    TextInput::make('tempat_tes')->label('Tempat')->required(),
                    Textarea::make('keterangan')->label('Keterangan')->rows(2),
                ])
                ->action(function (Sikartap $record, array $data): void {
                    $record->testSchedule()->updateOrCreate(['sikartap_id' => $record->id], $data);

                    Notification::make()->title('Jadwal tes disimpan')->success()->send();
                }),

            Action::make('submitTestResult')
                ->label('Input Hasil Tes')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn (Sikartap $record): bool => $record->testSchedule !== null
                    && Auth::user()->can('Update:Sikartap'))
                ->fillForm(fn (Sikartap $record): array => $record->only(['nilai_tes', 'nilai_psikotes', 'status_tes', 'catatan_tes']))
                ->schema([
                    TextInput::make('nilai_tes')->label('Nilai Tes Bidang')->numeric()->required(),
                    TextInput::make('nilai_psikotes')->label('Nilai Psikotes')->numeric()->required(),
                    Select::make('status_tes')
                        ->label('Status')
                        ->options(['lulus' => 'Lulus', 'tidak_lulus' => 'Tidak Lulus'])
                        ->native(false)
                        ->required(),
                    Textarea::make('catatan_tes')->label('Catatan')->rows(3),
                ])
                ->action(function (Sikartap $record, array $data): void {
                    $record->update($data);

                    Notification::make()->title('Hasil tes disimpan')->success()->send();
                }),

            Action::make('scheduleInterview')
                ->label(fn (Sikartap $record) => $record->interviewSchedule ? 'Ubah Jadwal Wawancara' : 'Jadwalkan Wawancara')
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->visible(fn (Sikartap $record): bool => $record->status_tes === 'lulus'
                    && Auth::user()->can('Update:Sikartap'))
                ->fillForm(fn (Sikartap $record): array => $record->interviewSchedule?->only(['tanggal_wawancara', 'waktu_wawancara', 'tempat_wawancara', 'pewawancara', 'pewawancara_2', 'keterangan']) ?? [])
                ->schema([
                    DatePicker::make('tanggal_wawancara')->label('Tanggal')->required(),
                    TimePicker::make('waktu_wawancara')->label('Waktu')->required(),
                    TextInput::make('tempat_wawancara')->label('Tempat')->required(),
                    TextInput::make('pewawancara')->label('Pewawancara 1')->required(),
                    TextInput::make('pewawancara_2')->label('Pewawancara 2 (opsional)'),
                    Textarea::make('keterangan')->label('Keterangan')->rows(2),
                ])
                ->action(function (Sikartap $record, array $data): void {
                    $record->interviewSchedule()->updateOrCreate(['sikartap_id' => $record->id], $data);

                    Notification::make()->title('Jadwal wawancara disimpan')->success()->send();
                }),

            Action::make('submitInterviewResult')
                ->label('Input Hasil Wawancara')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn (Sikartap $record): bool => $record->interviewSchedule !== null
                    && Auth::user()->can('Update:Sikartap'))
                ->fillForm(fn (Sikartap $record): array => $record->only(['status_wawancara', 'catatan_wawancara']))
                ->schema([
                    Select::make('status_wawancara')
                        ->label('Status')
                        ->options(['disarankan' => 'Disarankan', 'tidak_disarankan' => 'Tidak Disarankan'])
                        ->native(false)
                        ->required(),
                    Textarea::make('catatan_wawancara')->label('Catatan')->rows(3),
                ])
                ->action(function (Sikartap $record, array $data): void {
                    $record->update($data);

                    Notification::make()->title('Hasil wawancara disimpan')->success()->send();
                }),

            // ── Fase 3: MCU (rewrite Tahap 4 detail_pengajuan.php) ────────────────────
            // Legacy: $rek_dokter dipakai HRD sebagai saran, HRD tetap yang memutuskan
            // status_mcu final — bukan otomatis dari rekomendasi dokter (lihat komentar
            // PemeriksaanDokterPage). Digerbangi dokter sudah isi pemeriksaan, supaya HRD
            // tidak memutuskan sebelum ada hasil sama sekali.

            Action::make('submitMcuDecision')
                ->label(fn (Sikartap $record) => $record->status_mcu === 'pending' ? 'Putuskan Hasil MCU' : 'Ubah Hasil MCU')
                ->icon('heroicon-o-heart')
                ->color('gray')
                ->visible(fn (Sikartap $record): bool => $record->status_wawancara === 'disarankan'
                    && $record->mcuDoctorExamination !== null
                    && Auth::user()->can('Update:Sikartap'))
                ->fillForm(fn (Sikartap $record): array => [
                    'status_mcu' => $record->status_mcu === 'pending' ? null : $record->status_mcu,
                    'catatan_mcu' => $record->catatan_mcu ?? $record->mcuDoctorExamination?->rekomendasi,
                ])
                ->schema([
                    Select::make('status_mcu')
                        ->label('Status MCU')
                        ->options(['fit' => 'Fit', 'unfit' => 'Unfit'])
                        ->native(false)
                        ->required(),
                    Textarea::make('catatan_mcu')->label('Catatan')->rows(3),
                ])
                ->action(function (Sikartap $record, array $data): void {
                    $record->update($data);

                    Notification::make()->title('Hasil MCU disimpan')->success()->send();
                }),

            // ── Fase 4: Keputusan Direktur (rewrite Tahap 5 detail_pengajuan_direktur.php) ──
            // WAJIB lewat Approval Engine (bukan kolom ditulis langsung dari sini) - lihat
            // SikartapDirectorDecisionService & analisis § Approval Engine vs custom di
            // rencana-implementasi-sikartap.md.

            Action::make('ajukanKeputusanDirektur')
                ->label('Ajukan ke Direktur')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Pengajuan akan masuk ke alur approval Direktur sesuai flow perusahaan.')
                ->visible(fn (Sikartap $record): bool => $record->isEligibleForDirectorDecision()
                    && $record->approvalSnapshot === null
                    && Auth::user()->can('Update:Sikartap'))
                ->action(function (Sikartap $record): void {
                    try {
                        app(SikartapDirectorDecisionService::class)->submitForDecision($record);

                        Notification::make()->title('Pengajuan dikirim ke Direktur')->success()->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Gagal mengajukan')->body($e->getMessage())->danger()->send();
                    }

                    $this->redirect(SikartapResource::getUrl('view', ['record' => $record]));
                }),

            Action::make('approveDirektur')
                ->label('Setujui (Direktur)')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (Sikartap $record): bool => $record->approvalSnapshot !== null
                    && app(ApprovalPermissionService::class)->canApprove($record->approvalSnapshot->id, Auth::user()))
                ->schema([
                    Textarea::make('note')->label('Catatan')->rows(3),
                ])
                ->action(function (Sikartap $record, array $data): void {
                    app(SikartapDirectorDecisionService::class)->approve($record, $data['note'] ?? '');

                    Notification::make()->title('Keputusan tersimpan')->success()->send();

                    $this->redirect(SikartapResource::getUrl('view', ['record' => $record]));
                }),

            Action::make('rejectDirektur')
                ->label('Tolak (Direktur)')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Sikartap $record): bool => $record->approvalSnapshot !== null
                    && app(ApprovalPermissionService::class)->canApprove($record->approvalSnapshot->id, Auth::user()))
                ->schema([
                    Textarea::make('note')->label('Catatan Penolakan')->required()->rows(3),
                ])
                ->action(function (Sikartap $record, array $data): void {
                    app(SikartapDirectorDecisionService::class)->reject($record, $data['note']);

                    Notification::make()->title('Keputusan tersimpan')->success()->send();

                    $this->redirect(SikartapResource::getUrl('view', ['record' => $record]));
                }),
        ];
    }
}
