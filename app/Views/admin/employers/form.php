<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$old    = $old ?? [];
$employer = $employer ?? null;
$recruiters = $recruiters ?? [];
$val = function ($k, $d = '') use ($old, $employer) {
    if (array_key_exists($k, $old)) return htmlspecialchars((string)$old[$k]);
    if ($employer && array_key_exists($k, $employer)) return htmlspecialchars((string)$employer[$k]);
    return htmlspecialchars((string)$d);
};
$cls = fn($k) => isset($errors[$k]) ? 'is-invalid' : '';
$csrf = htmlspecialchars($_SESSION['csrf'] ?? '');
$isEdit = (bool)$employer;
$logo = $employer['company_logo'] ?? '';
$isClient = isset($old['is_client_company']) ? true : ((int)($employer['is_client_company'] ?? 0) === 1);
$rid = isset($old['created_by_recruiter_id']) ? (int)$old['created_by_recruiter_id'] : (int)($employer['created_by_recruiter_id'] ?? 0);
$action = $isEdit ? ($base . "/admin/employers/" . (int)$employer['employer_id'] . "/edit") : ($base . "/admin/employers");
?>
<section class="py-4">
    <div class="container" style="max-width: 860px;">
        <h1 class="h4 mb-3"><?= $isEdit ? 'Edit Employer' : 'Add Employer' ?></h1>
        <div class="card">
            <div class="card-body">
                <form action="<?= $action ?>" method="post" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name</label>
                            <input class="form-control <?= $cls('company_name') ?>" name="company_name" value="<?= $val('company_name') ?>" required>
                            <?php if (isset($errors['company_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['company_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email (leave blank for client company)</label>
                            <input class="form-control <?= $cls('email') ?>" name="email" value="<?= $val('email') ?>" placeholder="owner@company.com">
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"><?= $isEdit ? 'New Password (optional)' : 'Password' ?></label>
                            <input class="form-control <?= $cls('password') ?>" type="password" name="password" placeholder="<?= $isEdit ? 'Leave blank to keep current' : 'Min 6 characters (required if email set)' ?>">
                            <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Industry</label>
                            <input class="form-control" name="industry" value="<?= $val('industry') ?>" placeholder="e.g., Software">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input class="form-control" name="location" value="<?= $val('location') ?>" placeholder="e.g., Kuala Lumpur">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Contact Person</label>
                            <input class="form-control" name="contact_person_name" value="<?= $val('contact_person_name') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input class="form-control" name="contact_number" value="<?= $val('contact_number') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="company_description" rows="4"><?= $val('company_description') ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Logo (JPG/PNG, max 2MB)</label>
                            <input class="form-control <?= $cls('company_logo') ?>" type="file" name="company_logo" accept="image/png,image/jpeg">
                            <?php if (isset($errors['company_logo'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['company_logo']) ?></div><?php endif; ?>
                            <?php if ($logo): ?>
                                <div class="mt-2">
                                    <img src="<?= $base . '/' . ltrim($logo, '/') ?>" alt="Logo" style="max-height:60px;">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="is_client_company" name="is_client_company" <?= $isClient ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_client_company">Client Company (for Recruiter)</label>
                            </div>
                        </div>
                        <div class="col-md-8" id="recruiter-wrap" style="<?= $isClient ? '' : 'display:none;' ?>">
                            <label class="form-label">Owned by Recruiter</label>
                            <select class="form-select <?= $cls('created_by_recruiter_id') ?>" name="created_by_recruiter_id">
                                <option value="">— Choose recruiter —</option>
                                <?php foreach ($recruiters as $r): ?>
                                    <option value="<?= (int)$r['recruiter_id'] ?>" <?= $rid === (int)$r['recruiter_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['full_name'] ?: $r['email'] ?: ('#' . $r['recruiter_id'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['created_by_recruiter_id'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['created_by_recruiter_id']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <script>
                        document.getElementById('is_client_company').addEventListener('change', function() {
                            document.getElementById('recruiter-wrap').style.display = this.checked ? '' : 'none';
                        });
                    </script>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save Changes' : 'Create Employer' ?></button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/employers">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>