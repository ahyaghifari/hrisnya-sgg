<?php

namespace App\Enum;

/**
 * Permission KHUSUS widget Dashboard (kartu/ringkasan yang nempel di halaman Beranda portal,
 * bukan halaman/Resource berdiri sendiri) - dipisah dari CustomPermissionEnum (2026-08-19,
 * instruksi user) karena Dashboard diperkirakan bakal terus nambah widget baru ke depan, dan
 * mencampurnya ke satu enum besar bikin CustomPermissionEnum makin gemuk & tidak jelas mana
 * yang gerbang halaman vs gerbang widget. Pola/perilaku PERSIS sama seperti permission custom
 * biasa (flat, tanpa format "Verb:Subject", di-seed lewat WidgetPermissionSeeder bukan
 * CustomPermissionsSeeder) - cuma dikelompokkan di file/kelas terpisah.
 */
enum WidgetPermissionEnum: string
{
    case JADWAL_SHIFT_TIM = 'jadwal_shift_tim';

    /** Widget-widget HC Manager di Beranda (instruksi user 2026-09-18) - permission SENDIRI-
     * SENDIRI per widget (pola sama LIHAT_DASHBOARD_SIKARTAP_STATISTIK/_ANTRIAN_TAHAP di
     * CustomPermissionEnum, BUKAN 1 gate gabungan), supaya admin bisa nyalain/matiin tiap
     * widget terpisah per role lewat form Role (Filament) - misal HC Manager dapat semua,
     * tapi role lain cuma sebagian. Sengaja BUKAN SISTEM_KEPEGAWAIAN (itu dipegang siapa pun
     * yang punya akses modul Kepegawaian - HC Supervisor, Kasubag, dst). Tidak otomatis
     * ke-assign ke role manapun, perlu dicentang manual di form Role. */
    case DASHBOARD_HC_SUMMARY = 'dashboard_hc_summary';
    case DASHBOARD_HC_EMPLOYEE_TYPE = 'dashboard_hc_employee_type';
    case DASHBOARD_HC_ATTENDANCE_TODAY = 'dashboard_hc_attendance_today';
    case DASHBOARD_HC_ATTENDANCE_TREND = 'dashboard_hc_attendance_trend';
    case DASHBOARD_HC_COACHING = 'dashboard_hc_coaching';
    case DASHBOARD_HC_MANPOWER = 'dashboard_hc_manpower';

    public function label(): string
    {
        return match ($this) {
            self::JADWAL_SHIFT_TIM => 'Jadwal Shift Tim (Widget Dashboard)',
            self::DASHBOARD_HC_SUMMARY => 'Ringkasan HC (Widget Beranda)',
            self::DASHBOARD_HC_EMPLOYEE_TYPE => 'Status Kepegawaian (Widget Beranda)',
            self::DASHBOARD_HC_ATTENDANCE_TODAY => 'Attendance Hari Ini (Widget Beranda)',
            self::DASHBOARD_HC_ATTENDANCE_TREND => 'Tren Kehadiran (Widget Beranda)',
            self::DASHBOARD_HC_COACHING => 'Coaching (Widget Beranda)',
            self::DASHBOARD_HC_MANPOWER => 'Manpower Planning (Widget Beranda)',
        };
    }
}
