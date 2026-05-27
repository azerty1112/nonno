<?php
/**
 * Articles Management Handler
 */

if (isset($_POST['bulk_update_articles'])) {
    $bulkAction = trim((string)($_POST['bulk_article_action'] ?? ''));
    $selectedIds = array_map('intval', $_POST['article_ids'] ?? []);
    $selectedIds = array_values(array_filter(array_unique($selectedIds), function ($id) {
        return $id > 0;
    }));

    if (!$selectedIds) {
        $_SESSION['flash_message'] = 'Please select at least one article.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));

    if ($bulkAction === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM articles WHERE id IN ($placeholders)");
        $stmt->execute($selectedIds);
        $_SESSION['flash_message'] = 'Selected articles deleted.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }

    if ($bulkAction === 'set_category') {
        $bulkCategory = trim((string)($_POST['bulk_category'] ?? ''));
        $params = array_merge([$bulkCategory], $selectedIds);
        $stmt = $pdo->prepare("UPDATE articles SET category = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);
        $_SESSION['flash_message'] = 'Category updated for selected articles.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    $_SESSION['flash_message'] = 'Invalid bulk action selected.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['delete_article'])) {
    $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_article']]);
    $_SESSION['flash_message'] = 'Article deleted.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['save_article'])) {
    $articleId = (int)($_POST['article_id'] ?? 0);
    $title = trim((string)($_POST['article_title'] ?? ''));
    $slugInput = trim((string)($_POST['article_slug'] ?? ''));
    $slug = $slugInput !== '' ? slugify($slugInput) : slugify($title);
    $category = trim((string)($_POST['article_category'] ?? ''));
    $excerpt = trim((string)($_POST['article_excerpt'] ?? ''));
    $image = trim((string)($_POST['article_image'] ?? ''));
    $image2 = trim((string)($_POST['article_image2'] ?? ''));
    $content = trim((string)($_POST['article_content'] ?? ''));
    $translatedTitle = trim((string)($_POST['article_translated_title'] ?? ''));
    $translatedContent = trim((string)($_POST['article_translated_content'] ?? ''));

    if ($articleId <= 0 || $title === '' || $content === '' || $slug === '') {
        $_SESSION['flash_message'] = 'Article update failed. Title, slug, and content are required.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: admin.php');
        exit;
    }

    $duplicateStmt = $pdo->prepare("SELECT id FROM articles WHERE (title = :title OR slug = :slug) AND id != :id LIMIT 1");
    $duplicateStmt->execute([
        'title' => $title,
        'slug' => $slug,
        'id' => $articleId,
    ]);
    $duplicate = $duplicateStmt->fetch(PDO::FETCH_ASSOC);
    if ($duplicate) {
        $_SESSION['flash_message'] = 'Article update failed. Title or slug already exists.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: admin.php');
        exit;
    }

    $autoTranslate = getSettingInt('auto_translate_enabled', 0, 0, 1) === 1;
    $targetLang = trim((string)getSetting('auto_translate_target_language', ''));
    $origLanguage = '';
    if ($autoTranslate && $targetLang) {
        if ($translatedTitle === '') {
            $translatedTitle = translateText($title, $targetLang);
        }
        if ($translatedContent === '') {
            $translatedContent = translateText($content, $targetLang);
        }
        $origLanguage = $targetLang;
    }

    $stmt = $pdo->prepare("UPDATE articles SET title = ?, slug = ?, category = ?, excerpt = ?, image = ?, image2 = ?, content = ?, translated_title = ?, translated_content = ?, orig_language = ? WHERE id = ?");
    $stmt->execute([$title, $slug, $category, $excerpt, $image, $image2, $content, $translatedTitle ?: null, $translatedContent ?: null, $origLanguage, $articleId]);

    // regenerate auto-tags if none exist
    $existingTags = getArticleTags($articleId);
    if (empty($existingTags)) {
        $autoTags = generateAutoTags($title, $content);
        foreach ($autoTags as $tag) {
            addTagToArticle($articleId, $tag);
        }
    }

    // refresh export artifacts after manual update
    writeArticleExportFiles($articleId, $slug, [
        'id' => $articleId,
        'title' => $title,
        'slug' => $slug,
        'content' => $content,
        'excerpt' => $excerpt,
        'image' => $image ?: null,
        'image2' => $image2 ?: null,
        'translated_title' => $translatedTitle ?: null,
        'translated_content' => $translatedContent ?: null,
        'published_at' => date('c'),
    ]);

    $_SESSION['flash_message'] = 'Article updated successfully.';
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['add_titles'])) {
    $rawTitles = array_map('trim', explode("\n", $_POST['titles'] ?? ''));
    $titles = array_values(array_unique(array_filter($rawTitles, function ($t) {
        return mb_strlen((string)$t) >= 5;
    })));
    $generated = 0;

    foreach ($titles as $title) {
        if (!articleExists($title)) {
            $data = generateArticle($title);
            if (saveArticle($title, $data)) {
                $generated++;
            }
        }
    }

    $_SESSION['flash_message'] = "Generated {$generated} new article(s).";
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['generate_demo_pack'])) {
    $demoTitles = [
        '2026 Porsche Taycan Turbo GT Track Review',
        'Best Hybrid SUVs for Families in 2026',
        'Mercedes-AMG C63 Daily Driving Impressions',
        'How Fast Charging Changed EV Road Trips',
        'Budget Performance Cars Worth Buying This Year',
    ];
    $generated = 0;
    foreach ($demoTitles as $title) {
        if (!articleExists($title)) {
            $data = generateArticle($title);
            if (saveArticle($title, $data)) {
                $generated++;
            }
        }
    }

    $_SESSION['flash_message'] = "Demo content pack generated: {$generated} new article(s).";
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}
