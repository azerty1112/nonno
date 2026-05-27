<?php
/**
 * Login Page Template
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login - <?= e($siteTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(circle at top, #1f2937, #0b1120 65%);
        }
        .login-card {
            max-width: 430px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center text-light p-3">
<main class="card bg-dark shadow-lg login-card w-100">
    <div class="card-body p-4 p-md-5">
        <h1 class="h4 mb-3 text-center"><?= e($siteTitle) ?> Admin</h1>
        <p class="text-secondary text-center mb-4">Secure access to content management dashboard.</p>

        <?php if ($loginError): ?>
            <div class="alert alert-danger py-2 small mb-3" role="alert"><?= e($loginError) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <label for="pass" class="form-label">Password</label>
            <input id="pass" type="password" name="pass" class="form-control form-control-lg" placeholder="Enter admin password" autocomplete="current-password" required autofocus>
            <button class="btn btn-primary w-100 mt-3">Login</button>
        </form>

        <small class="d-block text-secondary mt-3 text-center">Tip: set <code>ADMIN_PASSWORD</code> env var for production.</small>
    </div>
</main>
</body>
</html>
