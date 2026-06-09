<?php

namespace Tests\Feature;

use Tests\TestCase;

class SitemapTest extends TestCase
{
    /**
     * Test that sitemap.xml returns a successful XML response.
     */
    public function test_sitemap_returns_successful_xml_response(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');

        $content = $response->getContent();
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<urlset', $content);
        $this->assertStringContainsString('<loc>'.url('').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.url('/login').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.url('/register').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.url('/forgot-password').'</loc>', $content);
    }

    /**
     * Test that public/robots.txt points to the relative sitemap.xml.
     */
    public function test_robots_txt_contains_relative_sitemap(): void
    {
        $robotsPath = public_path('robots.txt');
        $this->assertFileExists($robotsPath);

        $content = file_get_contents($robotsPath);
        $this->assertStringContainsString('Sitemap: /sitemap.xml', $content);
    }
}
