<?php
/**
 * Admin Password Update Handler
 */

if (isset($_POST['update_admin_password'])) {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (!verifyAdminPassword($currentPassword)) {
        $_SESSION['flash_message'] = 'Current password is incorrect.';
        $_SESSION['flash_type'] = 'danger';
    } elseif (strlen($newPassword) < 8) {
        $_SESSION['flash_message'] = 'New password must be at least 8 characters.';
        $_SESSION['flash_type'] = 'danger';
    } elseif ($newPassword !== $confirmPassword) {
        $_SESSION['flash_message'] = 'New password and confirmation do not match.';
        $_SESSION['flash_type'] = 'danger';
    } else {
        setSetting('admin_password_hash', password_hash($newPassword, PASSWORD_DEFAULT));
        $_SESSION['flash_message'] = 'Admin password updated successfully.';
        $_SESSION['flash_type'] = 'success';
    }

    header('Location: admin.php');
    exit;
}
