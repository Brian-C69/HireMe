<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$companies = $companies ?? [];
$csrf = htmlspecialchars($_SESSION['csrf'] ?? '');
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Companies</h1>
            <a class="btn btn-primary" href="<?= $base ?>/companies/create">Add Company</a>
        </div>

        <?php if (!$companies): ?>
            <div class="alert alert-light border">No companies yet. Add your first one.</div>
        <?php else: ?>
            <!-- Bulk actions -->
            <form id="bulk-form" action="<?= $base ?>/companies/bulk" method="post" class="mb-2">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="fw-semibold">Bulk actions:</div>
                    <select id="bulk-action" name="bulk_action" class="form-select form-select-sm w-auto" disabled>
                        <option value="">Choose…</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button id="bulk-apply" type="submit" class="btn btn-sm btn-secondary" disabled>Apply</button>
                    <span id="bulk-count" class="text-muted small ms-2">0 selected</span>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle" id="companies-table">
                    <thead>
                        <tr>
                            <th style="width:36px;">
                                <input class="form-check-input" type="checkbox" id="select-all">
                            </th>
                            <th>Company</th>
                            <th>Location</th>
                            <th>Industry</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $c): ?>
                            <?php
                            $id  = (int)$c['employer_id'];
                            $name = htmlspecialchars($c['company_name'] ?? '');
                            $loc  = htmlspecialchars($c['location'] ?? '');
                            $ind  = htmlspecialchars($c['industry'] ?? '');
                            $upd  = $c['updated_at'] ? date('d/m/Y', strtotime((string)$c['updated_at'])) : '';
                            ?>
                            <tr>
                                <td>
                                    <input class="form-check-input row-check" type="checkbox" name="ids[]" form="bulk-form" value="<?= $id ?>">
                                </td>
                                <td><?= $name ?></td>
                                <td><?= $loc ?></td>
                                <td><?= $ind ?></td>
                                <td><?= htmlspecialchars($upd) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/companies/<?= $id ?>/edit">Edit</a>
                                    <form action="<?= $base ?>/companies/<?= $id ?>/delete" method="post" class="d-inline" onsubmit="return confirm('Delete this company?');">
                                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <script>
                (function() {
                    const selectAll = document.getElementById('select-all');
                    const checks = Array.from(document.querySelectorAll('.row-check'));
                    const bulkAction = document.getElementById('bulk-action');
                    const bulkApply = document.getElementById('bulk-apply');
                    const bulkCount = document.getElementById('bulk-count');

                    function updateUI() {
                        const selected = checks.filter(c => c.checked);
                        const hasSel = selected.length > 0;
                        bulkAction.disabled = !hasSel;
                        bulkApply.disabled = !hasSel;
                        bulkCount.textContent = hasSel ? (selected.length + ' selected') : '0 selected';
                        selectAll.checked = hasSel && selected.length === checks.length;
                        selectAll.indeterminate = hasSel && selected.length !== checks.length;
                    }

                    selectAll?.addEventListener('change', (e) => {
                        checks.forEach(c => c.checked = e.target.checked);
                        updateUI();
                    });

                    checks.forEach(c => c.addEventListener('change', updateUI));
                    updateUI();
                })();
            </script>
        <?php endif; ?>

    </div>
</section>