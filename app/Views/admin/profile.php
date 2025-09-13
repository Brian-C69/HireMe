<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$old    = $old ?? [];
$me     = $me ?? [];
$csrf   = htmlspecialchars($csrf ?? '');
$val = fn($k, $d = '') => htmlspecialchars($old[$k] ?? ($me[$k] ?? $d));
?>
<section class="py-4">
    <div class="container" style="max-width:720px;">
        <h1 class="h4 mb-3">My Profile</h1>
        <div class="card">
            <div class="card-body">
                <form method="post" action="<?= $base ?>/admin/profile">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input class="form-control" name="full_name" value="<?= $val('full_name') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" value="<?= $val('email') ?>" required>
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password (optional)</label>
                        <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" name="password">
                        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input class="form-control" name="phone" value="<?= $val('phone') ?>">
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Save</button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/welcome">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>