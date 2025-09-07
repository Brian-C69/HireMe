<?php
$title = $title ?? 'HireMe';
$base  = defined('BASE_URL') ? BASE_URL : '';
$flash = $_SESSION['flash'] ?? null;
if ($flash) unset($_SESSION['flash']); // show-once
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="HireMe — Malaysia’s recruitment platform.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: #fff;
            color: #212529
        }

        .hero {
            background: radial-gradient(1200px 400px at 20% 0%, rgba(13, 110, 253, .08), transparent), linear-gradient(180deg, #f8fbff 0%, #fff 100%)
        }

        .rounded-2xl {
            border-radius: 1rem
        }

        .bg-soft {
            background: #f8f9fa
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= $base ?>/index.php"><i class="bi bi-stars text-primary me-1"></i> HireMe</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
            <div id="nav" class="collapse navbar-collapse">
                <?php if (!empty($_SESSION['user'])): ?>
                    <ul class="navbar-nav me-auto">
                        <a href="<?= $base ?>/welcome" class="btn btn-outline-primary me-2">Dashboard</a>
                    </ul>
                    <div class="d-flex gap-2">
                        <span class="navbar-text small me-2 text-muted">
                            <?= htmlspecialchars($_SESSION['user']['email'] ?? '') ?> (<?= htmlspecialchars($_SESSION['user']['role'] ?? '') ?>)
                        </span>
                        <a href="<?= $base ?>/logout" class="btn btn-outline-secondary">Logout</a>
                    </div>
                <?php else: ?>
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/index.php#features">Features</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/index.php#how">How It Works</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/index.php#roles">Roles</a></li>
                    </ul>
                    <div class="d-flex gap-2">
                        <a href="<?= $base ?>/login" class="btn btn-outline-primary">Login</a>
                        <a href="<?= $base ?>/register" class="btn btn-primary">Get Started</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <?php if ($flash): ?>
        <div class="container mt-3">
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <main><?php require $viewFile; ?></main>

    <footer class="py-4 bg-white border-top mt-5">
        <div class="container d-flex flex-column flex-lg-row justify-content-between align-items-center gap-2">
            <div class="small text-muted">© <?= date('Y') ?> HireMe. All rights reserved.</div>
            <div class="small">
                <a href="<?= $base ?>/terms" class="me-3 link-dark">Terms</a>
                <a href="<?= $base ?>/privacy" class="link-dark">Privacy</a>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>