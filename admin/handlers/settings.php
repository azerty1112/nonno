<?php
/**
 * Settings Management Handler
 */

if (isset($_POST['save_setting'])) {
    $settingKey = trim((string)($_POST['setting_key'] ?? ''));
    $settingValue = trim((string)($_POST['setting_value'] ?? ''));
    $originalKey = trim((string)($_POST['original_setting_key'] ?? ''));

    if (!preg_match('/^[a-z0-9_\-.]{2,80}$/i', $settingKey)) {
        $_SESSION['flash_message'] = 'Invalid setting key. Use letters, numbers, dots, dashes, and underscores only.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: admin.php');
        exit;
    }

    $protectedSettings = ['pipeline_defaults_v2_applied'];
    if ($originalKey !== '' && in_array($originalKey, $protectedSettings, true) && $originalKey !== $settingKey) {
        $_SESSION['flash_message'] = 'This system setting key cannot be renamed.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }

    if ($originalKey !== '' && $originalKey !== $settingKey) {
        $existsStmt = $pdo->prepare("SELECT key FROM settings WHERE key = ? LIMIT 1");
        $existsStmt->execute([$settingKey]);
        if ($existsStmt->fetchColumn()) {
            $_SESSION['flash_message'] = 'Cannot rename setting. Target key already exists.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php');
            exit;
        }

        $renameStmt = $pdo->prepare("UPDATE settings SET key = ?, value = ? WHERE key = ?");
        $renameStmt->execute([$settingKey, $settingValue, $originalKey]);
    } else {
        setSetting($settingKey, $settingValue);
    }

    $_SESSION['flash_message'] = "Setting '{$settingKey}' saved.";
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['delete_setting'])) {
    $settingKey = trim((string)($_POST['delete_setting'] ?? ''));
    $protectedSettings = ['pipeline_defaults_v2_applied'];
    if (in_array($settingKey, $protectedSettings, true)) {
        $_SESSION['flash_message'] = 'This system setting is protected and cannot be deleted.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM settings WHERE key = ?");
    $stmt->execute([$settingKey]);
    $_SESSION['flash_message'] = 'Setting removed.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['clear_page_visits'])) {
    $stmt = $pdo->prepare("DELETE FROM page_visits");
    $stmt->execute();
    $_SESSION['flash_message'] = 'All page visit statistics have been cleared.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['refresh_config_file'])) {
    $beforeFingerprint = getSetting('config_txt_fingerprint', '');
    $configFileResult = loadConfigFileIfChanged($pdo, __DIR__ . '/../../config.txt');
    $afterFingerprint = getSetting('config_txt_fingerprint', '');
    if ($afterFingerprint !== '' && $afterFingerprint !== $beforeFingerprint) {
        $_SESSION['flash_message'] = 'config.txt reloaded and settings applied.';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'No changes detected in config.txt or the file was not found.';
        $_SESSION['flash_type'] = 'info';
    }
    header('Location: admin.php#config-management');
    exit;
}
