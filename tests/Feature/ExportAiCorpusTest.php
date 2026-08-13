<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use App\Models\TechnicalAdviser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExportAiCorpusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @unlink(storage_path('app/ai-corpus/catalog.json'));
        @unlink(storage_path('app/ai-corpus/policies.json'));
    }

    private function seedPaper(): AcademicPaper
    {
        $author1 = Author::factory()->create(['name' => 'Juan Dela Cruz']);
        $author2 = Author::factory()->create(['name' => 'Maria Santos']);
        $researchAdviser = ResearchAdviser::factory()->create(['name' => 'Engr. Jose Rizal']);
        $technicalAdviser = TechnicalAdviser::factory()->create(['name' => 'Engr. Andres Bonifacio']);
        $dean = Dean::factory()->create(['name' => 'Dr. Emilio Aguinaldo']);

        $paper = AcademicPaper::create([
            'title' => 'Design of a Smart Flood Monitoring System',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'research_adviser_id' => $researchAdviser->id,
            'technical_adviser_id' => $technicalAdviser->id,
            'department' => 'Electrical Engineering',
            'dean_id' => $dean->id,
        ]);

        $paper->authors()->attach([$author1->id, $author2->id]);

        return $paper;
    }

    private function readExport(string $file): array
    {
        $path = storage_path('app/ai-corpus/'.$file);

        $this->assertFileExists($path, "Expected exported file {$file} to exist");

        return json_decode(file_get_contents($path), true);
    }

    #[Test]
    public function it_exports_catalog_documents_with_locked_schema(): void
    {
        $paper = $this->seedPaper();

        $this->artisan('ai:export-corpus')->assertExitCode(0);

        $catalog = $this->readExport('catalog.json');

        $this->assertSame(1, $catalog['schema_version']);
        $this->assertSame('academic_papers', $catalog['source']);
        $this->assertSame($catalog['count'], count($catalog['documents']));
        $this->assertNotFalse(strtotime($catalog['generated_at']));

        $doc = collect($catalog['documents'])->firstWhere('id', 'paper-'.$paper->id);

        $this->assertNotNull($doc);
        $this->assertSame('catalog', $doc['corpus']);
        $this->assertSame($paper->catalog_code, $doc['metadata']['catalog_code']);
        $this->assertContains('Juan Dela Cruz', $doc['metadata']['authors']);
        $this->assertContains('Maria Santos', $doc['metadata']['authors']);
        $this->assertSame('Engr. Jose Rizal', $doc['metadata']['research_adviser']);
        $this->assertSame('Engr. Andres Bonifacio', $doc['metadata']['technical_adviser']);
        $this->assertSame('Dr. Emilio Aguinaldo', $doc['metadata']['dean']);
        $this->assertSame('/academic-papers/'.$paper->id, $doc['metadata']['url']);
        $this->assertIsInt($doc['metadata']['publication_year']);

        $this->assertStringStartsWith($paper->title.'. '.$paper->title.'. ', $doc['text']);
        $this->assertStringContainsString('authors: Juan Dela Cruz', $doc['text']);
        $this->assertStringContainsString('research_adviser: Engr. Jose Rizal', $doc['text']);
        $this->assertStringContainsString('department: Electrical Engineering', $doc['text']);
    }

    #[Test]
    public function it_exports_policy_documents_as_header_and_regulation_docs(): void
    {
        $header = RuleHeader::factory()->create(['title' => 'General Information', 'order' => 1]);
        $reg1 = RuleRegulation::factory()->create(['rule_header_id' => $header->id, 'content' => 'Students must present their school ID.']);
        $reg2 = RuleRegulation::factory()->create(['rule_header_id' => $header->id, 'content' => 'No food and drinks inside the library.']);

        $this->artisan('ai:export-corpus')->assertExitCode(0);

        $policies = $this->readExport('policies.json');

        $this->assertSame('rulebook', $policies['source']);
        $this->assertSame($policies['count'], count($policies['documents']));

        $headerDoc = collect($policies['documents'])->firstWhere('id', 'policy-h'.$header->id);
        $regDoc = collect($policies['documents'])->firstWhere('id', 'policy-h'.$header->id.'-r'.$reg1->id);

        $this->assertNotNull($headerDoc);
        $this->assertSame('header', $headerDoc['metadata']['policy_type']);
        $this->assertSame('policy', $headerDoc['corpus']);
        $this->assertSame('Section: General Information', $headerDoc['text']);

        $this->assertNotNull($regDoc);
        $this->assertSame('regulation', $regDoc['metadata']['policy_type']);
        $this->assertStringStartsWith('Section: General Information', $regDoc['text']);
        $this->assertStringContainsString('Students must present their school ID.', $regDoc['text']);

        $regDoc2 = collect($policies['documents'])->firstWhere('id', 'policy-h'.$header->id.'-r'.$reg2->id);
        $this->assertNotNull($regDoc2);
    }

    #[Test]
    public function it_never_serializes_inventory_or_user_pii(): void
    {
        $this->seedPaper();

        $this->artisan('ai:export-corpus')->assertExitCode(0);

        $catalog = $this->readExport('catalog.json');
        $policies = $this->readExport('policies.json');

        $encoded = json_encode(array_merge($catalog['documents'], $policies['documents']));

        $this->assertStringNotContainsString('copy_number', $encoded);
        $this->assertStringNotContainsString('status', $encoded);
        $this->assertStringNotContainsString('email', $encoded);
    }

    #[Test]
    public function it_drops_deleted_papers_from_the_export(): void
    {
        $paper = $this->seedPaper();

        $this->artisan('ai:export-corpus')->assertExitCode(0);

        $paper->forceDelete();

        $this->artisan('ai:export-corpus')->assertExitCode(0);

        $catalog = $this->readExport('catalog.json');

        $ids = collect($catalog['documents'])->pluck('id')->all();
        $this->assertNotContains('paper-'.$paper->id, $ids);
    }

    #[Test]
    public function it_strips_control_characters_and_caps_long_titles(): void
    {
        $paper = $this->seedPaper();
        $paper->update(['title' => "Weird\x00Title ".str_repeat('X', 600)]);

        $this->artisan('ai:export-corpus')->assertExitCode(0);

        $catalog = $this->readExport('catalog.json');
        $doc = collect($catalog['documents'])->firstWhere('id', 'paper-'.$paper->id);

        $this->assertStringNotContainsString("\x00", $doc['title']);
        $this->assertSame(500, mb_strlen($doc['title']));
    }

    #[Test]
    public function it_exports_only_the_requested_corpus(): void
    {
        $this->seedPaper();

        $this->artisan('ai:export-corpus', ['--corpus' => 'catalog'])->assertExitCode(0);

        $this->assertFileExists(storage_path('app/ai-corpus/catalog.json'));
        $this->assertFileDoesNotExist(storage_path('app/ai-corpus/policies.json'));
    }

    #[Test]
    public function it_exports_policies_with_the_policy_corpus_flag(): void
    {
        $header = RuleHeader::factory()->create(['title' => 'General Information', 'order' => 1]);
        RuleRegulation::factory()->create(['rule_header_id' => $header->id]);

        $this->artisan('ai:export-corpus', ['--corpus' => 'policy'])->assertExitCode(0);

        $this->assertFileExists(storage_path('app/ai-corpus/policies.json'));
        $this->assertFileDoesNotExist(storage_path('app/ai-corpus/catalog.json'));
    }

    #[Test]
    public function it_rejects_invalid_corpus_values_without_writing_files(): void
    {
        $this->seedPaper();

        $this->artisan('ai:export-corpus', ['--corpus' => 'bogus'])->assertExitCode(1);

        $this->assertFileDoesNotExist(storage_path('app/ai-corpus/catalog.json'));
        $this->assertFileDoesNotExist(storage_path('app/ai-corpus/policies.json'));
    }
}
