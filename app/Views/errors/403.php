<?php http_response_code(403);
$base = defined('BASE_URL') ? BASE_URL : ''; ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Forbidden | HireMe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            color: #212529
        }

        .vh-70 {
            min-height: 70vh
        }
    </style>
</head>

<body>
    <main class="container d-flex flex-column justify-content-center align-items-center text-center vh-70 py-5">
        <div class="mb-3"><i class="bi bi-shield-lock" style="font-size:4rem;"></i></div>
        <h1 class="display-6 fw-semibold mb-2">Access denied</h1>
        <p class="text-muted mb-4">You don’t have permission to view this page.</p>
        <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?= htmlspecialchars($base) ?>/welcome"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($base) ?>/index.php"><i class="bi bi-house-door me-1"></i> Home</a>
        </div>
    </main>
    <footer class="py-4 border-top bg-white">
        <div class="container text-center small text-muted">© <?= date('Y') ?> HireMe. All rights reserved.</div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>