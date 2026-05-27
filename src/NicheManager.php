<?php

namespace App;

class NicheManager
{
    protected const VALID_SOURCE_TYPES = ['rss', 'web'];

    public static function listNiches(): array
    {
        $pdo = \db_connect();
        $stmt = $pdo->prepare("SELECT id, slug, name, description FROM niches ORDER BY id");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function getNicheBySlug(string $slug): ?array
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $pdo = \db_connect();
        $stmt = $pdo->prepare("SELECT id, slug, name, description FROM niches WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function getNicheById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = \db_connect();
        $stmt = $pdo->prepare("SELECT id, slug, name, description FROM niches WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function createNiche(string $slug, string $name, string $description = ''): int
    {
        $slug = self::normalizeSlug($slug);
        $name = trim($name);
        if ($slug === '' || $name === '') {
            return 0;
        }

        $pdo = \db_connect();
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO niches (slug, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$slug, $name, trim($description)]);
        $id = (int)$pdo->lastInsertId();
        if ($id > 0) {
            return $id;
        }

        $existing = self::getNicheBySlug($slug);
        return $existing['id'] ?? 0;
    }

    public static function updateNiche(int $nicheId, string $name, string $description = ''): bool
    {
        if ($nicheId <= 0 || trim($name) === '') {
            return false;
        }

        $pdo = \db_connect();
        $stmt = $pdo->prepare("UPDATE niches SET name = ?, description = ? WHERE id = ?");
        return $stmt->execute([trim($name), trim($description), $nicheId]);
    }

    public static function deleteNiche(int $nicheId): bool
    {
        if ($nicheId <= 0) {
            return false;
        }

        $pdo = \db_connect();
        $stmt = $pdo->prepare("DELETE FROM niches WHERE id = ?");
        return $stmt->execute([$nicheId]);
    }

    public static function addSource(int $nicheId, string $type, string $url): bool
    {
        if ($nicheId <= 0) {
            return false;
        }

        $type = self::normalizeSourceType($type);
        $url = self::normalizeUrl($url);
        if ($type === '' || $url === '') {
            return false;
        }

        $pdo = \db_connect();
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO niche_sources (niche_id, type, url) VALUES (?, ?, ?)");
        return $stmt->execute([$nicheId, $type, $url]);
    }

    public static function removeSource(int $nicheId, string $type, string $url): bool
    {
        if ($nicheId <= 0) {
            return false;
        }

        $type = self::normalizeSourceType($type);
        $url = trim($url);
        if ($type === '' || $url === '') {
            return false;
        }

        $pdo = \db_connect();
        $stmt = $pdo->prepare("DELETE FROM niche_sources WHERE niche_id = ? AND type = ? AND url = ?");
        return $stmt->execute([$nicheId, $type, $url]);
    }

    public static function getSourcesForNiche(int $nicheId, string $type = ''): array
    {
        $pdo = \db_connect();
        if ($type === '') {
            $stmt = $pdo->prepare("SELECT type, url FROM niche_sources WHERE niche_id = ? ORDER BY id");
            $stmt->execute([$nicheId]);
        } else {
            $type = self::normalizeSourceType($type);
            if ($type === '') {
                return [];
            }
            $stmt = $pdo->prepare("SELECT type, url FROM niche_sources WHERE niche_id = ? AND type = ? ORDER BY id");
            $stmt->execute([$nicheId, $type]);
        }

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function getNicheSources(int $nicheId): array
    {
        $sources = self::getSourcesForNiche($nicheId);
        $result = ['rss' => [], 'web' => []];
        foreach ($sources as $source) {
            if (!isset($source['type'], $source['url'])) {
                continue;
            }
            if (!in_array($source['type'], self::VALID_SOURCE_TYPES, true)) {
                continue;
            }
            $result[$source['type']][] = $source['url'];
        }
        return $result;
    }

    public static function replaceSources(int $nicheId, string $type, array $urls): bool
    {
        if ($nicheId <= 0) {
            return false;
        }

        $type = self::normalizeSourceType($type);
        if ($type === '') {
            return false;
        }

        $pdo = \db_connect();
        try {
            $pdo->beginTransaction();
            $deleteStmt = $pdo->prepare("DELETE FROM niche_sources WHERE niche_id = ? AND type = ?");
            $deleteStmt->execute([$nicheId, $type]);

            $insertStmt = $pdo->prepare("INSERT OR IGNORE INTO niche_sources (niche_id, type, url) VALUES (?, ?, ?)");
            foreach ($urls as $url) {
                $url = self::normalizeUrl((string)$url);
                if ($url === '') {
                    continue;
                }
                $insertStmt->execute([$nicheId, $type, $url]);
            }

            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    public static function seedDefaults(): void
    {
        $defaults = [
            'general' => [
                'name' => 'General Automotive',
                'description' => 'General car news and reviews.',
                'rss' => [
                    'https://www.caranddriver.com/rss/all.xml',
                    'https://www.autoblog.com/rss.xml',
                    'https://www.motortrend.com/feeds/all/'
                ],
                'web' => [
                    'https://www.autoblog.com/news/',
                    'https://www.caranddriver.com/news/'
                ]
            ]
        ];

        foreach ($defaults as $slug => $cfg) {
            $id = self::createNiche($slug, $cfg['name'], $cfg['description']);
            foreach ($cfg['rss'] as $r) {
                self::addSource($id, 'rss', $r);
            }
            foreach ($cfg['web'] as $w) {
                self::addSource($id, 'web', $w);
            }
        }
    }

    protected static function normalizeSlug(string $slug): string
    {
        $slug = trim(strtolower($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    protected static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        return $url;
    }

    protected static function normalizeSourceType(string $type): string
    {
        $type = trim(strtolower($type));
        return in_array($type, self::VALID_SOURCE_TYPES, true) ? $type : '';
    }
}
