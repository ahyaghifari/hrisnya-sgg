# Migration tertunda (TIDAK dijalankan `php artisan migrate`)

Folder ini sengaja di luar `database/migrations`, jadi tidak ikut `migrate`.

## 2026_10_28_100000_drop_name_nip_company_id_from_users_table.php

Mencabut `users.name`, `users.nip`, `users.company_id` (sumber kini `employees`). Pindahkan ke
`database/migrations/` **hanya setelah**:

1. Rilis transisi (penulisan ke employees + kolom users sebagai cermin) stabil di produksi beberapa hari.
2. `php artisan identity:backfill` selesai dan `php artisan identity:verify` bersih (nol selisih).
3. Backup database dibuat.

Setelah dipindah dan di-deploy: `php artisan migrate` lalu `php artisan queue:restart` (proses yang hidup
lama menyimpan hasil deteksi skema). Kode beradaptasi otomatis (`User::legacyColumns()` mendeteksi skema).

`up()` menolak berjalan bila ada user tanpa baris `employees` atau nilai name/nip/company_id yang berbeda.
`down()` memulihkan kolom dari `employees` (sudah diuji pada SQLite: data kembali terisi), jadi pencabutan
ini bisa dibatalkan dengan `php artisan migrate:rollback --step=1`.

Audit perubahan nama/NIP/perusahaan SUDAH dipindah ke `Employee` (kunci `{user_id}:name|nip|company_id` dan modul
`data_karyawan` sama dengan catatan lama, jadi riwayat di halaman Data Diri tetap satu aliran) dan berjalan sejak
rilis transisi, sehingga tidak ada langkah audit tambahan saat kolom dicabut. `User` kini hanya mengaudit
`email` dan `is_active`.
