<?php http_response_code(404); ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Page not found | HireMe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            color: #212529;
        }

        .vh-70 {
            min-height: 70vh;
        }
    </style>
</head>

<body>
    <main class="container d-flex flex-column justify-content-center align-items-center text-center vh-70 py-5">
        <div class="mb-3"><i class="bi bi-search" style="font-size: 4rem;"></i></div>
        <h1 class="display-5 fw-semibold mb-2">Page not found</h1>
        <p class="text-muted mb-4">The page you’re looking for doesn’t exist or has been moved.</p>
        <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?= htmlspecialchars(($baseUrl ?: '') . '/index.php') ?>">
                <i class="bi bi-house-door me-1"></i> Back to Home
            </a>
            <a class="btn btn-outline-primary" href="javascript:history.back()"><i class="bi bi-arrow-left me-1"></i> Go Back</a>
        </div>
    </main>
    <footer class="py-4 border-top bg-white">
        <div class="container text-center small text-muted">© <?= date('Y') ?> HireMe. All rights reserved.</div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>