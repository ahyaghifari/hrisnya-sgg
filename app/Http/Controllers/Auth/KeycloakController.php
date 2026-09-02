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
        $nip = isset($raw['nip']) ? trim((string) $raw['nip']) : null;

        // DEBUG SSO - hapus setelah pemetaan user beres.
        \Log::info('SSO Keycloak callback', [
            'sub' => $kc->getId(),
            'email' => $kc->getEmail(),
            'nip_claim' => $nip,
            'raw' => $raw,
        ]);

        // Urutan match: keycloak_sub (paling stabil) -> nip -> email. Tidak auto-provision:
        // user HRIS membawa data karyawan/company/payroll yang tidak bisa dibuat dari klaim SSO.
        $user = User::where('keycloak_sub', $kc->getId())->first()
            ?? ($nip !== null && $nip !== '' ? User::where('nip', $nip)->first() : null)
            ?? ($kc->getEmail() ? User::where('email', $kc->getEmail())->first() : null);

        if (! $user) {
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
