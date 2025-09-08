<?php

/** $candidate from controller, plus $errors/$old */
$errors = $errors ?? [];
$old    = $old ?? [];
$val = function (string $k, $default = '') use ($candidate, $old) {
    return htmlspecialchars($old[$k] ?? ($candidate[$k] ?? $default));
};
$err = fn(string $k) => $errors[$k] ?? null;
$cls = fn(string $k) => $err($k) ? 'is-invalid' : '';
$base = defined('BASE_URL') ? BASE_URL : '';
?>
<section class="py-5">
    <div class="container" style="max-width: 880px;">
        <h1 class="h3 mb-4">My Profile</h1>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php if (!empty($candidate['profile_picture_url'])): ?>
                                <img src="<?= $base . htmlspecialchars($candidate['profile_picture_url']) ?>" alt="Profile"
                                    class="rounded-circle" style="width: 140px; height: 140px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center"
                                    style="width: 140px; height: 140px;">
                                    <span class="text-muted">No photo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted mb-1">JPG/PNG, max 2MB</div>
                        <form action="<?= $base ?>/account" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                            <input type="hidden" name="section" value="photo">
                            <input class="form-control form-control-sm <?= $cls('profile_picture') ?>" type="file" name="profile_picture" accept=".jpg,.jpeg,.png">
                            <?php if ($err('profile_picture')): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($err('profile_picture')) ?></div><?php endif; ?>
                            <button class="btn btn-outline-primary btn-sm mt-3" type="submit">Upload Photo</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="mb-2">Current Resume</h6>
                        <?php if (!empty($candidate['resume_url'])): ?>
                            <a href="<?= $base . htmlspecialchars($candidate['resume_url']) ?>" target="_blank">View/Download</a>
                        <?php else: ?>
                            <span class="text-muted">No resume uploaded.</span>
                        <?php endif; ?>
                        <div class="small text-muted mt-2">PDF/DOC/DOCX, max 5MB</div>
                        <form action="<?= $base ?>/account" method="post" enctype="multipart/form-data" class="mt-2">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                            <input type="hidden" name="section" value="resume">
                            <input class="form-control form-control-sm <?= $cls('resume_file') ?>" type="file" name="resume_file" accept=".pdf,.doc,.docx">
                            <?php if ($err('resume_file')): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($err('resume_file')) ?></div><?php endif; ?>
                            <button class="btn btn-outline-primary btn-sm mt-3" type="submit">Upload Resume</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <form action="<?= $base ?>/account" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                            <input type="hidden" name="section" value="details">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Full name</label>
                                    <input class="form-control <?= $cls('full_name') ?>" type="text" name="full_name" value="<?= $val('full_name') ?>">
                                    <?php if ($err('full_name')): ?><div class="invalid-feedback"><?= htmlspecialchars($err('full_name')) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input class="form-control" type="text" name="phone_number" value="<?= $val('phone_number') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Education level</label>
                                    <input class="form-control" type="text" name="education_level" value="<?= $val('education_level') ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Skills</label>
                                    <textarea class="form-control" name="skills" rows="3" placeholder="e.g., PHP, Laravel, MySQL, Bootstrap"><?= $val('skills') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Experience (years)</label>
                                    <input class="form-control <?= $cls('experience_years') ?>" type="number" min="0" step="1" name="experience_years" value="<?= $val('experience_years') ?>">
                                    <?php if ($err('experience_years')): ?><div class="invalid-feedback"><?= htmlspecialchars($err('experience_years')) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Address</label>
                                    <input class="form-control" type="text" name="address" value="<?= $val('address') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">City</label>
                                    <input class="form-control" type="text" name="city" value="<?= $val('city') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">State</label>
                                    <input class="form-control" type="text" name="state" value="<?= $val('state') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Postal code</label>
                                    <input class="form-control" type="text" name="postal_code" value="<?= $val('postal_code') ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Country</label>
                                    <input class="form-control" type="text" name="country" value="<?= $val('country', 'Malaysia') ?>">
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
                    Your <strong>profile</strong> acts as your <strong>resume</strong>. Keep it updated for best results.
                </div>
            </div>
        </div>
    </div>
</section>