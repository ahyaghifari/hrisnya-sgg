# Checklist Deploy — HRIS Syifa Global Group

Referensi cepat "apa yang harus dicek/dilakukan tiap kali deploy ke production", digabung dari `README.md` (Known Gaps), `GIT-WORKFLOW.md`, dan pola yang sudah beberapa kali terjadi di riwayat sesi (`planning/next/*.md`). Bukan pipeline otomatis — semua langkah di sini masih **manual**, sesuai kondisi project saat ini (lihat §0).

## 0. Konteks penting sebelum mulai

- **Push ke `main` = langsung deploy ke server** (lihat `GIT-WORKFLOW.md`). Jangan pernah `git push origin main` kalau belum benar-benar siap.
- **Tidak ada test/lint gate di CI** — lolos `git push` bukan berarti kode benar. Jalankan test lokal dulu kalau ada test yang relevan (`php artisan test`), dan `php artisan migrate` di DB dev dulu sebelum production.
- **Queue** pakai driver `database` (`QUEUE_CONNECTION=database` di `.env`), **bukan** sync — artinya ada proses `queue:work`/`queue:listen` yang jalan terus-menerus di server (di luar repo ini, dikelola manual/supervisor server). Kalau ada Job baru atau Job yang class-nya diubah, worker itu **harus di-restart** setelah deploy (`php artisan queue:restart` cukup, tidak perlu kill manual — restart soft, worker lama selesaikan job yang sedang jalan dulu).
- **Redis dikonfigurasi tapi tidak dipakai** — cache/queue/session tetap `database`, jangan asumsikan Redis aktif di server.

## 1. Lanjut kerja di komputer lain (branch `dev`, belum deploy)

Beda dari deploy production (§3-6 di bawah) — ini buat kasus "kerjaan sudah di-push ke `dev`, lanjut dari komputer/environment lain". **Kerjaan yang belum di-`git commit`+push TIDAK ikut pindah** — pastikan sudah push dari komputer asal dulu.

- [ ] `git fetch origin && git checkout dev && git pull origin dev`
- [ ] `composer install` (aman dijalankan selalu, idempotent — cek `composer.lock` ikut berubah di commit terakhir kalau mau tahu perlu atau tidak)
- [ ] `npm install` (sama, aman selalu — perlu kalau `package-lock.json`/`package.json` ikut berubah)
- [ ] `php artisan migrate:status` dulu buat lihat migration mana yang pending di DB komputer ini, baru `php artisan migrate` — **DB tiap komputer itu instance MySQL lokal terpisah**, migration yang sudah jalan di komputer A tidak otomatis "sudah jalan" juga di komputer B walau kodenya sama.
- [ ] Jalankan seeder manual yang relevan sama perubahan terakhir (cek migration/seeder baru di commit yang baru di-pull) — contoh dari sesi 2026-07-17: `php artisan db:seed --class=PayrollSettingsSeeder` (idempotent, aman diulang kapan saja).
- [ ] `php artisan shield:generate --panel=admin --all --option=policies_and_permissions` **kalau ada Resource Filament yang berubah/dihapus** di commit yang di-pull (biar permission & Policy re-sync ke keadaan kode terbaru).
- [ ] `php artisan view:clear && php artisan config:clear` — buang cache lokal lama, terutama kalau ada class/enum yang di-rename atau dihapus (cache view lama bisa nyimpen referensi ke class yang sudah tidak ada).
- [ ] `npm run watch` (atau `npm run build` sekali kalau cuma mau lihat hasil akhir) — kalau ada perubahan `resources/css/app.css`, tanpa ini CSS baru tidak muncul di browser (lihat README § Known Gaps soal ini).
- [ ] **Permission/akses test tidak ikut pindah otomatis** — kalau sebelumnya ada permission yang di-grant manual ke 1 akun buat testing (lewat tinker, bukan lewat `/admin/roles`), itu cuma ada di DB komputer asal. Grant ulang di komputer ini kalau perlu:
  ```bash
  php artisan tinker --execute="\App\Models\User::where('email','EMAIL_AKUN')->first()->givePermissionTo('NAMA_PERMISSION');"
  ```
- [ ] Kalau DB komputer ini bukan salinan production yang sama (mis. lebih basi/beda snapshot dari komputer asal) — data uji (company, pangkat, user) bisa beda, jangan asumsikan hasil test di komputer A otomatis sama di komputer B.

## 2. Sebelum merge `dev` → `main`

- [ ] Semua perubahan sudah dites di `dev` (browser manual — belum ada test otomatis untuk semua modul, lihat README § Known Gaps).
- [ ] `git status` bersih, tidak ada perubahan uncommitted yang ketinggalan.
- [ ] Cek `git log main..dev` — baca ulang daftar commit yang akan ikut ke production, pastikan tidak ada commit "coba-coba"/WIP yang kebawa.
- [ ] Kalau ada migration baru: sudah dicoba `php artisan migrate` di DB **dev/lokal** dulu (jangan langsung ke production tanpa dicoba). Kalau migration itu tipe rename/drop tabel (destruktif), baca ulang isinya sekali lagi — di project ini pernah ada migration rename besar-besaran (redesign payroll, lihat `planning/2026-07-12-redesign-payroll-simplifikasi-nama-dan-provenance.md`) yang butuh urutan eksekusi ketat.
- [ ] Kalau ada perubahan ke `phpunit.xml` — **pastikan** `DB_CONNECTION`/`DB_DATABASE` di situ tetap mengarah ke sqlite `:memory:`, BUKAN database asli. `RefreshDatabase` di banyak test akan `migrate:fresh` dan **menghapus semua data** kalau salah arah (sudah pernah kejadian, lihat README § Known Gaps).

## 3. Proses deploy (`git push origin main`)

- [ ] `git checkout main && git pull origin main`
- [ ] `git merge dev` (atau merge lewat Pull Request kalau mau direview dulu)
- [ ] `git push origin main` — titik ini yang memicu deploy ke server (Bitbucket Pipelines, SSH ke staging/production).
- [ ] Tunggu pipeline selesai, cek log deploy tidak error.

## 4. Setelah kode sampai di server (langkah manual, sesuai konfigurasi CI saat ini — deploy cuma transfer kode, bukan full provisioning)

Jalankan **di server**, urutan penting (migration dulu baru cache):

- [ ] `composer install --no-dev --optimize-autoloader` (kalau `composer.lock` berubah).
- [ ] `npm install && npm run build` (kalau ada perubahan asset — cek dulu apakah `public/build/assets/*` sudah lebih baru dari source `resources/`, lihat README § Known Gaps soal ini).
- [ ] `php artisan migrate --force` (`--force` wajib karena `APP_ENV=production`). **Backup database dulu** kalau migration-nya destruktif (rename/drop tabel) — sudah jadi kebiasaan di sesi-sesi sebelumnya, lihat `planning/next/2026-07-14-rekap-sesi-dan-lanjutan.md`.
- [ ] Jalankan seeder tambahan **kalau ada** (tidak semua seeder auto-run tiap deploy — cek dulu apakah perubahan yang di-deploy butuh seed manual, mis. `CustomPermissionsSeeder` kalau ada permission manual baru lewat `App\Enum\CustomPermissionEnum`):
  ```bash
  php artisan db:seed --class=CustomPermissionsSeeder
  ```
- [ ] `php artisan shield:generate --panel=admin --all --option=permissions` **kalau ada Resource/Page/Widget Filament baru atau yang di-rename** — permission Shield tidak otomatis sinkron sendiri, harus di-generate ulang manual tiap ada Resource baru.
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache` (kalau server pakai cache production — cek dulu konvensi server ini, jangan asumsikan; kalau `config:cache` aktif dan ada `.env` yang berubah, cache HARUS di-clear ulang juga).
- [ ] `php artisan queue:restart` (lihat §0 — wajib kalau ada Job yang berubah).

## 5. Verifikasi setelah deploy

- [ ] Buka `/admin` — panel bisa diakses, sidebar tidak error/kosong mendadak.
- [ ] Kalau deploy nambah Resource Filament baru: cek muncul di `/admin/roles` (`RoleForm.php`) dengan permission yang benar (bukti `shield:generate` sukses), dan **assign manual** permission-nya ke role yang perlu — permission baru default 0 assignment, tidak otomatis ke-assign ke siapa pun (pola yang sudah disepakati project ini, lihat `planning/next/2026-07-11-rekap-sesi-dan-lanjutan.md`).
- [ ] Kalau deploy nambah halaman portal baru (route + entry `config/portal-pages.php`): login sebagai user **tanpa** permission terkait → pastikan 403/nav item tidak muncul. Assign permission ke 1 role test → pastikan halaman & CRUD-nya jalan.
- [ ] Smoke test alur inti: login, buka dashboard portal, coba 1 alur transaksi yang paling sering dipakai (pengajuan cuti/izin/lembur — modul paling aktif dipakai saat ini, lihat `knowledge/markdown/analisis-payroll-2-redesign-struktur.md` §1.2).
- [ ] Cek log error server (`storage/logs/laravel.log`) beberapa menit setelah deploy — tidak ada exception baru yang berulang.

## 6. Kalau ada yang salah (rollback)

- [ ] Migration bermasalah: `php artisan migrate:rollback --step=1 --force` (cuma aman kalau migration terakhir belum ada data baru bergantung padanya — cek dulu isi `down()`-nya sebelum jalan).
- [ ] Kode bermasalah tapi migration aman: `git revert <commit>` di `main`, push ulang (lebih aman daripada `git reset --hard` + force-push ke `main` yang sudah dipakai orang lain).
- [ ] Selalu informasikan ke tim kalau baru saja rollback production — supaya tidak ada yang kerja di atas asumsi state yang sudah balik.
