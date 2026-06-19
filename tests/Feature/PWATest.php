<?php

namespace Tests\Feature;

use App\Livewire\QrScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PWATest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_pwa_manifest_route_is_accessible()
    {
        $response = $this->get('/manifest.webmanifest');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/manifest+json');
    }

    /** @test */
    public function test_service_worker_route_is_accessible()
    {
        $response = $this->get('/sw.js');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/javascript');
    }

    /** @test */
    public function test_app_layout_contains_pwa_meta_tags()
    {
        $response = $this->get('/');
        
        $response->assertStatus(200);
        $response->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);
        $response->assertSee('<meta name="theme-color" content="#0046ad">', false);
        $response->assertSee('navigator.serviceWorker.register(\'/sw.js\')', false);
    }

    /** @test */
    public function test_app_layout_contains_app_badging_logic()
    {
        $response = $this->get('/');
        
        $response->assertSee('navigator.clearAppBadge()', false);
    }

    /** @test */
    public function test_app_layout_contains_install_banner_component()
    {
        $response = $this->get('/');
        
        $response->assertSee('Install CEIT Lib', false);
        $response->assertSee('beforeinstallprompt', false);
    }

    /** @test */
    public function test_qr_scanner_contains_offline_detection_logic()
    {
        Livewire::test(QrScanner::class)
            ->set('isScanning', true)
            ->assertSee('System Offline', false)
            ->assertSee('window.addEventListener(\'offline\'', false);
    }
}
