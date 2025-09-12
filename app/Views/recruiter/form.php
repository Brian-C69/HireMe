<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$old = $old ?? [];
$csrf = htmlspecialchars($_SESSION['csrf'] ?? '');
$isEdit = isset($company);
$val = function (string $k, $fallback = '') use ($company, $old) {
    if (!empty($old)) return htmlspecialchars((string)($old[$k] ?? ''));
    if (isset($company[$k])) return htmlspecialchars((string)$company[$k]);
    return htmlspecialchars((string)$fallback);
};
$action = $isEdit ? "{$base}/companies/" . (int)$company['employer_id'] . "/edit" : "{$base}/companies";
$titleText = $isEdit ? 'Edit Company' : 'Add Company';
?>
<section class="py-4">
    <div class="container" style="max-width: 720px;">
        <h1 class="h4 mb-3"><?= $titleText ?></h1>
        <form action="<?= $action ?>" method="post" enctype="multipart/form-data" class="card p-3">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">

            <div class="mb-3">
                <label class="form-label">Company Name</label>
                <input type="text" class="form-control <?= isset($errors['company_name']) ? 'is-invalid' : '' ?>" name="company_name" value="<?= $val('company_name') ?>">
                <?php if (isset($errors['company_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['company_name']) ?></div><?php endif; ?>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Industry</label>
                    <input class="form-control" name="industry" value="<?= $val('industry') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Location</label>
                    <input class="form-control" name="location" value="<?= $val('location') ?>">
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-md-6">
                    <label class="form-label">Contact Person</label>
                    <input class="form-control" name="contact_person_name" value="<?= $val('contact_person_name') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Number</label>
                    <input class="form-control" name="contact_number" value="<?= $val('contact_number') ?>">
                </div>
            </div>

            <div class="mb-3 mt-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" rows="4" name="company_description"><?= $val('company_description') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Company Logo (JPG/PNG, max 2MB)</label>
                <input type="file" class="form-control <?= isset($errors['company_logo']) ? 'is-invalid' : '' ?>" name="company_logo" accept="image/jpeg,image/png">
                <?php if (isset($errors['company_logo'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['company_logo']) ?></div><?php endif; ?>
                <?php if ($isEdit && !empty($company['company_logo'])): ?>
                    <div class="mt-2">
                        <img src="<?= $base . '/' . ltrim((string)$company['company_logo'], '/') ?>" alt="Logo" style="height:48px">
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save Changes' : 'Create Company' ?></button>
                <a class="btn btn-outline-secondary" href="<?= $base ?>/companies">Cancel</a>
            </div>
        </form>
    </div>
</section>