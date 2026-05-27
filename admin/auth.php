<?php
/**
 * Admin Authentication Handler
 * Handles login/logout functionality and session management
 */

require_once dirname(__DIR__) . '/functions.php';

if (!function_exists('endsWith')) {
    function endsWith($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

$siteTitle = getSiteTitle();
$maxAttempts = 5;
$lockSeconds = 60;
$now = time();

$_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0);
$_SESSION['login_lock_until'] = (int)($_SESSION['login_lock_until'] ?? 0);

$isLocked = $_SESSION['login_lock_until'] > $now;
$remainingLockSeconds = max(0, $_SESSION['login_lock_until'] - $now);

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (empty($_SESSION['logged']) && $requestMethod === 'POST' && isset($_POST['pass'])) {
    $submittedPassword = (string)($_POST['pass'] ?? '');

    if ($isLocked) {
        $_SESSION['login_error'] = 'Too many attempts. Try again in ' . $remainingLockSeconds . ' seconds.';
    } elseif ($submittedPassword === '') {
        $_SESSION['login_error'] = 'Password is required.';
    } elseif (verifyAdminPassword($submittedPassword)) {
        session_regenerate_id(true);
        $_SESSION['logged'] = true;
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_lock_until'] = 0;
        unset($_SESSION['login_error']);
    } else {
        $_SESSION['login_attempts']++;

        if ($_SESSION['login_attempts'] >= $maxAttempts) {
            $_SESSION['login_lock_until'] = $now + $lockSeconds;
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_error'] = 'Too many attempts. Login locked for ' . $lockSeconds . ' seconds.';
        } else {
            $remaining = $maxAttempts - $_SESSION['login_attempts'];
            $_SESSION['login_error'] = 'Invalid password. Remaining attempts: ' . $remaining . '.';
        }
    }

    header('Location: admin.php');
    exit;
}

// If not logged in, show login page
if (empty($_SESSION['logged'])) {
    $loginError = $_SESSION['login_error'] ?? null;
    unset($_SESSION['login_error']);
    include __DIR__ . '/views/login.php';
    exit;
}
