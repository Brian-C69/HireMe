<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$csrf   = htmlspecialchars($_SESSION['csrf'] ?? '');
$items  = $verifs ?? ($rows ?? []);
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Candidate Verifications</h1>
            <form id="bulk-form" action="<?= $base ?>/admin/verifications/bulk" method="post">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <div class="d-flex align-items-center gap-2">
                    <select name="action" class="form-select form-select-sm" id="bulk-action" disabled>
                        <option value="">Bulk action…</option>
                        <option value="approve">Approve</option>
                        <option value="reject">Reject</option>
                        <option value="clear">Clear Doc</option>
                    </select>
                    <button class="btn btn-sm btn-secondary" id="bulk-apply" type="submit" disabled>Apply</button>
                    <span class="small text-muted" id="bulk-count">0 selected</span>
                </div>
            </form>
        </div>

        <?php if (!$items): ?>
            <div class="alert alert-light border">No submissions yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle" id="verif-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input class="form-check-input" type="checkbox" id="select-all"></th>
                            <th>Candidate</th>
                            <th>Doc Type</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Document</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $row): ?>
                            <?php
                            // Map to actual column names from `candidates`
                            $cid    = (int)($row['candidate_id'] ?? 0);
                            $name   = htmlspecialchars((string)($row['full_name'] ?? ''));
                            $email  = htmlspecialchars((string)($row['email'] ?? ''));
                            $dtype  = htmlspecialchars((string)($row['verification_doc_type'] ?? '—'));
                            $durl   = (string)($row['verification_doc_url'] ?? '');
                            $dtime  = (string)($row['verification_date'] ?? '');
                            $statusRaw = $row['verified_status'] ?? null; // 1=Verified, 2=Rejected, 0/NULL=Pending
                            $submitted = $dtime ? date('d/m/Y H:i', strtotime($dtime)) : '—';

                            // Status badge
                            $statusTxt = 'Pending';
                            $badge     = 'secondary';
                            if ($statusRaw === 1 || $statusRaw === '1') {
                                $statusTxt = 'Verified';
                                $badge = 'success';
                            } elseif ($statusRaw === 2 || $statusRaw === '2') {
                                $statusTxt = 'Rejected';
                                $badge = 'danger';
                            }

                            // Document link (if any)
                            $docHtml = '—';
                            if ($durl !== '') {
                                $href = $base . '/' . ltrim($durl, '/');
                                $docHtml = '<a class="btn btn-sm btn-outline-dark" target="_blank" href="' . htmlspecialchars($href) . '">View</a>';
                            }
                            ?>
                            <tr>
                                <td>
                                    <input class="form-check-input row-check" type="checkbox" name="ids[]" form="bulk-form" value="<?= $cid ?>">
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= $name ?: '—' ?></div>
                                    <div class="small text-muted"><?= $email ?: '' ?></div>
                                </td>
                                <td><?= $dtype ?></td>
                                <td><?= htmlspecialchars($submitted) ?></td>
                                <td><span class="badge text-bg-<?= $badge ?>"><?= $statusTxt ?></span></td>
                                <td><?= $docHtml ?></td>
                                <td class="text-end">
                                    <form action="<?= $base ?>/admin/verifications/<?= $cid ?>/approve" method="post" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                        <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                    </form>
                                    <form action="<?= $base ?>/admin/verifications/<?= $cid ?>/reject" method="post" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                    </form>
                                    <form action="<?= $base ?>/admin/verifications/<?= $cid ?>/clear" method="post" class="d-inline" onsubmit="return confirm('Clear the uploaded document?');">
                                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Clear Doc</button>
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
                        const sel = checks.filter(c => c.checked);
                        const has = sel.length > 0;
                        bulkAction.disabled = !has;
                        bulkApply.disabled = !has;
                        bulkCount.textContent = has ? (sel.length + ' selected') : '0 selected';
                        selectAll.checked = has && sel.length === checks.length;
                        selectAll.indeterminate = has && sel.length !== checks.length;
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