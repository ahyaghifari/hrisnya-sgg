<?php

namespace Database\Seeders;

use App\Models\Coaching\Coaching;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh Coaching buat lihat halaman "Coaching Saya" & "Coaching" (atasan) di portal
 * terisi beneran — bukan data produksi. Idempotent lewat firstOrCreate (title unik per
 * pasangan user_id+coach_by), aman dijalankan ulang.
 *
 * php artisan db:seed --class=CoachingDemoSeeder
 */
class CoachingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $ahya = User::where('email', 'ahyaghifari288@gmail.com')->first();

        if (! $ahya) {
            $this->command?->warn('User ahyaghifari288@gmail.com tidak ditemukan, seeder dilewati.');

            return;
            
        }

        $coach = User::where('company_id', $ahya->company_id)
            ->where('id', '!=', $ahya->id)
            ->where('email', 'akbar@syifaglobalgroup.com')
            ->first()
            ?? User::where('company_id', $ahya->company_id)->where('id', '!=', $ahya->id)->first();

        if (! $coach) {
            $this->command?->warn('Tidak ada user lain di company yang sama buat jadi coach_by, seeder dilewati.');

            return;
        }

        $rows = [
            // Ahya sebagai yang dibina — muncul di "Coaching Saya".
            [
                'user_id' => $ahya->id,
                'coach_by' => $coach->id,
                'title' => 'Pembinaan Ketepatan Waktu',
                'goals' => 'Meningkatkan disiplin kehadiran tepat waktu.',
                'employee_condition' => 'Beberapa kali terlambat masuk dalam 1 bulan terakhir.',
                'solution' => 'Atur alarm lebih awal, evaluasi rute berangkat.',
                'improvement_action' => 'Datang tepat waktu selama 2 minggu berturut-turut.',
                'improvement_due_date' => now()->addWeeks(2)->toDateString(),
            ],
            [
                'user_id' => $ahya->id,
                'coach_by' => $coach->id,
                'title' => 'Pembinaan Penyelesaian Task',
                'goals' => 'Menyelesaikan task sesuai deadline yang disepakati.',
                'employee_condition' => 'Beberapa task tertunda dari target awal.',
                'solution' => 'Breakdown task jadi unit lebih kecil, laporan progres harian.',
                'improvement_action' => 'Semua task selesai sesuai deadline selama 1 bulan.',
                'improvement_due_date' => now()->subWeek()->toDateString(),
            ],
            // Ahya sebagai coach — muncul di halaman "Coaching" (atasan).
            [
                'user_id' => $coach->id,
                'coach_by' => $ahya->id,
                'title' => 'Pembinaan Dokumentasi Kode',
                'goals' => 'Membiasakan menulis dokumentasi teknis yang memadai.',
                'employee_condition' => 'Dokumentasi fitur baru sering tertinggal.',
                'solution' => 'Checklist dokumentasi sebelum PR dianggap selesai.',
                'improvement_action' => 'Semua PR baru menyertakan dokumentasi ringkas.',
                'improvement_due_date' => now()->addWeek()->toDateString(),
            ],
        ];

        foreach ($rows as $row) {
            Coaching::firstOrCreate(
                [
                    'user_id' => $row['user_id'],
                    'coach_by' => $row['coach_by'],
                    'title' => $row['title'],
                ],
                array_merge($row, ['company_id' => $ahya->company_id])
            );
        }

        $this->command?->info('CoachingDemoSeeder selesai: '.count($rows).' baris coaching contoh.');
    }
}
