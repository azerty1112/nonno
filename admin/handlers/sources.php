<?php
/**
 * Sources Management Handler (RSS & Web Sources)
 */

if (isset($_POST['update_rss'])) {
    $rssId = (int)($_POST['rss_id'] ?? 0);
    $rssUrl = trim((string)($_POST['rss_url'] ?? ''));
    if ($rssId <= 0 || !filter_var($rssUrl, FILTER_VALIDATE_URL)) {
        $_SESSION['flash_message'] = 'Invalid RSS source update request.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: admin.php');
        exit;
    }

    $duplicateStmt = $pdo->prepare("SELECT id FROM rss_sources WHERE url = ? AND id != ? LIMIT 1");
    $duplicateStmt->execute([$rssUrl, $rssId]);
    if ($duplicateStmt->fetchColumn()) {
        $_SESSION['flash_message'] = 'RSS URL already exists.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }

    $stmt = $pdo->prepare("UPDATE rss_sources SET url = ? WHERE id = ?");
    $stmt->execute([$rssUrl, $rssId]);
    $_SESSION['flash_message'] = 'RSS source updated.';
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['delete_rss'])) {
    $stmt = $pdo->prepare("DELETE FROM rss_sources WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_rss']]);
    $_SESSION['flash_message'] = 'RSS source removed.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['update_web'])) {
    $webId = (int)($_POST['web_id'] ?? 0);
    $webUrl = trim((string)($_POST['web_url'] ?? ''));
    if ($webId <= 0 || !filter_var($webUrl, FILTER_VALIDATE_URL)) {
        $_SESSION['flash_message'] = 'Invalid website source update request.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: admin.php');
        exit;
    }

    $duplicateStmt = $pdo->prepare("SELECT id FROM web_sources WHERE url = ? AND id != ? LIMIT 1");
    $duplicateStmt->execute([$webUrl, $webId]);
    if ($duplicateStmt->fetchColumn()) {
        $_SESSION['flash_message'] = 'Website URL already exists.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }

    $stmt = $pdo->prepare("UPDATE web_sources SET url = ? WHERE id = ?");
    $stmt->execute([$webUrl, $webId]);
    $_SESSION['flash_message'] = 'Website source updated.';
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['delete_web'])) {
    $stmt = $pdo->prepare("DELETE FROM web_sources WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_web']]);
    $_SESSION['flash_message'] = 'Normal website source removed.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['add_rss'])) {
    $singleUrl = trim($_POST['rss_url'] ?? '');
    $bulkInput = trim($_POST['rss_urls'] ?? '');
    $bulkUrls = $bulkInput === '' ? [] : preg_split('/\r\n|\r|\n/', $bulkInput);

    $rawUrls = [];
    if ($singleUrl !== '') {
        $rawUrls[] = $singleUrl;
    }
    foreach ($bulkUrls as $rawUrl) {
        $rawUrl = trim((string)$rawUrl);
        if ($rawUrl !== '') {
            $rawUrls[] = $rawUrl;
        }
    }

    $rawUrls = array_values(array_unique($rawUrls));
    if (!$rawUrls) {
        $_SESSION['flash_message'] = 'Please enter at least one RSS/XML URL.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: admin.php');
        exit;
    }

    $targetNicheId = 0;
    if (class_exists('App\\NicheManager')) {
        $activeNicheSlug = trim((string)getSetting('active_niche', 'general'));
        $targetNiche = \App\NicheManager::getNicheBySlug($activeNicheSlug);
        if ($targetNiche) {
            $targetNicheId = (int)($targetNiche['id'] ?? 0);
        }
    }

    $inserted = 0;
    $linkedToNiche = 0;
    $invalid = 0;
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO rss_sources (url) VALUES (?)");
    foreach ($rawUrls as $url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $invalid++;
            continue;
        }

        $stmt->execute([$url]);
        if ($stmt->rowCount() > 0) {
            $inserted++;
        }

        if ($targetNicheId > 0 && class_exists('App\\NicheManager')) {
            $existsStmt = $pdo->prepare("SELECT id FROM niche_sources WHERE niche_id = ? AND type = 'rss' AND url = ? LIMIT 1");
            $existsStmt->execute([$targetNicheId, $url]);
            if (!$existsStmt->fetchColumn()) {
                \App\NicheManager::addSource($targetNicheId, 'rss', $url);
                $linkedToNiche++;
            }
        }
    }

    $ignored = count($rawUrls) - $inserted - $invalid;
    if ($inserted > 0) {
        $_SESSION['flash_message'] = "Added {$inserted} RSS source(s)."
            . ($ignored > 0 ? " {$ignored} duplicate(s) skipped." : '')
            . ($invalid > 0 ? " {$invalid} invalid link(s) skipped." : '')
            . ($linkedToNiche > 0 ? " Linked {$linkedToNiche} source(s) to niche workflow." : '');
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = $invalid > 0
            ? "No RSS source was added. {$invalid} invalid link(s) detected."
            : 'No RSS source was added (all links already exist).';
        $_SESSION['flash_type'] = 'warning';
    }

    header('Location: admin.php');
    exit;
}

if (isset($_POST['add_web'])) {
    $singleUrl = trim($_POST['web_url'] ?? '');
    $bulkInput = trim($_POST['web_urls'] ?? '');
    $bulkUrls = $bulkInput === '' ? [] : preg_split('/\r\n|\r|\n/', $bulkInput);

    $rawUrls = [];
    if ($singleUrl !== '') {
        $rawUrls[] = $singleUrl;
    }
    foreach ($bulkUrls as $rawUrl) {
        $rawUrl = trim((string)$rawUrl);
        if ($rawUrl !== '') {
            $rawUrls[] = $rawUrl;
        }
    }

    $rawUrls = array_values(array_unique($rawUrls));
    if (!$rawUrls) {
        $_SESSION['flash_message'] = 'Please enter at least one normal website URL.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: admin.php');
        exit;
    }

    $targetNicheId = 0;
    if (class_exists('App\\NicheManager')) {
        $activeNicheSlug = trim((string)getSetting('active_niche', 'general'));
        $targetNiche = \App\NicheManager::getNicheBySlug($activeNicheSlug);
        if ($targetNiche) {
            $targetNicheId = (int)($targetNiche['id'] ?? 0);
        }
    }

    $inserted = 0;
    $linkedToNiche = 0;
    $invalid = 0;
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO web_sources (url) VALUES (?)");
    foreach ($rawUrls as $url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $invalid++;
            continue;
        }

        $stmt->execute([$url]);
        if ($stmt->rowCount() > 0) {
            $inserted++;
        }

        if ($targetNicheId > 0 && class_exists('App\\NicheManager')) {
            $existsStmt = $pdo->prepare("SELECT id FROM niche_sources WHERE niche_id = ? AND type = 'web' AND url = ? LIMIT 1");
            $existsStmt->execute([$targetNicheId, $url]);
            if (!$existsStmt->fetchColumn()) {
                \App\NicheManager::addSource($targetNicheId, 'web', $url);
                $linkedToNiche++;
            }
        }
    }

    $ignored = count($rawUrls) - $inserted - $invalid;
    if ($inserted > 0) {
        $_SESSION['flash_message'] = "Added {$inserted} normal website source(s)."
            . ($ignored > 0 ? " {$ignored} duplicate(s) skipped." : '')
            . ($invalid > 0 ? " {$invalid} invalid link(s) skipped." : '')
            . ($linkedToNiche > 0 ? " Linked {$linkedToNiche} source(s) to niche workflow." : '');
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = $invalid > 0
            ? "No normal website source was added. {$invalid} invalid link(s) detected."
            : 'No normal website source was added (all links already exist).';
        $_SESSION['flash_type'] = 'warning';
    }

    header('Location: admin.php');
    exit;
}
