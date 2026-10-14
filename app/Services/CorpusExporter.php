<?php

namespace App\Services;

use App\Models\AcademicPaper;
use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use App\Support\CitationUrl;
use App\Support\CorpusNormalizer;
use Illuminate\Support\Facades\File;

class CorpusExporter
{
    public function exportCatalog(): array
    {
        return AcademicPaper::with(['authors', 'researchAdviser', 'technicalAdviser', 'dean'])
            ->get()
            ->map(function (AcademicPaper $paper) {
                $title = CorpusNormalizer::text($paper->title, 500);
                $authors = $paper->authors->pluck('name')
                    ->map(fn ($name) => CorpusNormalizer::personName($name))
                    ->all();

                $researchAdviser = $paper->researchAdviser ? CorpusNormalizer::personName($paper->researchAdviser->name) : null;
                $technicalAdviser = $paper->technicalAdviser ? CorpusNormalizer::personName($paper->technicalAdviser->name) : null;
                $dean = $paper->dean ? CorpusNormalizer::personName($paper->dean->name) : null;

                $segments = [$title.'.'];
                $segments[] = 'authors: '.implode('; ', $authors);
                $segments[] = 'research_adviser: '.($researchAdviser ?? '');
                $segments[] = 'technical_adviser: '.($technicalAdviser ?? '');
                $segments[] = 'dean: '.($dean ?? '');
                $segments[] = 'department: '.CorpusNormalizer::text($paper->department, 200);
                $segments[] = 'publication_year: '.$paper->publication_year;
                $segments[] = 'paper_type: '.CorpusNormalizer::paperType($paper->paper_type);
                $segments[] = 'catalog_code: '.CorpusNormalizer::text($paper->catalog_code, 200);

                return [
                    'id' => 'paper-'.$paper->id,
                    'corpus' => 'catalog',
                    'title' => $title,
                    'text' => implode(' ', $segments),
                    'metadata' => [
                        'catalog_code' => CorpusNormalizer::text($paper->catalog_code, 200),
                        'department' => CorpusNormalizer::text($paper->department, 200),
                        'publication_year' => (int) $paper->publication_year,
                        'paper_type' => CorpusNormalizer::paperType($paper->paper_type),
                        'authors' => $authors,
                        'research_adviser' => $researchAdviser,
                        'technical_adviser' => $technicalAdviser,
                        'dean' => $dean,
                        'url' => CitationUrl::paper($paper->id),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    public function exportPolicies(): array
    {
        $documents = [];

        // NOTE: RuleHeader::ruleRegulations() applies orderBy('order') but the
        // rule_regulations table has no `order` column (latent model bug — R10).
        // Load regulations with an independent query ordered by id instead.
        $regulationsByHeader = RuleRegulation::orderBy('id')->get()->groupBy('rule_header_id');

        $headers = RuleHeader::orderBy('id')->get();

        foreach ($headers as $header) {
            $headerTitle = CorpusNormalizer::text($header->title, 500);

            $documents[] = [
                'id' => 'policy-h'.$header->id,
                'corpus' => 'policy',
                'title' => $headerTitle,
                'text' => 'Section: '.$headerTitle,
                'metadata' => [
                    'policy_type' => 'header',
                    'header_id' => (int) $header->id,
                    'header_title' => $headerTitle,
                    'order' => (int) ($header->order ?? 0),
                    'url' => CitationUrl::policy(),
                ],
            ];

            foreach ($regulationsByHeader->get($header->id, collect()) as $regulation) {
                $content = CorpusNormalizer::text($regulation->content, 20000);

                $documents[] = [
                    'id' => 'policy-h'.$header->id.'-r'.$regulation->id,
                    'corpus' => 'policy',
                    'title' => $headerTitle,
                    'text' => 'Section: '.$headerTitle."\n".$content,
                    'metadata' => [
                        'policy_type' => 'regulation',
                        'header_id' => (int) $header->id,
                        'regulation_id' => (int) $regulation->id,
                        'header_title' => $headerTitle,
                        'url' => CitationUrl::policy(),
                    ],
                ];
            }
        }

        return $documents;
    }

    public function exportToDisk(string $path, array $which = ['catalog', 'policies']): array
    {
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $counts = [];

        if (in_array('catalog', $which, true)) {
            $counts['catalog'] = $this->writeEnvelope($path, 'catalog.json', 'academic_papers', $this->exportCatalog());
        }

        if (in_array('policies', $which, true)) {
            $counts['policies'] = $this->writeEnvelope($path, 'policies.json', 'rulebook', $this->exportPolicies());
        }

        return $counts;
    }

    private function writeEnvelope(string $path, string $filename, string $source, array $documents): int
    {
        $payload = [
            'source' => $source,
            'schema_version' => 1,
            'generated_at' => now()->utc()->toIso8601String(),
            'count' => count($documents),
            'documents' => $documents,
        ];
        File::put($path.'/'.$filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return count($documents);
    }
}
