<!-- app/Views/auth/register.php -->
<?php
$errors = $errors ?? [];
$old    = $old ?? [];
$fv = fn($k) => htmlspecialchars($old[$k] ?? '');
$err = fn($k) => $errors[$k] ?? null;
$cls = fn($k) => $err($k) ? 'is-invalid' : '';
?>
<section class="py-5">
    <div class="container" style="max-width: 720px;">
        <h1 class="h3 mb-4">Create your account</h1>
        <div class="card">
            <div class="card-body">
                <form action="<?= BASE_URL ?>/register" method="post" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                    <div class="mb-3">
                        <label class="form-label" for="role">I am a</label>
                        <select class="form-select <?= $cls('role') ?>" id="role" name="role" required>
                            <option value="">— Select role —</option>
                            <option value="Candidate" <?= ($old['role'] ?? '') === 'Candidate' ? 'selected' : '' ?>>Job Seeker (Candidate)</option>
                            <option value="Employer" <?= ($old['role'] ?? '') === 'Employer' ? 'selected' : '' ?>>Employer (Company)</option>
                            <option value="Recruiter" <?= ($old['role'] ?? '') === 'Recruiter' ? 'selected' : '' ?>>Recruiter</option>
                        </select>
                        <?php if ($err('role')): ?><div class="invalid-feedback"><?= htmlspecialchars($err('role')) ?></div><?php endif; ?>
                        <div class="form-text">We’ll tailor the required fields to your role.</div>
                    </div>

                    <div id="candidateRecruiterFields" style="<?= ($old['role'] ?? '') === 'Employer' ? 'display:none' : '' ?>">
                        <div class="mb-3">
                            <label class="form-label" for="full_name">Full name</label>
                            <input class="form-control <?= $cls('full_name') ?>" type="text" id="full_name" name="full_name" value="<?= $fv('fullName') ?>" placeholder="e.g., Ali Bin Abu">
                            <?php if ($err('full_name')): ?><div class="invalid-feedback"><?= htmlspecialchars($err('full_name')) ?></div><?php endif; ?>
                        </div>
                        <div class="mb-3" id="agencyWrap" style="<?= ($old['role'] ?? '') === 'Recruiter' ? '' : 'display:none' ?>">
                            <label class="form-label" for="agency_name">Agency name (optional)</label>
                            <input class="form-control" type="text" id="agency_name" name="agency_name" value="<?= $fv('agencyName') ?>" placeholder="e.g., TalentPro Sdn Bhd">
                        </div>
                    </div>

                    <div id="employerFields" style="<?= ($old['role'] ?? '') === 'Employer' ? '' : 'display:none' ?>">
                        <div class="mb-3">
                            <label class="form-label" for="company_name">Company name</label>
                            <input class="form-control <?= $cls('company_name') ?>" type="text" id="company_name" name="company_name" value="<?= $fv('companyName') ?>" placeholder="e.g., ABC Sdn Bhd">
                            <?php if ($err('company_name')): ?><div class="invalid-feedback"><?= htmlspecialchars($err('company_name')) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control <?= $cls('email') ?>" type="email" id="email" name="email" value="<?= $fv('email') ?>" placeholder="you@example.com" required>
                            <?php if ($err('email')): ?><div class="invalid-feedback"><?= htmlspecialchars($err('email')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone (optional)</label>
                            <input class="form-control" type="text" id="phone" name="phone" value="<?= $fv('phone') ?>" placeholder="+60...">
                        </div>
                    </div>

                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control <?= $cls('password') ?>" type="password" id="password" name="password" placeholder="••••••••" required>
                            <?php if ($err('password')): ?><div class="invalid-feedback"><?= htmlspecialchars($err('password')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirm">Confirm password</label>
                            <input class="form-control <?= $cls('password_confirm') ?>" type="password" id="password_confirm" name="password_confirm" placeholder="••••••••" required>
                            <?php if ($err('password_confirm')): ?><div class="invalid-feedback"><?= htmlspecialchars($err('password_confirm')) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label" for="location">Location (optional)</label>
                        <input class="form-control" type="text" id="location" name="location" value="<?= $fv('location') ?>" placeholder="Kuala Lumpur, MY">
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button class="btn btn-primary" type="submit">Create account</button>
                        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/index.php">Back to Home</a>
                    </div>

                    <div class="mt-3 small text-muted">
                        Already have an account? <a href="<?= BASE_URL ?>/login">Log in</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
    (function() {
        const role = document.getElementById('role');
        const emp = document.getElementById('employerFields');
        const cr = document.getElementById('candidateRecruiterFields');
        const ag = document.getElementById('agencyWrap');

        function sync() {
            const v = role.value;
            emp.style.display = v === 'Employer' ? '' : 'none';
            cr.style.display = v === 'Employer' ? 'none' : '';
            ag.style.display = v === 'Recruiter' ? '' : 'none';
        }
        role.addEventListener('change', sync);
        sync();
    })();
</script>