<?php

use App\Livewire\Dashboard;
use App\Livewire\DashboardAndaPage;
use App\Livewire\EmployeeAttendanceStatistics;
use App\Livewire\MyEmployees;
use App\Livewire\OrganizationStructure;
use App\Livewire\Pembinaan\PembinaanAtasanPage;
use App\Http\Controllers\Pwa\PushSubscriptionController;
use App\Livewire\Pwa\Akun as PwaAkun;
use App\Livewire\Pwa\NotificationSettingsPage;
use App\Livewire\Pwa\UbahAkunLogin as PwaUbahAkunLogin;
use App\Livewire\Pwa\UbahPassword as PwaUbahPassword;
use App\Livewire\RiwayatApprovalPage;
use Illuminate\Support\Facades\Route;

/**
 * Route portal umum: dashboard, akun/notifikasi/push, Profil Saya, Struktur Organisasi,
 * My Staff, hak akses/role. Dipisah dari routes/web.php (2026-09-04) - lihat komentar di
 * routes/sikartap.php untuk konvensi split per-modul (bare Route::* di sini, inherit
 * middleware('auth','portal.access') dari require di web.php, bukan group() baru).
 */
Route::get('/', Dashboard::class)
    ->name('dashboard');

Route::get('/dashboard-anda', DashboardAndaPage::class)
    ->name('dashboard-anda');

Route::get('/notifikasi', \App\Livewire\NotificationsPage::class)
    ->name('notifikasi');

Route::get('/akun', PwaAkun::class)
    ->name('akun');

Route::get('/akun/ubah-password', PwaUbahPassword::class)
    ->name('akun.ubah-password');

Route::get('/akun/ubah-login', PwaUbahAkunLogin::class)
    ->name('akun.ubah-login');

Route::get('/akun/notifikasi', NotificationSettingsPage::class)
    ->name('akun.notifikasi');

Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])
    ->name('push.subscribe');
Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])
    ->name('push.unsubscribe');

Route::get('/profil-saya', EmployeeAttendanceStatistics::class)
    ->name('profil-saya');

Route::get('/profil-saya/penggajian/{payroll}', \App\Livewire\Keuangan\MyPayrollSlipPage::class)
    ->name('profil-saya.penggajian.show');

// Lampiran data diri (KTP/KK/dsb) — file terenkripsi di disk privat 'local', tidak boleh
// dilayani lewat URL /storage langsung. Route ini decrypt di memory + gerbang izin
// (pemilik akun atau permission lihat_lampiran_identitas_karyawan, sama seperti
// EmployeeAttendanceStatistics::getCanViewProfileDocumentsProperty()).
Route::get('/profil-saya/lampiran/{document}', function (\App\Models\User\UserProfileDocument $document) {
    $user = \Illuminate\Support\Facades\Auth::user();
    abort_unless(
        $document->user_id === $user->id
            || $user->can(\App\Enum\CustomPermissionEnum::LIHAT_LAMPIRAN_IDENTITAS_KARYAWAN->value),
        403
    );

    $ext = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));

    // Lampiran baru: terenkripsi di 'local'. Lampiran lama (< 2026-08-27): plaintext di
    // 'public' — dilayani apa adanya tanpa decrypt.
    $raw = \Illuminate\Support\Facades\Storage::disk('local')->exists($document->file_path)
        ? \Illuminate\Support\Facades\Crypt::decryptString(\Illuminate\Support\Facades\Storage::disk('local')->get($document->file_path))
        : \Illuminate\Support\Facades\Storage::disk('public')->get($document->file_path);

    abort_if($raw === null, 404);

    return response($raw, 200, [
        'Content-Type' => $ext === 'pdf' ? 'application/pdf' : ($ext === 'png' ? 'image/png' : 'image/jpeg'),
        'Content-Disposition' => 'inline; filename="' . addslashes($document->label) . '.' . $ext . '"',
    ]);
})->name('profil-saya.lampiran.show');

Route::get('/profil-saya/penggajian/{payroll}/preview', function (\App\Models\Payroll\Payroll $payroll) {
    abort_unless(
        $payroll->user_id === \Illuminate\Support\Facades\Auth::id() && $payroll->is_paid,
        403
    );

    return app(\App\Services\Payroll\PayrollService::class)->preview($payroll);
})->name('profil-saya.penggajian.preview');

// Path lama /data-diri masih bisa dibookmark orang - redirect permanen ke path baru,
// bukan dihapus begitu saja (rename 2026-08-19, label sudah lama "Profil Saya" di nav,
// URL-nya baru sekarang disamakan).
Route::redirect('/data-diri', '/profil-saya', 301);

Route::get('/struktur-organisasi', OrganizationStructure::class)
    ->name('organization-structure');

Route::get('/my-staff', MyEmployees::class)
    ->name('my-staff');

// Halaman TAMBAHAN, terpisah dari modul Pembinaan Karyawan (eks-Coaching & Counseling) HC
// di routes/pembinaan.php - lihat gate literal hasSubordinates() di
// PembinaanAtasanPage::mount() (instruksi user 2026-08-17).
Route::get('/my-staff/pembinaan', PembinaanAtasanPage::class)
    ->name('my-staff.pembinaan');

Route::get('/riwayat-approval', RiwayatApprovalPage::class)
    ->name('riwayat-approval');

Route::get('/hak-akses', \App\Livewire\RoleAccess\RoleAccessTreePage::class)
    ->name('hak-akses');

Route::get('/role', \App\Livewire\RoleAccess\RolesPage::class)
    ->name('role');

Route::get('/akses-filter-perusahaan', \App\Livewire\RoleAccess\CompanyFilterAccessPage::class)
    ->name('akses-filter-perusahaan');

// Halaman detail karyawan dipakai 2 entry point: MyStaff (bawahan) & Karyawan (company-wide,
// routes/kepegawaian.php) — makanya path-nya /karyawan, bukan /my-staff.
Route::get('/karyawan/{record}', EmployeeAttendanceStatistics::class)
    ->name('karyawan.show');
