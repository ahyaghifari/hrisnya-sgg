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

        $raw = $kc->getRaw();
        $nipClaim = isset($raw['nip']) ? trim((string) $raw['nip']) : null;
        // preferred_username tidak konsisten antar user Keycloak: sebagian berisi NIP
        // (mis. "05062026028"), sebagian berisi email. Claim 'email' sendiri belum di-map
        // ke userinfo jadi getEmail() sering null - pakai preferred_username buat dua-duanya.
        $username = isset($raw['preferred_username']) ? trim((string) $raw['preferred_username']) : null;
        $email = $kc->getEmail()
            ?: (($username && filter_var($username, FILTER_VALIDATE_EMAIL)) ? $username : null);

        // Urutan match: keycloak_sub -> claim 'nip' -> preferred_username sebagai NIP ->
        // email. Tidak auto-provision: user HRIS membawa data karyawan/company/payroll yang
        // tidak bisa dibuat dari klaim SSO.
        $user = User::where('keycloak_sub', $kc->getId())->first()
            ?? ($nipClaim !== null && $nipClaim !== '' ? User::where('nip', $nipClaim)->first() : null)
            ?? ($username !== null && $username !== '' ? User::where('nip', $username)->first() : null)
            ?? ($email ? User::where('email', $email)->first() : null);

        if (! $user) {
            // DEBUG SSO - hapus setelah pemetaan user beres.
            \Log::warning('SSO Keycloak: user tidak ketemu', [
                'sub' => $kc->getId(),
                'nip_claim' => $nipClaim,
                'preferred_username' => $username,
                'email' => $email,
                'nip_tanpa_scope' => $username
                    ? User::withoutGlobalScopes()->withTrashed()
                        ->where('nip', $username)->orWhere('email', (string) $email)
                        ->get(['id', 'nip', 'email', 'is_active', 'deleted_at'])->toArray()
                    : [],
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'Akun SSO belum terdaftar di HRIS. Hubungi HC/Admin.',
            ]);
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda sudah dinonaktifkan. Hubungi HC/Admin perusahaan.',
            ]);
        }

        if (! $user->keycloak_sub) {
            $user->forceFill(['keycloak_sub' => $kc->getId()])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->put('sso', true);

        // Samakan dengan LoginController::store() - jangan lempar balik ke /admin lewat
        // intended() kalau user cuma sempat nyasar ke sana sebelum login.
        if (str_starts_with((string) $request->session()->get('url.intended'), url('/admin'))) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route('dashboard'));
    }
}
