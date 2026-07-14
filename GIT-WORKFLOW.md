# Panduan Git: `dev` vs `main`

Saat ini setiap push ke `main` langsung ke-deploy ke server. Supaya kerja development tidak sengaja ke-deploy duluan, pakai 2 branch:

- **`main`** — production. Cuma isinya kode yang sudah siap deploy. Push ke sini = langsung ke server.
- **`dev`** — tempat kerja sehari-hari. Push ke sini **aman**, tidak menyentuh server sama sekali.

## 0. Setup awal (sekali saja, di komputer manapun yang belum punya branch `dev`)

Cek dulu apakah `dev` sudah ada:

```bash
git fetch origin
git branch -a
```

**Kalau `remotes/origin/dev` belum ada** (baru pertama kali setup), buat dari `main`:

```bash
git checkout main
git pull origin main          # pastikan main lokal paling baru
git checkout -b dev            # buat branch dev dari main
git push -u origin dev         # kirim ke origin + branch lokal auto-tracking origin/dev
```

**Kalau `remotes/origin/dev` sudah ada** (dev sudah dibuat sebelumnya, ini cuma menyambungkan lokal ke branch yang sudah ada):

```bash
git checkout -b dev origin/dev
```

## 1. Kerja harian (development) — jangan ke `main`

```bash
git checkout dev
git pull origin dev             # tarik perubahan terbaru dulu sebelum mulai kerja
# ...ubah kode...
git add <file-yang-diubah>       # hindari `git add -A`/`.` biar tidak kebawa file yang tidak diinginkan
git commit -m "pesan commit yang jelas"
git push origin dev
```

Push ke `dev` **tidak memicu deploy** — aman dicoba-coba, aman push berkali-kali sepanjang hari.

## 2. Kalau sudah siap deploy ke production

Setelah `dev` dites dan dirasa aman, gabungkan ke `main`:

```bash
git checkout main
git pull origin main             # pastikan main lokal sinkron dulu
git merge dev                    # gabungkan semua perubahan dev ke main
git push origin main             # ini yang men-trigger deploy ke server
```

Kalau mau lebih hati-hati (review dulu sebelum production), buat Pull Request `dev` → `main` di GitHub alih-alih merge langsung dari terminal — baru di-merge dari halaman PR-nya kalau sudah oke.

**Setelah merge & deploy**, sinkronkan lagi `dev` supaya tidak ketinggalan (biar `dev` juga punya riwayat commit yang sama dengan `main`):

```bash
git checkout dev
git merge main
git push origin dev
```

## 3. Narik (pull) dari komputer lain yang sudah ada clone yang sama

**Clone baru** (komputer belum pernah clone repo ini):

```bash
git clone https://github.com/ahyaghifari/hrisnya-sgg.git
cd hrisnya-sgg
git checkout -b dev origin/dev     # sambungkan branch lokal dev ke origin/dev
```

**Komputer yang sudah punya clone**, mau ambil update terbaru:

```bash
git fetch origin                   # ambil info branch terbaru dari origin (belum ubah file lokal)

git checkout dev
git pull origin dev                 # update dev

git checkout main
git pull origin main                # update main
```

## Cheat sheet cepat

| Mau ngapain | Command |
|---|---|
| Lihat branch aktif sekarang | `git branch` (tanda `*`) |
| Pindah ke dev | `git checkout dev` |
| Pindah ke main | `git checkout main` |
| Simpan kerjaan ke dev (aman, tidak deploy) | `git add ... && git commit -m "..." && git push origin dev` |
| Deploy ke production | `git checkout main && git merge dev && git push origin main` |
| Ambil update terbaru tanpa pindah branch | `git fetch origin` |
| Update branch yang sedang aktif | `git pull origin <nama-branch>` |

## Aturan penting

- **Jangan pernah kerja langsung di `main`.** Selalu `git checkout dev` dulu sebelum mulai ngoding.
- **Jangan `git push origin main`** kecuali memang mau deploy ke production saat itu juga.
- Kalau ragu branch mana yang aktif, cek dulu dengan `git branch` sebelum commit/push apapun.
- `git merge` di atas mengasumsikan histori linear tanpa konflik. Kalau ada conflict pas merge, Git akan kasih tahu file mana yang bentrok — selesaikan manual (edit file, hapus marker `<<<<<<<`/`=======`/`>>>>>>>`), lalu `git add` file itu dan `git commit` untuk menyelesaikan merge-nya.
