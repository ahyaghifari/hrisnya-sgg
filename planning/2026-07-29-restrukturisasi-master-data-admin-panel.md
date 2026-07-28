# Restrukturisasi Resource Master Data (Filament Admin Panel)

## Konteks & Tujuan

Admin panel (`/admin`) cuma boleh diakses role **Super Admin** dan **Admin**. Bedanya:
- **Super Admin**: lintas company - lihat/filter semua perusahaan, field/kolom/filter "Perusahaan" muncul.
- **Admin**: dibatasi company sendiri - field/kolom/filter "Perusahaan" disembunyikan total, company_id otomatis ke-isi company Admin sendiri (baik saat create maupun edit, walau field-nya hidden).

Resource-resource lama (form + table + pages terpisah, banyak duplikasi kode cek role di tiap resource) sedang direstrukturisasi satu-per-satu ke pola baru: `BaseResource`/`BaseCompanyResource` + form-table di-embed langsung (tanpa file Schemas/Tables terpisah) + 1 halaman modal (`ManageRecords`, bukan List+Create+Edit terpisah).

User contoh awal (`BaseResource.php`, `BaseRumahSakitResource.php`, `RumahSakitResource.php` — sempat ditaruh di root project buat referensi, bisa dihapus kalau sudah tidak dipakai) berasal dari project RS lain, dipakai sebagai pola acuan lalu diadaptasi ke konsep company_id di project ini.

## Yang SUDAH selesai

### Infrastruktur dasar
- **`app/Filament/Resources/BaseResource.php`** — abstract base semua resource restrukturisasi:
  - `user()`, `isSuperAdmin()` (cek role 'Super Admin'), `companyId()`
  - `canAccess()` — gate resource cuma buat role Super Admin & Admin
- **`app/Filament/Resources/BaseCompanyResource.php`** — extends BaseResource, buat resource yang datanya merujuk `company_id`:
  - `getEloquentQuery()` — Super Admin lihat semua, Admin discope ke company sendiri
  - `companyFormField()` — Select company_id, `->default()` ke company Admin (tetap ke-dehidrasi walau `->visible(false)`), visible cuma Super Admin
  - `companyTableColumn()` — kolom `company.name`, visible cuma Super Admin
  - `companyTableFilter()` — SelectFilter company_id, visible cuma Super Admin

### Resource yang sudah dikonversi (Master group, urutan nav sengaja Kelas di atas Pangkat)
1. **CompaniesResource** (`Master/Companies`) — row-nya SENDIRI company, jadi tidak pakai BaseCompanyResource (row bukan "milik" company, tapi company itu sendiri). Relation manager `JobEvaluationsRelationManager` DIHAPUS (butuh halaman Edit/View, konflik sama simple-modal) - dihapus filenya total, bukan cuma dilepas dari `getRelations()`.
2. **DepartmentsResource** (`Master/Departments`, level 1 org_structure) — `BaseCompanyResource`. Guard hapus: tidak boleh kalau masih ada divisi (`divisions()->exists()`). Kolom "Jumlah Divisi" (`withCount('divisions')`).
3. **DivisionsResource** (`Master/Divisions`, level 2) — filter berjenjang Perusahaan→Departemen (Departemen di-scope ke company yang dipilih). Guard hapus: tidak boleh kalau masih ada sub divisi. Kolom "Jumlah Sub Divisi".
4. **SubDivisionsResource** (`Master/SubDivisions`, level 3, level terdalam org_structure - TIDAK ada level 4) — filter berjenjang Perusahaan→Departemen→Divisi (Departemen di sini VIRTUAL, cuma alat bantu mempersempit opsi Divisi, `->dehydrated(false)`). Guard hapus: tidak boleh kalau masih ada jabatan (`jobPositions()->exists()`) - relasi terdekat sub divisi bukan org_structure lagi tapi `JobPositions.org_structure_id`. Kolom "Jumlah Jabatan".
5. **KelasResource** (`Master/Kelas`) — induk dari Pangkat (Pangkat py rentang `start_kelas_id..max_kelas_id`, bukan 1 kelas tetap), makanya navigationSort digeser ke ATAS Pangkat. Field `level` (urutan kelas) SEBELUMNYA ada di model tapi hilang dari form - ditambahkan. Kolom "Jumlah Pangkat" (pangkat yang rentangnya MENCAKUP kelas ini - method baru `Kelas::pangkatsInRange()`, bukan FK langsung jadi tidak bisa `withCount`) dan "Jumlah Karyawan" (`UserProfiles.kelas_id` langsung, `withCount('userProfiles')` - relasi baru `Kelas::userProfiles()`).
6. **PangkatsResource** (`Master/Pangkats`) — field `start_kelas_id`/`max_kelas_id` SEBELUMNYA ada di model tapi hilang dari form juga - ditambahkan (Select, opsi Kelas di-scope ke company sama). Kolom "Jumlah Posisi" (`job_positions_count`) & "Jumlah Karyawan" (`user_profiles_count`), keduanya relasi FK langsung jadi `withCount` biasa. Guard hapus diperluas: cek jobPositions DAN userProfiles (sebelumnya cuma jobPositions).

### Pola konsisten yang dipakai di semua resource di atas
- Tanpa `Section` di form — input langsung.
- Tanpa `BulkActionGroup`/`DeleteBulkAction` — hapus cuma per-baris (`DeleteAction`), sesuai instruksi eksplisit user.
- `getModelLabel()` di-override ke Bahasa Indonesia — modal heading "Buat/Ubah/Hapus :label" otomatis benar (translasi ID `filament-actions` sudah ter-publish di `lang/vendor/filament-actions/id/`, cuma `create.php` yang di-override custom, `edit.php`/`delete.php` pakai bawaan package).
- Guard hapus pola: `DeleteAction->visible(fn ($record) => static::canDelete($record))->before(...)` — visible() buat sembunyikan tombol, before()+`$action->cancel()` buat jaga-jaga race condition, pola diambil dari `EditQuestionBanks.php` yang sudah ada duluan di codebase.
- Filter cascading (Perusahaan→Departemen→Divisi dst) HARUS digabung dalam **1** `Filter::make('nama')->schema([...])`, BUKAN beberapa `SelectFilter` terpisah — `$get()` lintas-field cuma bisa baca sibling yang satu container/schema yang sama. 2 `SelectFilter` terpisah TIDAK berbagi container, `$get()` lintas-filter bikin crash `"Typed property ...$container must not be accessed before initialization"`.
- Setiap resource: cek `getRelations()` (ada relation manager atau tidak - kalau ada dan aktif dipakai, JANGAN asal hapus, tanya user dulu), cek field yang ada di model tapi hilang dari form (sudah 2x ketemu kasusnya: `level` di Kelas, `start_kelas_id`/`max_kelas_id` di Pangkat), dan cek relasi child yang masuk akal buat kolom "Jumlah X".

## Gotcha teknis yang ditemukan (penting buat resource selanjutnya)

1. **`SelectFilter` TIDAK punya method `->live()`** — beda dari form `Select`. Filter tabel sudah otomatis reaktif, jangan panggil `.live()` di atasnya (`BadMethodCallException`).
2. **`Get` di Filament v4 pindah namespace** — `Filament\Schemas\Components\Utilities\Get`, BUKAN `Filament\Forms\Get` (lama/v3). Salah namespace → `BindingResolutionException: Target class [Filament\Forms\Get] does not exist`.
3. **`$get()` lintas-filter butuh 1 container** — lihat poin "Filter cascading" di atas. Solusinya: `Filter::make()->schema([...banyak field...])->query(function (Builder $query, array $data) {...})`, bukan beberapa `SelectFilter` berdiri sendiri.
4. **Field hidden TETAP ke-dehidrasi** (submit value) secara default di Filament, KECUALI eksplisit `->dehydrated(false)`. Ini yang dipakai buat `companyFormField()` (Admin: field disembunyikan tapi `->default()`-nya tetap kesimpan) - JANGAN kaget/JANGAN "diperbaiki" jadi terlihat, ini pola yang disengaja.
5. **`withCount()` cuma bisa buat relasi FK asli.** Kalau hubungan "termasuk"/"dalam rentang" (seperti Kelas↔Pangkat, dibandingkan lewat kolom `level`, bukan FK langsung), pakai `TextColumn::make(...)->getStateUsing(fn ($record) => ...)` (N+1 query per baris, tapi master data baris dikit jadi tidak masalah).

## Yang BELUM selesai / keputusan yang ditunda

- **`Master/JobPositions` (Jabatan)** — DITUNDA. Ternyata bukan master data datar, tapi resource approval-workflow kompleks: Wizard 3 step (Struktur Organisasi cascading 4 level, Job Evaluation, Work Load Analysis), status draft/requested/approved/rejected/completed yang mengunci field, riwayat approval (slideover History), tracking kuota (Kuota/Terisi/Tersedia), relation manager `UserAssignmentsRelationManager` (assign karyawan ke jabatan - FITUR AKTIF, beda dari relation manager Companies yang bisa ditunda). Kolom `name` di tabel-nya ke-label salah "Nama Pangkat" (harusnya "Nama Jabatan") - belum diperbaiki. Kolom "Unit" (`orgStructure.name`) generik, bisa nunjuk Departemen/Divisi/Sub Divisi tergantung sedalam apa jabatan ditempatkan.
- **Ada 2 resource "Jabatan" duplikat** — `Master/JobPositions/JobPositionResource.php` (yang di atas, di grup Master) DAN `ManPower/JobPositions/JobPositionResource.php` (di cluster "Man Power Planning", `admin/man-power-planing/man-power/job-positions`). Sama-sama target model `JobPositions::class`, sama-sama label "Jabatan". User pilih fokus ke yang Master dulu, yang ManPower dibiarkan apa adanya - BELUM diputuskan apakah nanti mau dihapus/digabung/dibiarkan permanen sebagai 2 resource beda konteks.
- **Resource Master lain yang BELUM disentuh sama sekali** (masih pola lama: `extends Resource` biasa, form/table/pages terpisah, cek role manual pakai `PermissionHelper::hasGlobalRole()` di tiap file) — kandidat lanjutan kalau mau diteruskan pola yang sama:
  - `Golongans` (kemungkinan konsep mirip Kelas/Pangkat - BELUM dicek relasinya)
  - `EmployeeTypes`
  - `Religions`
  - `Banks`
  - `LeaaveTypes` (perhatikan: nama foldernya sendiri ada typo "Leaave", cek dulu apa perlu dibenerin atau biarkan biar tidak break referensi lain)
  - `PermissionTypes`
  - `OvertimeTypes`
  - `TaskServices`
  - `Holidays`
  - `PositionCompetencies`
  - `AttendanceDevices`
  - `Competency`

  Sebelum konversi resource-resource ini, cek dulu: (a) apakah company-scoped (`BaseCompanyResource`) atau global (`BaseResource` polos), (b) ada `getRelations()` aktif atau tidak, (c) field yang ada di model tapi hilang dari form, (d) relasi child yang masuk akal buat kolom "Jumlah X".

## File referensi yang mungkin perlu dibersihkan

- `RumahSakitResource.php`, `BaseResource.php` (versi lama punya user), `BaseRumahSakitResource.php` — masih ada di ROOT project (bukan di `app/`), dipakai sebagai contoh referensi. Boleh dihapus kalau sudah tidak dibutuhkan lagi sebagai acuan (tidak ter-autoload karena namespace-nya tidak cocok sama lokasi filenya).
