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
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password salah.'])
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
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
