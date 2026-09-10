# Integrasi Keycloak SSO (OIDC)

Dokumen ini menjelaskan cara HRIS mengintegrasikan Keycloak, lalu cara memakai
pola yang sama di **project Laravel biasa** dengan **email** sebagai penghubung
antar-sistem (bukan NIP seperti di HRIS).

---

## 1. Ringkasan pola

- Protokol: **OpenID Connect** (Authorization Code Flow) via
  [Laravel Socialite](https://laravel.com/docs/socialite) + provider komunitas
  `socialiteproviders/keycloak`.
- SSO **opsional & berdampingan** dengan login lokal (email + password). Kalau
  env Keycloak kosong, tombol SSO mati, login lokal tetap jalan.
- **Tidak auto-provision**: user yang belum ada di DB aplikasi ditolak. Keycloak
  cuma dipakai untuk membuktikan identitas; data user tetap milik aplikasi.
- Penghubung akun:
  - HRIS: klaim `nip` (fallback `preferred_username`), kolom `keycloak_sub`
    sebagai cadangan.
  - **Project lain (dokumen ini): klaim `email`.**

Alur:

```
User → /auth/keycloak/redirect → Keycloak login → /auth/keycloak/callback
     → ambil klaim (email) → cari User by email → Auth::login() → dashboard
```

---

## 2. Yang dipakai di HRIS (referensi)

| Bagian | Lokasi |
|---|---|
| Paket | `composer.json`: `socialiteproviders/keycloak: ^5.3`, `laravel/socialite: ^5.29` |
| Registrasi provider | `app/Providers/AppServiceProvider.php` — listener `SocialiteWasCalled` |
| Config | `config/services.php` → key `keycloak` |
| Env | `.env.example` → `KEYCLOAK_*` |
| Route | `routes/keycloak.php`, di-load di `bootstrap/app.php` dengan middleware `web` |
| Controller | `app/Http/Controllers/Auth/KeycloakController.php` |
| Kolom cadangan | migrasi `2026_09_03_000000_add_keycloak_sub_to_users_table.php` (`users.keycloak_sub`) |
| Tombol | `resources/views/auth/login.blade.php` → `route('sso.redirect')` |
| Single logout | `app/Http/Controllers/Auth/LoginController.php@destroy` |

---

## 3. Setup di sisi Keycloak (admin console)

Lakukan ini sekali per aplikasi yang mau SSO.

1. **Realm**: pakai realm yang sama untuk semua aplikasi yang mau SSO satu sama
   lain (mis. `perusahaan`). SSO lintas-aplikasi hanya jalan kalau realmnya sama.
2. **Client baru**:
   - Client ID: mis. `app-absensi` (beda per aplikasi).
   - Client authentication: **On** (confidential) → menghasilkan *Client secret*
     di tab **Credentials**.
   - Standard flow: **On**. Direct access grants: Off.
   - Valid redirect URIs: `https://app-absensi.example.com/auth/keycloak/callback`
   - Valid post logout redirect URIs: `https://app-absensi.example.com/login`
   - Web origins: `+` (atau origin aplikasi).
3. **Pastikan `email` ada di token**:
   - Scope `email` default sudah memuat `email` + `email_verified`. Pastikan
     scope `email` berstatus **Default** di tab *Client scopes* client tsb.
   - Kalau butuh klaim custom (mis. `nip`), buat *User Attribute* + *Protocol
     mapper* tipe "User Attribute" → Token Claim Name `nip`, centang
     "Add to userinfo".
4. **User** di Keycloak harus punya email yang **sama persis** dengan
   `users.email` di aplikasi. Sebaiknya `Email verified = On`.

---

## 4. Implementasi di project Laravel biasa (match by email)

### 4.1 Install

```bash
composer require laravel/socialite socialiteproviders/keycloak
```

### 4.2 Registrasi provider

Laravel 11/12 tidak punya `EventServiceProvider` default. Daftarkan listener di
`AppServiceProvider::boot()`:

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;

public function boot(): void
{
    Event::listen(function (SocialiteWasCalled $event) {
        $event->extendSocialite('keycloak', \SocialiteProviders\Keycloak\Provider::class);
    });
}
```

> Laravel <11 dengan `EventServiceProvider`: tambahkan di array `$listen`:
> `SocialiteWasCalled::class => [KeycloakExtendSocialite::class]`.

### 4.3 Config

```php
// config/services.php
'keycloak' => [
    'client_id'     => env('KEYCLOAK_CLIENT_ID'),
    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
    'redirect'      => env('KEYCLOAK_REDIRECT_URI'),
    'base_url'      => env('KEYCLOAK_BASE_URL'),   // https://sso.example.com  (tanpa trailing slash)
    'realms'        => env('KEYCLOAK_REALM'),      // nama realm, mis. "perusahaan"
],
```

```dotenv
# .env
KEYCLOAK_BASE_URL=https://sso.example.com
KEYCLOAK_REALM=perusahaan
KEYCLOAK_CLIENT_ID=app-absensi
KEYCLOAK_CLIENT_SECRET=xxxxxxxx
KEYCLOAK_REDIRECT_URI="${APP_URL}/auth/keycloak/callback"
```

Kosongkan semua `KEYCLOAK_*` untuk mematikan SSO (tombol jadi non-fungsional,
login lokal tetap jalan).

### 4.4 Route

Buat `routes/keycloak.php`:

```php
<?php

use App\Http\Controllers\Auth\KeycloakController;
use Illuminate\Support\Facades\Route;

// Butuh session + CSRF state untuk alur OAuth → middleware 'web'.
// Di LUAR 'auth' supaya tamu bisa memulai login.
Route::get('/auth/keycloak/redirect', [KeycloakController::class, 'redirect'])->name('sso.redirect');
Route::get('/auth/keycloak/callback', [KeycloakController::class, 'callback'])->name('sso.callback');
```

Load di `bootstrap/app.php` (Laravel 11/12):

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    // ...
    then: function () {
        Route::middleware('web')->group(__DIR__.'/../routes/keycloak.php');
    },
)
```

> Laravel <11: `require __DIR__.'/keycloak.php';` di dalam grup `web` pada
> `RouteServiceProvider`.

### 4.5 Controller (match by email)

`app/Http/Controllers/Auth/KeycloakController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class KeycloakController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('keycloak')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $kc = Socialite::driver('keycloak')->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors(['email' => 'Login SSO gagal. Coba lagi.']);
        }

        // Penghubung antar-sistem: EMAIL. Normalisasi ke lowercase.
        $email = strtolower(trim((string) $kc->getEmail()));

        if ($email === '') {
            return redirect()->route('login')->withErrors([
                'email' => 'Akun SSO tidak mengirim email. Hubungi admin.',
            ]);
        }

        // Tidak auto-provision: user harus sudah ada di aplikasi ini.
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Akun SSO belum terdaftar di aplikasi ini. Hubungi admin.',
            ]);
        }

        // Kalau ada kolom status aktif:
        // if (! $user->is_active) { ... tolak ... }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->put('sso', true);

        return redirect()->intended(route('dashboard'));
    }
}
```

Catatan:
- `Socialite::driver('keycloak')->user()` sudah tukar `code` → token, verifikasi,
  dan panggil `userinfo`. Klaim tersedia lewat `$kc->getRaw()`.
- Match pakai `LOWER(email)` supaya beda kapitalisasi tidak bikin user tak
  ketemu. Kalau kolom email kamu sudah dijamin lowercase, `User::where('email', $email)`
  cukup.
- **Tidak perlu kolom `keycloak_sub`** kalau email dijamin unik & stabil. Kalau
  mau tahan terhadap ganti email, tambahkan kolom itu dan simpan `$kc->getId()`
  saat pertama login (lihat §6).

### 4.6 Tombol di halaman login

```blade
@if (config('services.keycloak.base_url'))
    <a href="{{ route('sso.redirect') }}" class="...">
        Masuk dengan SSO
    </a>
@endif
```

### 4.7 Logout (opsional: single logout)

Supaya klik "Masuk dengan SSO" berikutnya tidak langsung login lagi tanpa
prompt, akhiri juga sesi Keycloak:

```php
public function destroy(Request $request): RedirectResponse
{
    $wasSso = $request->session()->get('sso');

    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    if ($wasSso && config('services.keycloak.base_url')) {
        $base  = rtrim((string) config('services.keycloak.base_url'), '/');
        $realm = config('services.keycloak.realms');

        return redirect($base . '/realms/' . $realm . '/protocol/openid-connect/logout?' . http_build_query([
            'client_id'                => config('services.keycloak.client_id'),
            'post_logout_redirect_uri' => route('login'),
        ]));
    }

    return redirect()->route('login');
}
```

`post_logout_redirect_uri` harus terdaftar di *Valid post logout redirect URIs*
client (lihat §3).

---

## 5. Checklist deploy

- [ ] Client dibuat di realm yang benar, `confidential`, standard flow on.
- [ ] Redirect URI & post-logout URI persis sama dengan yang di `.env` (HTTPS,
      tanpa typo trailing slash).
- [ ] Scope `email` default-on untuk client → cek isi token di tab *Client
      scopes → Evaluate*.
- [ ] Email user Keycloak == `users.email` aplikasi (case-insensitive).
- [ ] `APP_URL` benar (dipakai menyusun `KEYCLOAK_REDIRECT_URI`).
- [ ] `SESSION_DOMAIN` / cookie benar kalau di balik proxy; `TrustProxies`
      aktif supaya `redirect()` pakai `https`.

---

## 6. Opsi: tahan terhadap ganti email

Email bisa berubah. Kalau itu risiko nyata:

```php
// migrasi
$table->string('keycloak_sub')->nullable()->unique()->after('email');
```

```php
// di callback(), setelah dapat $kc
$user = User::where('keycloak_sub', $kc->getId())->first()
    ?? User::whereRaw('LOWER(email) = ?', [$email])->first();

if ($user && ! $user->keycloak_sub) {
    $user->forceFill(['keycloak_sub' => $kc->getId()])->save();
}
```

`sub` (`$kc->getId()`) itu ID user Keycloak yang immutable — jadi jangkar utama,
email jadi jalan masuk pertama kali.

---

## 7. Troubleshooting

| Gejala | Penyebab umum |
|---|---|
| `Invalid redirect_uri` di Keycloak | URI di `.env` beda dengan yang terdaftar di client |
| `state` mismatch / halaman login loop | Session tidak persist — cek `SESSION_DRIVER`, cookie domain, `web` middleware di route callback |
| User selalu "belum terdaftar" | Email di Keycloak beda kapitalisasi / beda dengan DB; pakai `LOWER()` match |
| Token tidak ada `email` | Scope `email` tidak default-on untuk client, atau user Keycloak memang tidak punya email |
| `redirect()` menghasilkan `http://` di balik proxy | `TrustProxies` / `APP_URL` belum diset |
| Logout SSO error `invalid post logout redirect` | URI belum masuk *Valid post logout redirect URIs* |
