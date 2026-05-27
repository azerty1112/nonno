<?php
/**
 * Scripts and Third-party Services Handler
 */

if (isset($_POST['update_scripts_settings'])) {
    $googleAnalyticsId = strtoupper(trim((string)($_POST['google_analytics_id'] ?? '')));
    $googleTagManagerId = strtoupper(trim((string)($_POST['google_tag_manager_id'] ?? '')));
    $googleSiteVerification = trim((string)($_POST['google_site_verification'] ?? ''));
    $bingSiteVerification = trim((string)($_POST['bing_site_verification'] ?? ''));
    $metaPixelId = preg_replace('/[^0-9]/', '', trim((string)($_POST['meta_pixel_id'] ?? '')));
    $customHeadScripts = trim((string)($_POST['custom_head_scripts'] ?? ''));
    $customBodyScripts = trim((string)($_POST['custom_body_scripts'] ?? ''));

    if ($googleAnalyticsId !== '' && !preg_match('/^(G-[A-Z0-9]+|GT-[A-Z0-9]+|UA-\d+-\d+|AW-\d+)$/', $googleAnalyticsId)) {
        $googleAnalyticsId = '';
    }
    if ($googleTagManagerId !== '' && !preg_match('/^GTM-[A-Z0-9]+$/', $googleTagManagerId)) {
        $googleTagManagerId = '';
    }
    if (mb_strlen($googleSiteVerification) > 255) {
        $googleSiteVerification = mb_substr($googleSiteVerification, 0, 255);
    }
    if (mb_strlen($bingSiteVerification) > 255) {
        $bingSiteVerification = mb_substr($bingSiteVerification, 0, 255);
    }
    if ($metaPixelId !== '' && mb_strlen($metaPixelId) > 30) {
        $metaPixelId = mb_substr($metaPixelId, 0, 30);
    }
    if (mb_strlen($customHeadScripts) > 20000) {
        $customHeadScripts = mb_substr($customHeadScripts, 0, 20000);
    }
    if (mb_strlen($customBodyScripts) > 20000) {
        $customBodyScripts = mb_substr($customBodyScripts, 0, 20000);
    }

    setSetting('google_analytics_id', $googleAnalyticsId);
    setSetting('google_tag_manager_id', $googleTagManagerId);
    setSetting('google_site_verification', $googleSiteVerification);
    setSetting('bing_site_verification', $bingSiteVerification);
    setSetting('meta_pixel_id', $metaPixelId);
    setSetting('custom_head_scripts', $customHeadScripts);
    setSetting('custom_body_scripts', $customBodyScripts);
    
    // optional ads.txt content (also persisted to file)
    $adsTxtContent = trim((string)($_POST['ads_txt_content'] ?? ''));
    if (mb_strlen($adsTxtContent) > 100000) {
        $adsTxtContent = mb_substr($adsTxtContent, 0, 100000);
    }
    setSetting('ads_txt', $adsTxtContent);
    @file_put_contents(__DIR__ . '/../../ads.txt', $adsTxtContent);
    
    // since sitemap or base url might be referenced elsewhere, refresh robots.txt too
    updateRobotsTxt();

    $_SESSION['flash_message'] = 'Scripts settings updated successfully.';
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}
