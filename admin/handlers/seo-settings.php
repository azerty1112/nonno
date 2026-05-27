<?php
/**
 * SEO Settings Handler
 */

if (isset($_POST['update_daily_limit'])) {
    $newLimit = (int)($_POST['daily_limit'] ?? 5);
    $newLimit = max(1, min(200, $newLimit));
    setSetting('daily_limit', (string)$newLimit);
    $_SESSION['flash_message'] = 'Daily workflow generation limit updated.';
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['update_seo_settings'])) {
    $configuredSiteTitle = trim((string)($_POST['site_title'] ?? ''));
    $homeTitle = trim((string)($_POST['seo_home_title'] ?? ''));
    $homeDescription = trim((string)($_POST['seo_home_description'] ?? ''));
    $articleTitleSuffix = trim((string)($_POST['seo_article_title_suffix'] ?? ''));
    $defaultRobots = trim((string)($_POST['seo_default_robots'] ?? 'index,follow'));
    $defaultOgImage = trim((string)($_POST['seo_default_og_image'] ?? ''));
    $twitterSite = trim((string)($_POST['seo_twitter_site'] ?? ''));
    $imageAltSuffix = trim((string)($_POST['seo_image_alt_suffix'] ?? ''));
    $imageTitleSuffix = trim((string)($_POST['seo_image_title_suffix'] ?? ''));
    $seoAutoLinkRules = trim((string)($_POST['seo_auto_link_rules'] ?? ''));
    $seoAutoLinkAutoInternal = isset($_POST['seo_auto_link_auto_internal']) ? 1 : 0;
    $seoAutoLinkMaxPerArticle = (int)($_POST['seo_auto_link_max_per_article'] ?? 3);

    if (mb_strlen($configuredSiteTitle) > 80) {
        $configuredSiteTitle = mb_substr($configuredSiteTitle, 0, 80);
    }
    if (mb_strlen($homeTitle) > 120) {
        $homeTitle = mb_substr($homeTitle, 0, 120);
    }
    if (mb_strlen($homeDescription) > 160) {
        $homeDescription = mb_substr($homeDescription, 0, 160);
    }
    if (mb_strlen($articleTitleSuffix) > 80) {
        $articleTitleSuffix = mb_substr($articleTitleSuffix, 0, 80);
    }
    if (!in_array($defaultRobots, ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'], true)) {
        $defaultRobots = 'index,follow';
    }
    if (mb_strlen($defaultOgImage) > 500) {
        $defaultOgImage = mb_substr($defaultOgImage, 0, 500);
    }
    if ($twitterSite !== '' && mb_substr($twitterSite, 0, 1) !== '@') {
        $twitterSite = '@' . ltrim($twitterSite, '@');
    }
    if (mb_strlen($twitterSite) > 40) {
        $twitterSite = mb_substr($twitterSite, 0, 40);
    }
    if (mb_strlen($imageAltSuffix) > 80) {
        $imageAltSuffix = mb_substr($imageAltSuffix, 0, 80);
    }
    if (mb_strlen($imageTitleSuffix) > 80) {
        $imageTitleSuffix = mb_substr($imageTitleSuffix, 0, 80);
    }
    if (mb_strlen($seoAutoLinkRules) > 12000) {
        $seoAutoLinkRules = mb_substr($seoAutoLinkRules, 0, 12000);
    }
    $seoAutoLinkMaxPerArticle = max(1, min(10, $seoAutoLinkMaxPerArticle));

    setSetting('site_title', $configuredSiteTitle !== '' ? $configuredSiteTitle : SITE_TITLE);
    setSetting('seo_home_title', $homeTitle);
    setSetting('seo_home_description', $homeDescription);
    setSetting('seo_article_title_suffix', $articleTitleSuffix);
    setSetting('seo_default_robots', $defaultRobots);
    setSetting('seo_default_og_image', $defaultOgImage);
    setSetting('seo_twitter_site', $twitterSite);
    setSetting('seo_image_alt_suffix', $imageAltSuffix);
    setSetting('seo_image_title_suffix', $imageTitleSuffix);
    setSetting('seo_auto_link_rules', $seoAutoLinkRules);
    setSetting('seo_auto_link_auto_internal', (string)$seoAutoLinkAutoInternal);
    setSetting('seo_auto_link_max_per_article', (string)$seoAutoLinkMaxPerArticle);

    // translation settings
    $autoTranslateEnabled = isset($_POST['auto_translate_enabled']) ? 1 : 0;
    $autoTranslateTarget = trim((string)($_POST['auto_translate_target_language'] ?? ''));
    if (mb_strlen($autoTranslateTarget) > 5) {
        $autoTranslateTarget = mb_substr($autoTranslateTarget, 0, 5);
    }
    setSetting('auto_translate_enabled', (string)$autoTranslateEnabled);
    setSetting('auto_translate_target_language', $autoTranslateTarget);

    // regenerate static pages when SEO metadata changes
    exportStaticPages();

    $_SESSION['flash_message'] = 'SEO settings updated successfully.';
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['ping_sitemap'])) {
    $sitemap = getSiteBaseUrl() . '/sitemap.php';
    pingSearchEngines($sitemap);
    updateRobotsTxt();
    $_SESSION['flash_message'] = 'Search engines notified (sitemap pinged).';
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}
