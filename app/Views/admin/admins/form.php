<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$old    = $old ?? [];
$admin  = $admin ?? null;
$csrf   = htmlspecialchars($csrf ?? '');
$val = fn($k, $d = '') => htmlspecialchars($old[$k] ?? ($admin[$k] ?? $d));
$isEdit = (bool)$admin;
$action = $isEdit ? ($base . '/admin/admins/' . (int)$admin['admin_id'] . '/edit')
    : ($base . '/admin/admins');
$roles  = ['SuperAdmin', 'Support', 'Verifier', 'Finance'];
$statuses = ['Active', 'Suspended', 'Deleted'];
?>
<section class="py-4">
    <div class="container" style="max-width:900px;">
        <h1 class="h4 mb-3"><?= $isEdit ? 'Edit Admin' : 'New Admin' ?></h1>
        <div class="card">
            <div class="card-body">
                <form method="post" action="<?= $action ?>" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input class="form-control" name="full_name" value="<?= $val('full_name') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" value="<?= $val('email') ?>" required>
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"><?= $isEdit ? 'New Password (optional)' : 'Password' ?></label>
                            <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" name="password" <?= $isEdit ? '' : 'required' ?>>
                            <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <?php $r = $old['role'] ?? ($admin['role'] ?? 'Support'); ?>
                            <select class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>" name="role">
                                <?php foreach ($roles as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $r === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <?php $st = $old['status'] ?? ($admin['status'] ?? 'Active'); ?>
                            <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" name="status">
                                <?php foreach ($statuses as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $st === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Permissions (JSON or notes)</label>
                            <textarea class="form-control" name="permissions" rows="5" placeholder='e.g. {"canRefund": true, "areas":["credits","verifications"]}'><?= $val('permissions') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" class="form-control <?= isset($errors['profile_photo']) ? 'is-invalid' : '' ?>" name="profile_photo" accept="image/*">
                            <?php if (isset($errors['profile_photo'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['profile_photo']) ?></div><?php endif; ?>
                            <?php if ($isEdit && !empty($admin['profile_photo'])): ?>
                                <div class="mt-2">
                                    <img src="<?= $base . '/' . ltrim($admin['profile_photo'], '/') ?>" alt="Photo" style="height:64px;">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save Changes' : 'Create Admin' ?></button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/admins">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>