<?php

namespace Tests\Feature\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Banner "Pasang aplikasi" (PWA) di Beranda + penangkap beforeinstallprompt di layout. Logikanya JS di browser,
 * jadi di sini cuma dipastikan markup & script-nya ikut terender. */
class PwaInstallBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_install_banner_and_layout_captures_install_prompt(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

        // Banner tersembunyi default (JS yang menampilkan), sebelum banner notifikasi.
        $this->assertStringContainsString('id="pwa-install-banner"', $html);
        $this->assertStringContainsString('pwaInstallApp()', $html);
        $this->assertLessThan(strpos($html, 'id="push-notif-banner"'), strpos($html, 'id="pwa-install-banner"'));

        // Layout menangkap beforeinstallprompt ke window supaya tidak hilang kalau Beranda belum terbuka.
        $this->assertStringContainsString('beforeinstallprompt', $html);
        $this->assertStringContainsString('__pwaInstallPrompt', $html);
    }
}
