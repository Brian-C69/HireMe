<!-- app/Views/auth/login.php -->
<section class="py-5">
    <div class="container" style="max-width: 540px;">
        <h1 class="h3 mb-4">Login</h1>
        <div class="card">
            <div class="card-body">
                <?php
                $errors = $errors ?? [];
                $old    = $old ?? [];
                $emailErr = $errors['email'] ?? null;
                $passErr  = $errors['password'] ?? null;
                ?>
                <form action="<?= BASE_URL ?>/login" method="post" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input
                            class="form-control <?= $emailErr ? 'is-invalid' : '' ?>"
                            type="email" id="email" name="email"
                            value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                            placeholder="you@example.com" required>
                        <?php if ($emailErr): ?><div class="invalid-feedback"><?= htmlspecialchars($emailErr) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label d-flex justify-content-between">
                            <span>Password</span>
                            <a class="small" href="<?= BASE_URL ?>/forgot">Forgot?</a>
                        </label>
                        <input
                            class="form-control <?= $passErr ? 'is-invalid' : '' ?>"
                            type="password" id="password" name="password" placeholder="••••••••" required>
                        <?php if ($passErr): ?><div class="invalid-feedback"><?= htmlspecialchars($passErr) ?></div><?php endif; ?>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" type="submit">Sign in</button>
                        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/index.php">Back to Home</a>
                    </div>
                    <div class="mt-3 small text-muted">
                        New here? <a href="<?= BASE_URL ?>/register">Create an account</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>