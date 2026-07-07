# Planning: Pemetaan Menu Navigasi Filament ke Role — 2026-07-05

Status: **catatan referensi/pengetahuan saja — belum tentu dieksekusi, boleh dijadikan bahan diskusi.**

## Kenapa dokumen ini dibuat

Setelah navigasi Filament dirapikan jadi 7 grup (Master Data, Kepegawaian, Manajemen Organisasi, Jadwal Shift Karyawan, Manajemen Pengguna, Keuangan, LMS) dan form Role juga sudah disamakan groupingnya (lihat `RoleForm.php`), pertanyaan alaminya: **kalau bikin role baru, menu/resource apa saja yang masuk akal untuk role itu?** Dokumen ini pemetaannya, per role, disusun dari nama role yang sudah ada di `RoleSeeder.php` dan konteks tugasnya masing-masing.

Ini **bukan** dokumen baru dari nol — ini penjelasan/alasan di balik apa yang **sudah dikodekan** di `database/seeders/RoleDefaultPermissionsSeeder.php` (dibuat sesi sebelumnya, belum tentu sudah dijalankan ke database asli — cek dulu sebelum asumsi sudah aktif). Kalau ada perbedaan antara dokumen ini dan seeder itu, seeder yang jadi acuan kode aktual; dokumen ini yang perlu disesuaikan.

## Daftar Role (dari `RoleSeeder.php`)

| Role | Cakupan company | Karakter tugas |
|---|---|---|
| **Super Admin** | Holding saja | Akses penuh semua menu — tidak perlu dipetakan, otomatis dapat semua |
| **Admin** | Semua company selain holding | Setara Super Admin tapi lingkup 1 company — juga tidak perlu dipetakan detail |
| **Direktur** | Holding (lintas company kalau `canBrowseAllCompanies()`) | Pengawasan level atas, keputusan final approval, bukan operasional harian |
| **HC Supervisor** | Per company | Operasional HR harian |
| **HC Manager** | Per company | HR level lebih tinggi, plus kontrol master data kepegawaian |
| **Kasubag / Staff Kepegawaian** | Per company | Staff HR operasional, tanpa kontrol master data |
| **Staff Finance** | Per company | Payroll, jasa dokter, gaji trainer, insentif |
| **Learning & Development** | Per company | LMS, pelatihan |
| **Manager / Kepala Bidang** | Per company | Atasan lintas-bidang, approval bawahan |
| **Supervisor / Kepala Ruangan** | Per company | Atasan langsung level unit/ruangan |
| **Karyawan / Staff** | Per company | Self-service saja |

## Pemetaan per Grup Navigasi

### Master Data
Menu di sini (Perusahaan, Departemen, Divisi, Sub Divisi, Pangkat, Golongan, Kelas, Jabatan, Tipe Karyawan, Agama, Bank, Jenis Cuti, Jenis Izin, Jenis Lembur, Layanan Penugasan) — semua bersifat **konfigurasi jangka panjang**, jarang berubah. Cocoknya:
- **HC Manager** — akses penuh (create/edit) untuk item yang berhubungan langsung ke struktur kepegawaian (Departemen, Divisi, Sub Divisi, Jabatan, Pangkat, Golongan, Kelas, Tipe Karyawan, Jenis Cuti, Jenis Izin, Jenis Lembur).
- **HC Supervisor / Kasubag Kepegawaian** — cukup **view**, tidak perlu ubah master data (biar tidak berantakan kalau semua orang bisa ubah).
- Perusahaan, Agama, Bank — biasanya cuma perlu diisi sekali di awal, cukup **Super Admin/Admin** saja yang pegang, tidak perlu masuk role operasional manapun.

### Kepegawaian
| Menu | Role yang cocok |
|---|---|
| Karyawan | HC Supervisor & HC Manager (view+create+edit), HC Manager tambah delete; Manager/Kepala Bidang & Supervisor cukup **view** (buat lihat bawahan) |
| Dokumen Kepegawaian | HC Supervisor & HC Manager (full) |
| Penilaian Karyawan (Tipe Penilaian, Form Template, Assessment, User Assessment) | HC Manager (full, termasuk atur template), Manager/Kepala Bidang & Supervisor (isi penilaian ke bawahan — view+create+edit), HC Supervisor (view) |
| Pola Shift (Master Data Shift, Jadwal template, Tukar Shift Approval, Template Shift) | HC Manager / HC Supervisor — ini soal desain shift, bukan personal, jadi HR yang atur |
| Coaching / Counseling | HC Supervisor & HC Manager (full), Manager/Kepala Bidang & Supervisor (view+create — mereka yang kasih coaching ke bawahan) |
| Manajemen Lowongan Kerja | HC Manager (full+approve), Direktur (view+approve — persetujuan akhir buka lowongan) |

### Manajemen Organisasi
| Menu | Role yang cocok |
|---|---|
| Data Absensi | HC Supervisor/Manager (view), Direktur (view+report) |
| Attendance Request (versi admin, bukan portal) | HC Supervisor/Manager (view+approve — sebagai fallback kalau approval flow butuh HR turun tangan) |
| Penugasan Individu | Manager/Kepala Bidang & Supervisor (view+edit — mereka yang assign tugas ke bawahan), Karyawan (view — lihat tugas sendiri, biasanya lewat portal bukan Filament) |
| Manpower Planning | HC Manager (full), Direktur (view) |
| Perubahan Status Karyawan | HC Manager (full), HC Supervisor/Kasubag (view+create — ajukan perubahan, HC Manager yang approve) |
| **Manajemen Persetujuan** (baru dipindah ke sini) | **HC Manager** (full — dia yang paling sering atur alur approval baru), Direktur (view — buat ngerti alur yang berlaku) |

### Jadwal Shift Karyawan
| Menu | Role yang cocok |
|---|---|
| Jadwal Shift (per karyawan) | HC Supervisor/Manager (full — assign jadwal), Supervisor/Kepala Ruangan (view — lihat jadwal timnya) |
| Tukar Shift | HC Supervisor/Manager (approve), Karyawan (ajukan — biasanya lewat portal) |

### Manajemen Pengguna
Sekarang cuma berisi **Role** dan **Permission** (Data Diri sudah dipisah berdiri sendiri di luar grup, Manajemen Persetujuan sudah pindah ke Manajemen Organisasi). Ini murni menu **administrasi sistem**, bukan operasional bisnis — cocoknya **tetap dikunci ke Super Admin/Admin saja**. Tidak disarankan dikasih ke role operasional manapun (HC Manager sekalipun) kecuali memang ada kebutuhan HR mengatur role sendiri — kalau iya, cukup **view_role** saja (lihat siapa punya akses apa), bukan create/edit/delete.

### Keuangan
| Menu | Role yang cocok |
|---|---|
| Payroll, Payroll Period | **Staff Finance** (full) — satu-satunya yang megang ini |
| Pengajuan Jasa Dokter, Doctor Payroll Period | **Staff Finance** — lihat catatan di `analisis-payroll.md` soal ini seharusnya cuma relevan buat company rumah sakit, jadi role ini idealnya cuma diberi ke Staff Finance **di company rumah sakit**, bukan otomatis semua company |
| Gaji Trainer, Variabel/Skala Nilai Trainer | **Staff Finance** (full), Learning & Development (view — buat tahu skor trainernya sudah dinilai) |
| Program Incentive | **Staff Finance** (full) |
| Master Data Gaji (Komponen Gaji, Lembur, dst) | **Staff Finance** (full) — ini konfigurasi tarif, bukan transaksi harian |
| Direktur | view semua di atas (oversight, bukan input) |

### LMS
| Menu | Role yang cocok |
|---|---|
| Pelatihan, Kategori, Rencana Pelatihan, Bank Soal, Kuis, Baseline Pelatihan, Laporan Pelatihan, Trainer | **Learning & Development** (full) — pemilik penuh modul ini |
| Kompetensi, Jabatan & Kompetensi | Learning & Development (full), HC Manager (view — ini juga menyentuh data jabatan) |
| Item Rencana Pelatihan | Learning & Development (full, biasanya diakses lewat halaman Rencana Pelatihan, bukan menu berdiri sendiri) |

## Catatan Penting

1. **Karyawan / Staff** sengaja tidak dimasukkan ke tabel di atas kolom demi kolom — perannya murni self-service (data pribadi sendiri, ajukan cuti/izin/lembur sendiri, lihat tugas sendiri) yang **kebanyakan sudah dilayani lewat portal Livewire** (`/dashboard`, `/karyawanku`, `/pengajuan-absensi`), bukan lewat Filament admin panel sama sekali. Jangan buru-buru kasih akses Filament ke role ini kecuali memang ada kebutuhan spesifik.
2. **Direktur** di seluruh tabel di atas konsisten cuma dapat **view** + approval level akhir (bukan create/edit/delete operasional) — sesuai posisinya sebagai pengawas, bukan pelaksana.
3. Pemetaan ini **sudah dituangkan ke kode** di `RoleDefaultPermissionsSeeder.php` (per permission string, bukan per nama menu) — dokumen ini cuma versi "terjemahan ke bahasa menu" biar lebih gampang didiskusikan dengan yang tidak familiar baca daftar permission string.
4. Belum dijalankan ke database produksi/dev — ingat aturan project ini soal seeder yang mengubah data role/permission: review dulu isinya sebelum `php artisan db:seed --class=RoleDefaultPermissionsSeeder`, karena ini keputusan akses yang mempengaruhi user sungguhan.
5. **Jasa Dokter tidak dibatasi per tipe company** (lihat `analisis-payroll.md` §5-6) — kalau mau benar-benar "cuma rumah sakit yang lihat menu ini", perlu diatur manual per company lewat `/admin/roles` (assign permission `view_pengajuan_jasa_dokter` cuma ke role Staff Finance di company RSU), bukan otomatis dari seeder generik ini yang menyamakan semua company.

## Kalau Mau Ditindaklanjuti

Kalau nanti keputusan sudah bulat dan mau benar-benar diterapkan:
1. Review ulang `RoleDefaultPermissionsSeeder.php` — cocokkan dengan tabel di atas, sesuaikan kalau ada yang beda pendapat.
2. Jalankan `php artisan db:seed --class=RoleDefaultPermissionsSeeder` di environment yang tepat.
3. Untuk kasus jasa dokter (per company-type, bukan generik), assign manual lewat `/admin/roles` khusus company rumah sakit — tidak lewat seeder umum ini.
4. Verifikasi lewat login sebagai tiap role, cek menu yang muncul di sidebar sudah sesuai ekspektasi.
