# Konsep: Tidak Ada "User Admin"/"User Super Admin" — Yang Ada User + Role yang Di-assign (per 2026-07-18)

## Kesalahpahaman awal

Sempat ada anggapan bahwa Admin/Super Admin itu **jenis akun tersendiri**, terpisah dari karyawan — user khusus yang cuma bisa akses `/admin`. Ini tidak sesuai dengan implementasi maupun prinsip RBAC yang benar.

## Yang sebenarnya terjadi di kode

Satu model `User` untuk semua orang — karyawan biasa maupun admin sistem. Tidak ada tabel/model terpisah untuk "akun admin". `AdminUserResource` (`app/Filament/Resources/AdminUsers/AdminUserResource.php`) yang kelihatannya "bikin akun admin" itu **model-nya tetap `User::class`** — cuma listing-nya difilter `whereHas('roles', 'Admin')` dan form create-nya tidak minta data karyawan (job position dll), karena ditujukan untuk akun admin murni yang bukan karyawan tercatat (mis. akun IT/vendor eksternal).

## Prinsip RBAC yang benar

Role itu **atribut yang menempel ke identitas** (`User` via Spatie `HasRoles`), bukan jenis akun berbeda. Siapapun row `User`-nya — karyawan lengkap dengan job position, atau akun admin murni — bisa di-assign role apapun termasuk "Admin"/"Super Admin" lewat mekanisme yang sama:

```php
$user->assignRole($role);
```

Kalau ada karyawan yang juga perlu jadi Super Admin, **tidak perlu akun kedua** — cukup assign role itu ke `User` (baris karyawan) yang sudah ada. Satu orang = satu identitas = satu login, terlepas dari peran apa yang dia pegang.

Contoh nyata: `ahyaghifari288@gmail.com` (karyawan, role awal "Karyawan / Staff") di-assign tambahan role "Super Admin" lewat `$user->assignRole($role)` — sekarang dia punya 2 role sekaligus (`Super Admin`, `Karyawan / Staff`) di 1 akun yang sama, bukan akun terpisah.

## Kenapa User = Karyawan (bukan Employee terpisah dari User) itu wajar di sini

Pola alternatif yang lebih "textbook" adalah entity `Employee` (data HR) terpisah dari `User` (kredensial login, opsional 1-to-0/1). Itu masuk akal **kalau** ada sebagian entity yang secara desain tidak akan pernah butuh login (karyawan harian, outsourcing, vendor).

Di project ini, filosofi produknya justru self-service penuh lewat portal — attendance, cuti, slip gaji, LMS, coaching semua lewat portal, bukan cuma Filament admin. Kalau memang setiap karyawan pada akhirnya butuh akun untuk pakai portal, memisahkan `User` dari `Employee` cuma nambah lapisan join tanpa manfaat nyata. Lihat [[filament-scoping-analysis]] untuk kenapa modul operasional memang diarahkan ke portal custom, bukan Filament.

**Catatan terbuka**: `EmployeeForm.php` mewajibkan email+password untuk SETIAP karyawan baru — kalau ternyata ada tipe karyawan (Outsourcing/Harian Lepas) yang secara riil tidak akan pernah login, ini jadi gap nyata yang perlu dikonfirmasi ke pemilik project, bukan diasumsikan.

## Perubahan yang mengikuti prinsip ini (2026-07-18)

Karena Admin/Super Admin bukan "gerbang akun terpisah", beberapa hal ikut disederhanakan jadi satu pintu:

1. **Login satu pintu** — `->login()` dihapus dari `AdminPanelProvider` (`app/Providers/Filament/AdminPanelProvider.php`), jadi route `/admin/login` sudah tidak ada. Filament otomatis redirect ke `route('login')` (`/login`, satu-satunya gerbang) kalau ada yang akses `/admin` tanpa sesi.
2. **Tidak ada auto-redirect berdasarkan role** — `LoginController::store()` (`app/Http/Controllers/Auth/LoginController.php`) dulu redirect Admin/Super Admin otomatis ke `/admin` setelah login. Ini dihapus — semua orang (termasuk Admin/Super Admin) selalu mendarat di `dashboard` portal biasa dulu.
3. **Akses ke `/admin` lewat tombol nav, bukan redirect otomatis** — nav portal (`resources/views/components/nav.blade.php`) punya tombol "Halaman Admin" (outline navy, kelas `.nav-item-navy-outline` di `resources/css/app.css`) yang cuma tampil kalau `auth()->user()->hasRole(['Admin', 'Super Admin'])` — sudah pakai variable `$canAccessAdmin` yang sebelumnya sudah dihitung di nav tapi belum dipakai.

Jadi alurnya sekarang: login → selalu ke dashboard portal → kalau punya role Admin/Super Admin, ada tombol eksplisit buat masuk `/admin`. Tidak ada percabangan otomatis berdasarkan role saat login.
