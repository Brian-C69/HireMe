<?php
$errors = $errors ?? [];
$old    = $old ?? [];
$emailErr = $errors['email'] ?? null;
?>
<section class="py-5">
    <div class="container" style="max-width: 540px;">
        <h1 class="h3 mb-4">Forgot your password?</h1>
        <div class="card">
            <div class="card-body">
                <p class="text-muted">Enter your account email. If it exists, we’ll email you a reset link.</p>
                <form action="<?= BASE_URL ?>/forgot" method="post" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input class="form-control <?= $emailErr ? 'is-invalid' : '' ?>"
                            type="email" id="email" name="email" required
                            value="<?= htmlspecialchars($old['email'] ?? '') ?>" placeholder="you@example.com">
                        <?php if ($emailErr): ?><div class="invalid-feedback"><?= htmlspecialchars($emailErr) ?></div><?php endif; ?>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" type="submit">Email reset link</button>
                        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/login">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>