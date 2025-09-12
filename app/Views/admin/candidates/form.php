<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$old    = $old ?? [];
$val = function ($k, $d = '') use ($old, $candidate) {
    return htmlspecialchars($old[$k] ?? ($candidate[$k] ?? $d));
};
$cls = fn($k) => isset($errors[$k]) ? 'is-invalid' : '';
$isEdit = !empty($candidate);
$csrf = htmlspecialchars($_SESSION['csrf'] ?? '');
?>
<section class="py-4">
    <div class="container" style="max-width:720px;">
        <h1 class="h4 mb-3"><?= $isEdit ? 'Edit Candidate' : 'Add Candidate' ?></h1>
        <div class="card">
            <div class="card-body">
                <form method="post" action="<?= $isEdit ? $base . '/admin/candidates/' . $candidate['candidate_id'] . '/edit' : $base . '/admin/candidates' ?>">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input class="form-control <?= $cls('full_name') ?>" name="full_name" value="<?= $val('full_name') ?>" required>
                        <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['full_name']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input class="form-control <?= $cls('email') ?>" name="email" type="email" value="<?= $val('email') ?>" required>
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input class="form-control" name="phone_number" value="<?= $val('phone_number') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Country</label>
                            <input class="form-control" name="country" value="<?= $val('country', 'Malaysia') ?>">
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="verified_status" id="v" <?= ($old['verified_status'] ?? ($candidate['verified_status'] ?? 0)) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="v">Verified</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="premium_badge" id="p" <?= ($old['premium_badge'] ?? ($candidate['premium_badge'] ?? 0)) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="p">Premium badge</label>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label"><?= $isEdit ? 'Password (leave blank to keep)' : 'Password' ?></label>
                        <input class="form-control <?= $cls('password') ?>" name="password" type="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save Changes' : 'Create' ?></button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/candidates">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>