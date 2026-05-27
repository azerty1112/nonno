<?php
/**
 * Auto Title Generation Handler
 */

if (isset($_POST['update_smart_niche_automation'])) {
    $nicheSlug = trim((string)($_POST['smart_active_niche'] ?? ''));
    $activeNicheSlug = trim((string)getSetting('active_niche', 'general'));
    $validNicheSlug = '';

    if ($nicheSlug !== '' && class_exists('App\\NicheManager')) {
        $candidateNiche = \App\NicheManager::getNicheBySlug($nicheSlug);
        if ($candidateNiche) {
            $validNicheSlug = (string)($candidateNiche['slug'] ?? '');
            $activeNicheSlug = $validNicheSlug;
            setSetting('active_niche', $validNicheSlug);
        }
    }

    $enabled = isset($_POST['smart_auto_ai_enabled']) ? 1 : 0;
    $intervalFrom = (int)($_POST['smart_auto_publish_interval_seconds_from'] ?? 1);
    $intervalTo = (int)($_POST['smart_auto_publish_interval_seconds_to'] ?? 10800);
    $intervalFrom = max(1, min(300000, $intervalFrom));
    $intervalTo = max(1, min(300000, $intervalTo));
    if ($intervalTo < $intervalFrom) {
        [$intervalFrom, $intervalTo] = [$intervalTo, $intervalFrom];
    }
    $interval = max(1, $intervalTo);

    $mode = trim((string)($_POST['smart_auto_title_mode'] ?? 'template'));
    if (!in_array($mode, ['template', 'list'], true)) {
        $mode = 'template';
    }

    $minYearOffset = (int)($_POST['smart_auto_title_min_year_offset'] ?? 0);
    $maxYearOffset = (int)($_POST['smart_auto_title_max_year_offset'] ?? 1);
    $minYearOffset = max(-1, min(2, $minYearOffset));
    $maxYearOffset = max(-1, min(3, $maxYearOffset));
    if ($maxYearOffset < $minYearOffset) {
        [$minYearOffset, $maxYearOffset] = [$maxYearOffset, $minYearOffset];
    }

    setSetting('auto_ai_enabled', (string)$enabled);
    setSetting('auto_publish_interval_seconds_from', (string)$intervalFrom);
    setSetting('auto_publish_interval_seconds_to', (string)$intervalTo);
    setSetting('auto_publish_interval_seconds', (string)$interval);

    $nichePrefix = 'niche.' . ($activeNicheSlug !== '' ? $activeNicheSlug : 'general') . '.';
    setSetting($nichePrefix . 'auto_title_mode', $mode);
    setSetting($nichePrefix . 'auto_title_min_year_offset', (string)$minYearOffset);
    setSetting($nichePrefix . 'auto_title_max_year_offset', (string)$maxYearOffset);
    setSetting('smart_source_prefill_niche', $activeNicheSlug);

    $smartMsg = 'Smart automation hub updated (niche + scheduler + title mode).';
    $smartFlashType = 'success';
    if ($nicheSlug !== '' && $validNicheSlug === '') {
        $smartMsg .= ' Niche not found, keeping previous active niche.';
        $smartFlashType = 'warning';
    }
    $_SESSION['flash_message'] = $smartMsg;
    $_SESSION['flash_type'] = $smartFlashType;
    header('Location: admin.php#auto-scheduler-section');
    exit;
}

if (isset($_POST['update_auto_title_settings'])) {
    $mode = trim((string)($_POST['auto_title_mode'] ?? 'template'));
    if (!in_array($mode, ['template', 'list'], true)) {
        $mode = 'template';
    }

    $minYearOffset = (int)($_POST['auto_title_min_year_offset'] ?? 0);
    $maxYearOffset = (int)($_POST['auto_title_max_year_offset'] ?? 1);
    $minYearOffset = max(-1, min(2, $minYearOffset));
    $maxYearOffset = max(-1, min(3, $maxYearOffset));
    if ($maxYearOffset < $minYearOffset) {
        [$minYearOffset, $maxYearOffset] = [$maxYearOffset, $minYearOffset];
    }

    $fields = [
        'auto_title_brands',
        'auto_title_models',
        'auto_title_modifiers',
        'auto_title_audiences',
        'auto_title_angles',
        'auto_title_templates',
        'auto_title_fixed_titles',
    ];

    $activeNicheSlug = trim((string)getSetting('active_niche', 'general'));
    $titlePrefix = 'niche.' . $activeNicheSlug . '.';
    setSetting($titlePrefix . 'auto_title_mode', $mode);
    setSetting($titlePrefix . 'auto_title_min_year_offset', (string)$minYearOffset);
    setSetting($titlePrefix . 'auto_title_max_year_offset', (string)$maxYearOffset);

    foreach ($fields as $fieldKey) {
        $raw = trim((string)($_POST[$fieldKey] ?? ''));
        if (mb_strlen($raw) > 10000) {
            $raw = mb_substr($raw, 0, 10000);
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line !== '') {
                $clean[] = $line;
            }
        }

        if ($fieldKey === 'auto_title_templates') {
            $allowedVars = ['{year}', '{brand}', '{model}', '{modifier}', '{angle}', '{audience}'];
            $validated = [];
            foreach ($clean as $tpl) {
                $hasVar = false;
                foreach ($allowedVars as $var) {
                    if (mb_strpos($tpl, $var) !== false) {
                        $hasVar = true;
                        break;
                    }
                }
                if ($hasVar) {
                    $validated[] = $tpl;
                }
            }
            $clean = $validated;
        }

        $value = implode("\n", array_values(array_unique($clean)));
        setSetting($titlePrefix . $fieldKey, $value);
    }

    $_SESSION['flash_message'] = 'Auto title generation controls updated.';
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['preview_auto_titles'])) {
    $samples = [];
    for ($i = 0; $i < 5; $i++) {
        $samples[] = generateAutoTitle();
    }
    $_SESSION['flash_message'] = 'Title preview: ' . implode(' | ', $samples);
    $_SESSION['flash_type'] = 'info';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['reset_auto_title_defaults'])) {
    $defaults = getAutoTitleDefaultSettings();
    $activeNicheSlug = trim((string)getSetting('active_niche', 'general'));
    $titlePrefix = 'niche.' . $activeNicheSlug . '.';
    foreach ($defaults as $k => $v) {
        setSetting($titlePrefix . $k, (string)$v);
    }
    $_SESSION['flash_message'] = 'Auto title controls reset to defaults.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: admin.php');
    exit;
}
