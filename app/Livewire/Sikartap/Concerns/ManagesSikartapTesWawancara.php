<?php

namespace App\Livewire\Sikartap\Concerns;

use App\Models\Sikartap\Sikartap;
use App\Notifications\Sikartap\SikartapHasilTes;
use App\Notifications\Sikartap\SikartapHasilWawancara;
use App\Notifications\Sikartap\SikartapJadwalTesDitetapkan;
use App\Notifications\Sikartap\SikartapJadwalWawancaraDitetapkan;
use Filament\Notifications\Notification;

/**
 * Form jadwal + hasil Tes & Wawancara — dipakai bareng PengajuanDetailPage (per-record) serta
 * TesPage/WawancaraPage (list, penjadwalan bisa massal). Satu-satunya sumber kebenaran untuk
 * gate tahap, aturan validasi, kolom yang disimpan, dan notifikasi — biar tempat-tempat itu
 * tidak menyimpang.
 *
 * Host wajib: properti $activePanel, accessor $this->canUpdate, dan method sikartapContext()
 * (record tunggal buat panel di detail / modal hasil). Override afterSikartapSave() kalau
 * perlu aksi lanjutan (mis. refresh record). TesPage/WawancaraPage meng-override
 * save*Schedule() jadi versi massal yang memakai helper apply*() di sini.
 */
trait ManagesSikartapTesWawancara
{
    public array $testScheduleForm = ['tanggal_tes' => null, 'waktu_tes' => null, 'tempat_tes' => null, 'keterangan' => null];

    public array $interviewScheduleForm = ['tanggal_wawancara' => null, 'waktu_wawancara' => null, 'tempat_wawancara' => null, 'pewawancara' => null, 'pewawancara_2' => null, 'keterangan' => null];

    public array $testResultForm = ['nilai_tes' => null, 'nilai_psikotes' => null, 'status_tes' => null, 'catatan_tes' => null];

    public array $interviewResultForm = ['status_wawancara' => null, 'catatan_wawancara' => null];

    /** Host wajib menyediakan: record tunggal yang jadi target save 1-record di sini. */
    // protected function sikartapContext(): Sikartap;

    protected function afterSikartapSave(): void {}

    protected function closeSikartapModal(): void
    {
        $this->activePanel = null;
    }

    // ── Gate tahap (sumber kebenaran, dipakai untuk hide tombol + abort) ──

    /** Boleh (re)jadwalkan tes selama berkas lolos & hasil tes belum diinput. */
    public static function canScheduleTest(Sikartap $s): bool
    {
        return $s->status_berkas === 'approved'
            && in_array($s->status_tes, [null, 'pending'], true);
    }

    /** Boleh (re)jadwalkan wawancara selama tes lulus & hasil wawancara belum diinput. */
    public static function canScheduleInterview(Sikartap $s): bool
    {
        return $s->status_tes === 'lulus'
            && in_array($s->status_wawancara, [null, 'pending'], true);
    }

    public static function canEnterTestResult(Sikartap $s): bool
    {
        return $s->testSchedule !== null;
    }

    public static function canEnterInterviewResult(Sikartap $s): bool
    {
        return $s->interviewSchedule !== null;
    }

    // ── Aturan validasi ─────────────────────────────────────────────────

    protected function testScheduleRules(): array
    {
        return [
            'testScheduleForm.tanggal_tes' => ['required', 'date'],
            'testScheduleForm.waktu_tes' => ['required'],
            'testScheduleForm.tempat_tes' => ['required', 'string'],
        ];
    }

    protected function interviewScheduleRules(): array
    {
        return [
            'interviewScheduleForm.tanggal_wawancara' => ['required', 'date'],
            'interviewScheduleForm.waktu_wawancara' => ['required'],
            'interviewScheduleForm.tempat_wawancara' => ['required', 'string'],
            'interviewScheduleForm.pewawancara' => ['required', 'string'],
        ];
    }

    // ── Kernel: persist + notifikasi (1 record) ─────────────────────────

    protected function applyTestSchedule(Sikartap $s, array $data): void
    {
        $s->testSchedule()->updateOrCreate(['sikartap_id' => $s->id], $data);
        $s->user?->notify(new SikartapJadwalTesDitetapkan($s->id, $data['tanggal_tes'], $data['tempat_tes']));
    }

    protected function applyInterviewSchedule(Sikartap $s, array $data): void
    {
        $s->interviewSchedule()->updateOrCreate(['sikartap_id' => $s->id], $data);
        $s->user?->notify(new SikartapJadwalWawancaraDitetapkan($s->id, $data['tanggal_wawancara'], $data['tempat_wawancara']));
    }

    protected function applyTestResult(Sikartap $s, array $data): void
    {
        $s->update($data);
        $s->user?->notify(new SikartapHasilTes($s->id, $data['status_tes']));
    }

    protected function applyInterviewResult(Sikartap $s, array $data): void
    {
        $s->update($data);
        $s->user?->notify(new SikartapHasilWawancara($s->id, $data['status_wawancara']));
    }

    // ── Save 1 record (default; TesPage/WawancaraPage override schedule jadi massal) ──

    public function saveTestSchedule(): void
    {
        $s = $this->sikartapContext();
        abort_unless($this->canUpdate && static::canScheduleTest($s), 403);

        $this->validate($this->testScheduleRules());
        $this->applyTestSchedule($s, $this->testScheduleForm);

        $this->closeSikartapModal();
        $this->afterSikartapSave();

        Notification::make()->title('Jadwal tes disimpan')->success()->send();
    }

    public function saveInterviewSchedule(): void
    {
        $s = $this->sikartapContext();
        abort_unless($this->canUpdate && static::canScheduleInterview($s), 403);

        $this->validate($this->interviewScheduleRules());
        $this->applyInterviewSchedule($s, $this->interviewScheduleForm);

        $this->closeSikartapModal();
        $this->afterSikartapSave();

        Notification::make()->title('Jadwal wawancara disimpan')->success()->send();
    }

    public function saveTestResult(): void
    {
        $s = $this->sikartapContext();
        abort_unless($this->canUpdate && static::canEnterTestResult($s), 403);

        $this->validate([
            'testResultForm.nilai_tes' => ['required', 'numeric'],
            'testResultForm.nilai_psikotes' => ['required', 'numeric'],
            'testResultForm.status_tes' => ['required', 'in:lulus,tidak_lulus'],
        ]);
        $this->applyTestResult($s, $this->testResultForm);

        $this->closeSikartapModal();
        $this->afterSikartapSave();

        Notification::make()->title('Hasil tes disimpan')->success()->send();
    }

    public function saveInterviewResult(): void
    {
        $s = $this->sikartapContext();
        abort_unless($this->canUpdate && static::canEnterInterviewResult($s), 403);

        $this->validate([
            'interviewResultForm.status_wawancara' => ['required', 'in:disarankan,tidak_disarankan'],
        ]);
        $this->applyInterviewResult($s, $this->interviewResultForm);

        $this->closeSikartapModal();
        $this->afterSikartapSave();

        Notification::make()->title('Hasil wawancara disimpan')->success()->send();
    }
}
