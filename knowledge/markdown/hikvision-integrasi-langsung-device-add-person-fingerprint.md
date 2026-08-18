# Hikvision: Integrasi Langsung ke Device (Add Person & Fingerprint) — 2026-08-17

Dokumen ini merangkum eksplorasi cara **push** data karyawan (bukan cuma baca presensi) dari HRIS ke device Hikvision — spesifik: nambah Person + fingerprint pakai NIP sebagai `employeeNo`. Beda topik dari `rencana-integrasi-hikvision-dan-pola-jadwal.md` (itu soal **baca** `access_logs`, sudah diputuskan lewat DB-direct). Dokumen ini soal arah sebaliknya: HRIS **menulis** ke device.

**Status**: baru riset/percobaan, belum ada kode dieksekusi. Ditulis biar bisa dilanjut dari kantor pas akses jaringan `10.10.x` (device target) sudah kebuka.

## 1. Kenapa Bukan Pakai SDK Folder `HIKSDK/`

Folder `HIKSDK/EN-HCNetSDKV6.1.9.48_build20230410_win64/` isinya SDK native C++/C# (HCNetSDK.dll) — Laravel/PHP gak bisa panggil langsung, butuh bridge service (C#) kalau mau pakai jalur ini. **Ternyata gak perlu** karena device support ISAPI (HTTP REST langsung) — Laravel bisa `Http::withDigestAuth()` tanpa bridge sama sekali.

SDK tetap berguna sebagai **referensi dokumentasi** (PDF di `HIKSDK/.../doc/`) karena command ISAPI yang dipakai SDK dan ISAPI native itu sama personanya — sudah diverifikasi field-nya cocok (lihat §3).

Demo siap pakai kalau nanti butuh jalur SDK juga ada: `HIKSDK/.../C# demo/8-ACS_Optimization_ALL/` — folder `CardManagement`, `FingerPrintManagement`, `FaceManagement`, `PlanTemplate`, `ControlDoor`, `GetACSEvent`, tiap satu punya `.sln` sendiri, sudah pakai `NET_DVR_STDXMLConfig`.

## 2. Topologi Jaringan (Kendala Saat Ini)

- Akses ke Hikvision sekarang lewat **WireGuard**, tapi cuma nyampe segment `172.x` (tempat **HikCentral** access control server).
- Device fisik target ada di segment **`10.10.x`** — beda VLAN, WireGuard `AllowedIPs` belum nyakup situ. **Ini blocker utama** buat lanjut dari luar kantor.
- **HikCentral OpenAPI/Artemis gak tersedia** (gak ada AppKey/AppSecret, module OpenAPI kemungkinan belum diaktifkan/di-license) — jadi jalur "push lewat HikCentral pusat ke semua device" gak bisa dipakai. Konsekuensi: kalau ada banyak device/lokasi, Person harus didaftar **per-device** langsung (bukan sekali lewat HikCentral).
- **Jangan** insert langsung ke DB HikCentral buat nambah Person/fingerprint — HikCentral push config ke device secara event-driven, insert manual ke tabel gak ke-propagate ke device fisik. (DB-direct cuma valid buat **baca** `access_logs`, sudah diputuskan sebelumnya.)

**Next step pas di kantor**: tes akses langsung ke IP device `10.10.x` (LAN lokal, gak lewat WireGuard) — kalau device dan admin PC di jaringan fisik sama, ini harusnya langsung reachable tanpa perlu ubah rute VPN apa pun.

## 3. ISAPI Sudah Dikonfirmasi Hidup di Device

Tes `http://<ip_device>/ISAPI/System/deviceInfo` sudah berhasil nyampe device (perlu login/auth — Digest, bukan Basic).

### Add Person — sudah cross-check ke dokumentasi resmi

Sumber: `HIKSDK/.../doc/Device Network SDK (Person-Based Access Control)_Developer Guide_V6.1.7.X_20230330.pdf`, section **E.167** (request definition) dan **F.165** (`JSON_UserInfo` field spec). Diverifikasi lewat `pdftotext -layout`, bukan cuma dari pengetahuan umum.

```bash
curl -v --digest -u admin:PASSWORD \
  -H "Content-Type: application/json" \
  -X POST http://<ip_device>/ISAPI/AccessControl/UserInfo/Record?format=json \
  -d '{
    "UserInfo": {
      "employeeNo": "<NIP>",
      "name": "<Nama Karyawan>",
      "userType": "normal",
      "Valid": {
        "enable": true,
        "beginTime": "2026-01-01T00:00:00",
        "endTime": "2037-12-31T23:59:59",
        "timeType": "local"
      },
      "doorRight": "1",
      "RightPlan": [
        { "doorNo": 1, "planTemplateNo": "1" }
      ]
    }
  }'
```

Catatan field (dari dokumentasi resmi, bukan tebakan):
- `employeeNo` — **required**, string, ini yang dipakai NIP.
- `userType` — **required**, cuma 3 value valid: `"normal"`, `"visitor"`, `"blackList"`.
- `Valid` — **required** (enable/beginTime/endTime).
- `doorRight` — **string** (bukan array), format `"1,3"` kalau mau multi pintu.
- `name` — optional (device tetap terima tanpa nama, tapi sebaiknya selalu diisi).
- Response sukses: `{"statusCode":1,"statusString":"OK","subStatusCode":"ok"}`.
- Endpoint ini **add-only** (kalau `employeeNo` udah ada, kemungkinan error duplikat) — buat update pakai `UserInfo/Modify` (PUT), buat hapus `UserInfo/Delete` (PUT, field `deleteUser: true`).

### Add Fingerprint (belum diverifikasi ke PDF, masih dari SDK header `HCNetSDK.h`)

- Command SDK: `NET_DVR_SET_FINGERPRINT` (2564), struct `NET_DVR_FINGERPRINT_RECORD`: `byCardNo`/`employeeNo` (link ke Person yang sudah dibuat di atas), `byFingerPrintID` (slot 1-10), `byFingerData` (template biner).
- Endpoint ISAPI kemungkinan setara di modul fingerprint (belum dicek section persisnya di PDF — **TODO** cross-check kayak §3 di atas sebelum eksekusi beneran).
- Prasyarat: Person (`employeeNo`) harus sudah terdaftar duluan via `UserInfo/Record` sebelum bisa attach fingerprint.
- Kalau HRIS yang capture langsung dari reader (bukan upload template siap pakai): SDK command `NET_DVR_CAPTURE_FINGERPRINT_INFO` (2504), butuh reader nyambung ke device/PC.

## 3b. Gap Ditemukan Setelah Tes Nyata (2026-08-18): HikCentral Tidak Auto-Sync

Add Person via ISAPI (§3) **sudah berhasil dites langsung ke device fisik** (dari kantor, jaringan `10.10.x` reachable). Tapi terkonfirmasi: Person yang ditambah lewat ISAPI **tidak otomatis muncul di HikCentral** — device dan HikCentral punya database Person terpisah, gak ada auto-sync event-driven dari device → HikCentral (cuma sebaliknya yang biasanya jalan: HikCentral → device). Konsekuensi: kalau push cuma lewat ISAPI ke device, `access_logs` (yang sumbernya dari HikCentral, bukan device langsung) kemungkinan gak kebentuk buat Person itu, dan Person gak muncul di HikCentral UI. Perlu sync/import manual di HikCentral setelah add via device.

**Opsi lanjutan yang sedang dipertimbangkan** (belum dieksekusi):
1. **Cek fitur "Import/Synchronize Person from Device" di HikCentral** — banyak produk access-control Hikvision punya tombol ini di device management (pull Person yang sudah ada di device masuk ke HikCentral), kemungkinan lebih simpel daripada reverse-engineer create-Person API HikCentral dari nol. **Belum dicek apakah ada & bisa dipanggil via API (bukan cuma klik manual UI)**.
2. **Reverse-engineer web UI HikCentral** (bukan ISAPI device) — tiru request yang dipakai form "Add Person" di HikCentral sendiri (lewat DevTools Network tab), supaya push cukup 1 kali ke HikCentral dan device di-propagate otomatis olehnya. Keuntungan: HikCentral reachable di `172.x` (WireGuard sudah nyambung, gak perlu akses `10.10.x`). Risiko: private/unsupported API, bisa berubah kalau HikCentral di-update, mungkin ada enkripsi RSA di login.
3. Manual: tetap double-input (device + HikCentral UI) — paling aman tapi paling banyak kerjaan manual, gak scalable buat banyak karyawan.

**Update 2026-08-18**: Opsi 1 sudah dicoba manual di HikCentral UI (fitur import/sync Person dari device) — **berhasil**, Person yang ditambah via ISAPI langsung ke device sekarang muncul di HikCentral setelah di-sync manual. Jadi flow lengkap yang terverifikasi: Add Person via ISAPI ke device → sync manual di HikCentral UI → Person kebentuk di HikCentral juga. Opsi 2 (reverse-engineer create-Person HikCentral) gak perlu dikejar lagi.

**Next buat otomatisasi**: rekam request yang dipanggil tombol sync/import itu (DevTools Network tab pas diklik) — kemungkinan besar ini juga panggilan API yang lebih murah ditiru daripada full create-Person, karena cuma "pull dari device yang sudah ada" bukan "create baru dari nol".

## 4. Rencana Lanjutan (Belum Dieksekusi)

1. **Buka akses jaringan ke `10.10.x`** — via kantor langsung (LAN lokal) atau minta admin jaringan tambah rute WireGuard. Blocker utama sebelum semua langkah berikut bisa dites di device asli.
2. Cross-check endpoint **add fingerprint** ke PDF (section yang sama `Person-Based Access Control Developer Guide`, cari `FingerPrint` command E.xx) — biar formatnya diverifikasi kayak Add Person, bukan cuma dari header SDK.
3. Test end-to-end: add Person (NIP) → add fingerprint → cek device bisa verifikasi scan orang itu.
4. Kalau sukses manual via curl, baru putuskan bentuk integrasi HRIS-nya:
   - Job/command Laravel yang manggil ISAPI device tiap ada karyawan baru + fingerprint di-capture (butuh alat capture terpisah, atau device sendiri yang punya reader buat enroll).
   - Pertimbangkan: banyak device/lokasi → perlu daftar IP device per lokasi + kredensial disimpan aman (bukan hardcode), loop request per device kalau karyawan perlu akses ke lebih dari 1 lokasi.
5. **Belum diputuskan**: siapa yang capture fingerprint fisik (device sendiri saat enrollment di lokasi, atau alat terpisah lalu template di-push dari HRIS) — ini nentuin apakah butuh fitur "capture" (`NET_DVR_CAPTURE_FINGERPRINT_INFO`) atau cukup "push template siap pakai".

## Referensi Terkait

- `rencana-integrasi-hikvision-dan-pola-jadwal.md` — arah integrasi baca presensi (`access_logs`, DB-direct), topik terpisah dari dokumen ini.
- `pengumpulan-data-absensi.md` — analisis awal sebelum integrasi Hikvision dikonfirmasi.
- `HIKSDK/EN-HCNetSDKV6.1.9.48_build20230410_win64/doc/Device Network SDK (Person-Based Access Control)_Developer Guide_V6.1.7.X_20230330.pdf` — sumber utama verifikasi field ISAPI.
- `HIKSDK/EN-HCNetSDKV6.1.9.48_build20230410_win64/incEn/HCNetSDK.h` — command constant & struct SDK (`NET_DVR_SET_FINGERPRINT`, dll).
- `HIKSDK/EN-HCNetSDKV6.1.9.48_build20230410_win64/C# demo/8-ACS_Optimization_ALL/` — demo siap pakai kalau jalur SDK/bridge C# jadi dibutuhkan.
