# Daftar Akun untuk Tes Login `/admin` (2026-07-03)

Password semua akun di bawah ini: **`password`** (sudah direset massal untuk kebutuhan dev, lihat catatan di percakapan sebelumnya — cuma berlaku di database lokal).

## Super Admin (bypass semua company, akses global)

| Email | Nama |
|---|---|
| `superadmin@syifa.com` | Super Admin |
| `super_admin@mail.com` | Super Admin |

## Admin (full akses, tapi cuma company sendiri — hasil rename dari "Super Admin" per-company)

| Email | Nama | Company |
|---|---|---|
| `admin@rsu-syifa-medika-banjarbaru.com` | Admin | RSU Syifa Medika Banjarbaru |
| `super_admin_banjarbaru@mail.com` | Super Admin *(nama akun lama, role-nya sudah "Admin")* | RSU Syifa Medika Banjarbaru |
| `admin@rsu-syifa-medika-barabai.com` | Admin RSU Syifa Medika Barabai | RSU Syifa Medika Barabai |

Pakai akun-akun ini untuk verifikasi: login sebagai Admin RSU Banjarbaru harus **tidak bisa** lihat data company lain (LMS, Shift Schedule, dst yang sudah di-scope), tapi tetap bisa apa saja di company sendiri.

## Holding — Syifa Global Group (role beragam, cocok untuk tes approval flow & hierarki)

| Email | Nama | Role | Jabatan |
|---|---|---|---|
| `akbar@syifaglobalgroup.com` | Muhammad Fauzul Akbar | Direktur | — *(top-of-hierarchy, lihat catatan approval flow)* |
| `hcga.manager@syifaglobalgroup.com` | Muhammad Sandy Alfianoor, S.Kom | HC Manager | PJ HCGA Manager |
| `hcsupervisor@syifaglobalgroup.com` | Rickcy Herdian Zenda Putra, S.T | HC Supervisor | HC Supervisor |
| `itcoordinator@syifaglobalgroup.com` | Lutfi Salisa Setiawati | IT Supervisor | IT Supervisor |
| `itprogrammer@syifaglobalgroup.com` | Muhammad Khairil Amin, S.Kom | Karyawan / Staff | IT Programmer Staff |
| `ahyaghifari288@gmail.com` | Muhammad Ahya Ghifari | Karyawan / Staff | IT Programmer Staff |
| `financemanager@syifaglobalgroup.com` | Syepi Eka Suhartatiek, SE | Finance Manager | Finance Manager |
| `financeanalyst@syifaglobalgroup.com` | Safaris Lutfi Zakaria, S.E. | Karyawan / Staff | Finance Analyst Staff |
| `accountingmanager@syifaglobalgroup.com` | Noor Afini, SE | Accounting Manager | Accounting Manager |
| `recruiterbenefit@syifaglobalgroup.com` | M. Immawan Nuryahdi, S.Psi | Kasubag / Staff Kepegawaian | Recruiter & Benefit Staff |
| `learningdevelopment@syifaglobalgroup.com` | Muhammad Rizky Afriani, S.Psi | Learning & Development | Learning & Development Staff |
| `Yudith.melanny@gmail.com` | Yudith Melani Wael | Karyawan / Staff | Sekretaris Corporate *(belum ter-cover flow approval, lihat knowledge doc)* |

## RSU Syifa Medika Banjarbaru (contoh karyawan biasa, untuk tes company-scope & hierarki di rumah sakit)

| Email | Nama | Role |
|---|---|---|
| `pugawangi@gmail.com` | dr. Puga Sharaz Wangi, MARS | Direktur (RSU) |
| `putriastriana29@gmail.com` | Putri Astriana, S.E | Karyawan / Staff |
| `isti.yunita77@gmail.com` | Isti Yunita, SE | Karyawan / Staff |

## Catatan

- Company selain holding & RSU Banjarbaru/Barabai (Syifa Global Medica, Syifa Global Lestari Banjarbaru/Surabaya, Lerina Inn, Syifa Agro Natura) baru punya 1 user placeholder masing-masing (akun admin generik), belum ada karyawan sungguhan — tidak dimasukkan ke daftar ini karena kurang representatif untuk tes.
- Kalau butuh akun lain di luar daftar ini, semua password sama (`password`) — tinggal query `email` dari tabel `users` sesuai kebutuhan.
