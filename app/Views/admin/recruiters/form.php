<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$old    = $old ?? [];
$recruiter = $recruiter ?? null;
$val = function ($k, $d = '') use ($old, $recruiter) {
    if (array_key_exists($k, $old)) return htmlspecialchars((string)$old[$k]);
    if ($recruiter && array_key_exists($k, $recruiter)) return htmlspecialchars((string)$recruiter[$k]);
    return htmlspecialchars((string)$d);
};
$cls = fn($k) => isset($errors[$k]) ? 'is-invalid' : '';
$csrf = htmlspecialchars($_SESSION['csrf'] ?? '');
$isEdit = (bool)$recruiter;
$action = $isEdit ? ($base . "/admin/recruiters/" . (int)$recruiter['recruiter_id'] . "/edit") : ($base . "/admin/recruiters");
?>
<section class="py-4">
    <div class="container" style="max-width: 720px;">
        <h1 class="h4 mb-3"><?= $isEdit ? 'Edit Recruiter' : 'Add Recruiter' ?></h1>
        <div class="card">
            <div class="card-body">
                <form action="<?= $action ?>" method="post" novalidate>
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input class="form-control <?= $cls('full_name') ?>" name="full_name" value="<?= $val('full_name') ?>" required>
                            <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['full_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control <?= $cls('email') ?>" name="email" value="<?= $val('email') ?>" placeholder="recruiter@agency.com" required>
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"><?= $isEdit ? 'New Password (optional)' : 'Password' ?></label>
                            <input class="form-control <?= $cls('password') ?>" type="password" name="password" placeholder="<?= $isEdit ? 'Leave blank to keep current' : 'Min 6 characters' ?>">
                            <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Agency</label>
                            <input class="form-control" name="agency_name" value="<?= $val('agency_name') ?>" placeholder="e.g., TalentWorks">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input class="form-control" name="contact_number" value="<?= $val('contact_number') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input class="form-control" name="location" value="<?= $val('location') ?>" placeholder="e.g., Kuala Lumpur">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save Changes' : 'Create Recruiter' ?></button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/recruiters">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>