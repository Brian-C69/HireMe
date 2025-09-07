<?php $title = $title ?? 'HireMe'; ?>
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
            <a class="navbar-brand fw-bold" href="/"><i class="bi bi-stars text-primary me-1"></i> HireMe</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
            <div id="nav" class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="/#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="/#how">How It Works</a></li>
                    <li class="nav-item"><a class="nav-link" href="/#roles">Roles</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="/login" class="btn btn-outline-primary">Login</a>
                    <a href="/register" class="btn btn-primary">Get Started</a>
                </div>
            </div>
        </div>
    </nav>

    <main><?php require $viewFile; ?></main>

    <footer class="py-4 bg-white border-top mt-5">
        <div class="container d-flex flex-column flex-lg-row justify-content-between align-items-center gap-2">
            <div class="small text-muted">© <?= date('Y') ?> HireMe. All rights reserved.</div>
            <div class="small">
                <a href="/terms" class="me-3 link-dark">Terms</a>
                <a href="/privacy" class="link-dark">Privacy</a>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>