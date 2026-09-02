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
        // NIP karyawan: dari claim 'nip' kalau ada, kalau tidak dari preferred_username
        // (userinfo scope 'profile' HRIS mengisi salah satunya dengan NIP).
        $nip = $raw['nip'] ?? $raw['preferred_username'] ?? null;
        $nip = $nip !== null ? trim((string) $nip) : null;

        // Match: NIP dulu, keycloak_sub cuma cadangan. Tidak auto-provision: user HRIS
        // membawa data karyawan/company/payroll yang tidak bisa dibuat dari klaim SSO.
        $user = ($nip !== null && $nip !== '' ? User::where('nip', $nip)->first() : null)
            ?? User::where('keycloak_sub', $kc->getId())->first();

        if (! $user) {
            // DEBUG SSO - hapus kalau pemetaan sudah stabil.
            \Log::warning('SSO Keycloak: user tidak ketemu', [
                'sub' => $kc->getId(),
                'nip' => $nip,
                'raw' => $raw,
                'nip_tanpa_scope' => $nip
                    ? User::withoutGlobalScopes()->withTrashed()
                        ->where('nip', $nip)
                        ->get(['id', 'nip', 'is_active', 'deleted_at'])->toArray()
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
