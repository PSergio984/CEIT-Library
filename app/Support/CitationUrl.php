<?php

namespace App\Support;

class CitationUrl
{
    public static function paper(int $id): string
    {
        return route('academic-paper.index', ['paper' => $id], false);
    }

    public static function policy(): string
    {
        return route('rules-and-regulations.index', [], false);
    }

    /**
     * Rewrite legacy corpus URLs to the current canonical route.
     * Keeps a stale sidecar index functional without a 301 hop.
     */
    public static function canonicalize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        if ($url === '/policies') {
            return self::policy();
        }

        if (preg_match('#^/academic-papers/(\d+)$#', $url, $m)) {
            return self::paper((int) $m[1]);
        }

        return $url;
    }
}
