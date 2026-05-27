<?php
/**
 * Ads Settings Handler
 */

if (isset($_POST['update_ads_settings'])) {
    $adsEnabled = isset($_POST['ads_enabled']) ? 1 : 0;
    $adsMode = trim((string)($_POST['ads_injection_mode'] ?? 'smart'));
    if (!in_array($adsMode, ['smart', 'interval'], true)) {
        $adsMode = 'smart';
    }

    $adsInterval = (int)($_POST['ads_paragraph_interval'] ?? 4);
    $adsInterval = max(2, min(10, $adsInterval));

    $adsMaxUnits = (int)($_POST['ads_max_units_per_article'] ?? 2);
    $adsMaxUnits = max(1, min(6, $adsMaxUnits));

    $adsMinWords = (int)($_POST['ads_min_words_before_first_injection'] ?? 180);
    $adsMinWords = max(80, min(600, $adsMinWords));

    $adsMinArticleWords = (int)($_POST['ads_min_article_words'] ?? 420);
    $adsMinArticleWords = max(120, min(3000, $adsMinArticleWords));

    $adsBlockedTitleKeywords = trim((string)($_POST['ads_blocked_title_keywords'] ?? ''));
    if (mb_strlen($adsBlockedTitleKeywords) > 300) {
        $adsBlockedTitleKeywords = mb_substr($adsBlockedTitleKeywords, 0, 300);
    }

    $adsBlockedCategories = trim((string)($_POST['ads_blocked_categories'] ?? ''));
    if (mb_strlen($adsBlockedCategories) > 300) {
        $adsBlockedCategories = mb_substr($adsBlockedCategories, 0, 300);
    }

    $adsLabel = trim((string)($_POST['ads_label_text'] ?? 'Sponsored'));
    if ($adsLabel === '') {
        $adsLabel = 'Sponsored';
    }
    if (mb_strlen($adsLabel) > 40) {
        $adsLabel = mb_substr($adsLabel, 0, 40);
    }

    $adsHtmlCode = trim((string)($_POST['ads_html_code'] ?? ''));
    if ($adsHtmlCode === '') {
        $adsHtmlCode = '<div class="ad-unit-inner">Place your ad code here</div>';
    }
    if (mb_strlen($adsHtmlCode) > 12000) {
        $adsHtmlCode = mb_substr($adsHtmlCode, 0, 12000);
    }

    setSetting('ads_enabled', (string)$adsEnabled);
    setSetting('ads_injection_mode', $adsMode);
    setSetting('ads_paragraph_interval', (string)$adsInterval);
    setSetting('ads_max_units_per_article', (string)$adsMaxUnits);
    setSetting('ads_min_words_before_first_injection', (string)$adsMinWords);
    setSetting('ads_min_article_words', (string)$adsMinArticleWords);
    setSetting('ads_blocked_title_keywords', $adsBlockedTitleKeywords);
    setSetting('ads_blocked_categories', $adsBlockedCategories);
    setSetting('ads_label_text', $adsLabel);
    setSetting('ads_html_code', $adsHtmlCode);

    $_SESSION['flash_message'] = 'Ad placement controls updated successfully.';
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}
