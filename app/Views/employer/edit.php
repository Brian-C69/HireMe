<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$old    = $old ?? [];
$val = function (string $k, $d = '') use ($employer, $old) {
    return htmlspecialchars($old[$k] ?? ($employer[$k] ?? $d));
};
$err = fn(string $k) => $errors[$k] ?? null;
$cls = fn(string $k) => $err($k) ? 'is-invalid' : '';
$logo = trim((string)($employer['company_logo'] ?? ''));
?>
<section class="py-5">
    <div class="container" style="max-width: 900px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Company Profile</h1>
            <a class="btn btn-outline-secondary" href="<?= $base ?>/welcome">Back</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <div class="border rounded d-inline-flex align-items-center justify-content-center bg-light"
                                style="width:140px;height:140px;">
                                <?php if ($logo): ?>
                                    <img src="<?= $base . '/' . ltrim($logo, '/') ?>" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
                                <?php else: ?>
                                    <span class="text-muted small">Company Logo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="small text-muted mb-1">JPG/PNG, max 2MB</div>
                        <form action="<?= $base ?>/company" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                            <input type="hidden" name="section" value="logo">
                            <input class="form-control form-control-sm <?= $cls('company_logo') ?>" type="file" name="company_logo" accept=".jpg,.jpeg,.png">
                            <?php if ($err('company_logo')): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($err('company_logo')) ?></div><?php endif; ?>
                            <button class="btn btn-outline-primary btn-sm mt-3" type="submit">Upload Logo</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <form action="<?= $base ?>/company" method="post" novalidate>
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                            <input type="hidden" name="section" value="details">

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Company Name</label>
                                    <input class="form-control <?= $cls('company_name') ?>" name="company_name" value="<?= $val('company_name') ?>" required>
                                    <?php if ($err('company_name')): ?><div class="invalid-feedback"><?= htmlspecialchars($err('company_name')) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Industry</label>
                                    <input class="form-control" name="industry" value="<?= $val('industry') ?>" placeholder="e.g., Software">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Location</label>
                                    <input class="form-control" name="location" value="<?= $val('location') ?>" placeholder="Kuala Lumpur">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input class="form-control" name="contact_number" value="<?= $val('contact_number') ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Contact Person</label>
                                    <input class="form-control" name="contact_person_name" value="<?= $val('contact_person_name') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Company Description</label>
                                    <textarea class="form-control" name="company_description" rows="5" placeholder="About the company…"><?= $val('company_description') ?></textarea>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button class="btn btn-primary" type="submit">Save changes</button>
                                <a class="btn btn-outline-secondary" href="<?= $base ?>/welcome">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Info here is used across your job posts (branding, logo, contact).
                </div>
            </div>
        </div>
    </div>
</section>