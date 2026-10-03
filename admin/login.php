<?php
/**
 * Admin login. Credentials are checked by admin_login() (password_hash /
 * password_verify, lockout after repeated failures). The form carries a
 * CSRF token tied to the session.
 */
require_once dirname(__DIR__) . '/api/admin/core/bootstrap.php';

$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
// Only allow local admin pages as redirect targets.
if (!preg_match('/^[a-z0-9\-]+\.php(\?[A-Za-z0-9_=&%\-.]*)?$/i', $next) || str_starts_with($next, 'login.php')) {
    $next = 'index.php';
}

if (admin_current()) {
    header('Location: ' . $next);
    exit;
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['username'] ?? ''));
    if (!csrf_valid($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        try {
            admin_login($email, (string) ($_POST['password'] ?? ''), true, false);
            header('Location: ' . $next);
            exit;
        } catch (ApiException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            error_log('[ZenHomeExperts admin login] ' . $e->getMessage());
            $error = 'Could not sign in right now. Please try again.';
        }
    }
}
$csrf = csrf_token();
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login | Zen Home Experts</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=3">
</head>
<body>
<div class="login-page">

    <!-- Brand panel (hidden on mobile) -->
    <section class="login-brand">
        <div>
            <span class="brand-logo"><img src="assets/img/logo.png" alt="Zen Home Experts"></span>
        </div>
        <div>
            <h2>Manage every home service booking from one place.</h2>
            <p class="mb-4">Receive bookings, assign trusted professionals, track service status and keep customers informed in real time.</p>
            <div class="feature"><i class="bi bi-calendar-check"></i> Live booking queue &amp; status tracking</div>
            <div class="feature"><i class="bi bi-person-check"></i> Manual professional assignment</div>
            <div class="feature"><i class="bi bi-graph-up-arrow"></i> Payments, reports &amp; customer insights</div>
        </div>
        <small class="text-white-50">&copy; <?= date('Y') ?> Zen Home Experts</small>
    </section>

    <!-- Form -->
    <section class="login-form-wrap">
        <div class="login-card">
            <div class="text-center mb-4">
                <span class="login-mobile-logo mb-3"><img src="assets/img/logo.png" alt="Zen Home Experts" height="60"></span>
                <h1 class="mb-1">Welcome back</h1>
                <p class="text-muted mb-0">Sign in to the Zen Home Experts admin panel</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 fs-13" role="alert">
                    <i class="bi bi-exclamation-octagon"></i> <?= $h($error) ?>
                </div>
            <?php elseif (isset($_GET['logged_out'])): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 fs-13" role="status">
                    <i class="bi bi-check-circle"></i> You have been signed out.
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body p-4">
                    <form method="post" action="login.php" class="needs-validation" novalidate autocomplete="on">
                        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="next" value="<?= $h($next) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="loginUser">Email</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="email" class="form-control" id="loginUser" name="username" value="<?= $h($email) ?>" placeholder="admin@zenhomeexperts.com" required autofocus>
                                <div class="invalid-feedback">Please enter your email address.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label class="form-label" for="loginPass">Password</label>
                                <a href="#" class="fs-12 fw-semibold" data-bs-toggle="tooltip" title="Contact the Super Admin to reset your password">Forgot password?</a>
                            </div>
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="loginPass" name="password" placeholder="Enter password" required>
                                <button class="btn toggle-pass border" type="button" aria-label="Show password"><i class="bi bi-eye"></i></button>
                                <div class="invalid-feedback">Please enter your password.</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 mt-2">
                            Sign In <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-muted fs-12 mt-4 mb-0">
                <i class="bi bi-shield-lock me-1"></i> Authorized personnel only. Activity is logged.
            </p>
        </div>
    </section>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/api.js?v=2"></script>
<script src="assets/js/admin.js?v=2"></script>
</body>
</html>
