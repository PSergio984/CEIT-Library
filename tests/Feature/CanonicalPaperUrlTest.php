<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\User;
use App\Support\CitationUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CanonicalPaperUrlTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function citation_url_helper_generates_canonical_paper_url(): void
    {
        $paper = AcademicPaper::factory()->create(['title' => 'Test Paper']);

        $this->assertSame('/academic-papers?paper='.$paper->id, CitationUrl::paper($paper->id));
        $this->assertSame('/academic-papers?paper=77', CitationUrl::paper(77));
    }

    #[Test]
    public function citation_url_helper_generates_policy_url(): void
    {
        $this->assertSame('/rule-and-regulation', CitationUrl::policy());
    }

    #[Test]
    public function citation_url_canonicalizes_legacy_urls(): void
    {
        $this->assertSame('/academic-papers?paper=77', CitationUrl::canonicalize('/academic-papers/77'));
        $this->assertSame('/academic-papers?paper=123', CitationUrl::canonicalize('/academic-papers/123'));
        $this->assertSame('/rule-and-regulation', CitationUrl::canonicalize('/policies'));
        $this->assertSame('/academic-papers?paper=77', CitationUrl::canonicalize('/academic-papers?paper=77'));
        $this->assertNull(CitationUrl::canonicalize(null));
    }

    #[Test]
    public function legacy_academic_paper_show_redirects_to_canonical(): void
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create(['title' => 'Redirect Test']);

        $this->actingAs($user);

        $response = $this->get('/academic-papers/'.$paper->id);

        $response->assertRedirect('/academic-papers?paper='.$paper->id);
        $this->assertSame(301, $response->status());
    }

    #[Test]
    public function legacy_academic_paper_show_with_missing_id_redirects_to_clean_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/academic-papers/999999');

        $response->assertRedirect('/academic-papers');
        $this->assertSame(301, $response->status());
    }

    #[Test]
    public function policies_redirects_to_rule_and_regulation(): void
    {
        $response = $this->get('/policies');

        $response->assertRedirect('/rule-and-regulation');
        $this->assertSame(301, $response->status());
    }
}
