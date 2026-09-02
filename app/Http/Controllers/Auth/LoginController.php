<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = $request->input('email');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'nip';

        if (!Auth::attempt([$field => $login, 'password' => $request->input('password')], $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password salah.'])
                ->onlyInput('email');
        }

        if (! Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Akun Anda sudah dinonaktifkan. Hubungi HC/Admin perusahaan.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        // Portal & panel Filament (/admin) sama-sama pakai guard 'web' yang sama, dan panel
        // admin tidak punya halaman login sendiri (AdminPanelProvider tidak set ->login()) -
        // jadi kalau user sempat nyasar ke /admin SEBELUM login, Laravel nyimpen /admin/... itu
        // sebagai "intended URL" di session, lalu redirect()->intended() di bawah bakal
        // melempar dia ke /admin lagi setelah login sungguhan - padahal belum tentu dia admin.
        // Abaikan intended kalau nyasarnya ke /admin, portal tetap jadi tujuan default; deep-link
        // ke halaman portal biasa (mis. buka /pelatihan/xxx sambil belum login) tetap dihormati.
        if (str_starts_with((string) $request->session()->get('url.intended'), url('/admin'))) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Kalau sesi ini lewat SSO, akhiri juga sesi di Keycloak (single logout) - kalau tidak,
        // klik "Masuk dengan SSO" berikutnya langsung login lagi tanpa prompt.
        $wasSso = $request->session()->get('sso');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($wasSso && config('services.keycloak.base_url')) {
            $base = rtrim((string) config('services.keycloak.base_url'), '/');
            $realm = config('services.keycloak.realms');

            return redirect($base . '/realms/' . $realm . '/protocol/openid-connect/logout?' . http_build_query([
                'client_id' => config('services.keycloak.client_id'),
                'post_logout_redirect_uri' => route('login'),
            ]));
        }

        return redirect()->route('login');
    }
}
