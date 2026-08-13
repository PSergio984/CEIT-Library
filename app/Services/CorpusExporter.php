<?php

namespace App\Services;

use App\Models\AcademicPaper;
use App\Models\RuleHeader;
use Illuminate\Support\Facades\File;

class CorpusExporter
{
    public function exportCatalog(): array
    {
        return AcademicPaper::with(['authors', 'researchAdviser', 'technicalAdviser', 'dean'])
            ->get()
            ->map(function (AcademicPaper $paper) {
                $title = $this->sanitize($paper->title, 500);
                $authors = $paper->authors->pluck('name')
                    ->map(fn ($name) => $this->sanitize($name, 200))
                    ->all();

                $researchAdviser = $paper->researchAdviser ? $this->sanitize($paper->researchAdviser->name, 200) : null;
                $technicalAdviser = $paper->technicalAdviser ? $this->sanitize($paper->technicalAdviser->name, 200) : null;
                $dean = $paper->dean ? $this->sanitize($paper->dean->name, 200) : null;

                $segments = [$title.'. ', $title.'. '];
                $segments[] = 'authors: '.implode('; ', $authors);
                $segments[] = 'research_adviser: '.($researchAdviser ?? '');
                $segments[] = 'technical_adviser: '.($technicalAdviser ?? '');
                $segments[] = 'dean: '.($dean ?? '');
                $segments[] = 'department: '.$this->sanitize($paper->department, 200);
                $segments[] = 'publication_year: '.$paper->publication_year;
                $segments[] = 'paper_type: '.$this->sanitize($paper->paper_type, 200);
                $segments[] = 'catalog_code: '.$this->sanitize($paper->catalog_code, 200);

                return [
                    'id' => 'paper-'.$paper->id,
                    'corpus' => 'catalog',
                    'title' => $title,
                    'text' => implode(' ', $segments),
                    'metadata' => [
                        'catalog_code' => $this->sanitize($paper->catalog_code, 200),
                        'department' => $this->sanitize($paper->department, 200),
                        'publication_year' => (int) $paper->publication_year,
                        'paper_type' => $this->sanitize($paper->paper_type, 200),
                        'authors' => $authors,
                        'research_adviser' => $researchAdviser,
                        'technical_adviser' => $technicalAdviser,
                        'dean' => $dean,
                        'url' => '/academic-papers/'.$paper->id,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    public function exportPolicies(): array
    {
        $documents = [];

        $headers = RuleHeader::with(['ruleRegulations' => function ($query) {
            $query->orderBy('id');
        }])->orderBy('id')->get();

        foreach ($headers as $header) {
            $headerTitle = $this->sanitize($header->title, 500);

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
                    'url' => '/policies',
                ],
            ];

            foreach ($header->ruleRegulations as $regulation) {
                $content = $this->sanitize($regulation->content, 20000);

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
                        'url' => '/policies',
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
            $catalog = $this->exportCatalog();
            $payload = [
                'source' => 'academic_papers',
                'schema_version' => 1,
                'generated_at' => now()->utc()->toIso8601String(),
                'count' => count($catalog),
                'documents' => $catalog,
            ];
            File::put($path.'/catalog.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $counts['catalog'] = count($catalog);
        }

        if (in_array('policies', $which, true)) {
            $policies = $this->exportPolicies();
            $payload = [
                'source' => 'rulebook',
                'schema_version' => 1,
                'generated_at' => now()->utc()->toIso8601String(),
                'count' => count($policies),
                'documents' => $policies,
            ];
            File::put($path.'/policies.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $counts['policies'] = count($policies);
        }

        return $counts;
    }

    private function sanitize(?string $value, int $maxLen): string
    {
        $value = $value ?? '';

        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = trim($value);

        if (mb_strlen($value) > $maxLen) {
            $value = mb_substr($value, 0, $maxLen);
        }

        return $value;
    }
}
