<?php
$errors = $errors ?? [];
$passErr = $errors['password'] ?? null;
$confErr = $errors['password_confirm'] ?? null;
$resetToken = $resetToken ?? '';
?>
<section class="py-5">
    <div class="container" style="max-width: 540px;">
        <h1 class="h3 mb-4">Reset your password</h1>
        <div class="card">
            <div class="card-body">
                <form action="<?= BASE_URL ?>/reset" method="post" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($resetToken) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="password">New password</label>
                        <input class="form-control <?= $passErr ? 'is-invalid' : '' ?>" type="password" id="password" name="password" placeholder="••••••••" required>
                        <?php if ($passErr): ?><div class="invalid-feedback"><?= htmlspecialchars($passErr) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirm">Confirm new password</label>
                        <input class="form-control <?= $confErr ? 'is-invalid' : '' ?>" type="password" id="password_confirm" name="password_confirm" placeholder="••••••••" required>
                        <?php if ($confErr): ?><div class="invalid-feedback"><?= htmlspecialchars($confErr) ?></div><?php endif; ?>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" type="submit">Update password</button>
                        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/login">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>