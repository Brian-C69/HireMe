<?php
$base    = defined('BASE_URL') ? BASE_URL : '';
$rows    = $rows ?? [];       // from controller
$f       = $filters ?? [];
$page    = (int)($f['page'] ?? 1);
$per     = (int)($f['per']  ?? 12);
$total   = (int)($f['total'] ?? 0);
$pages   = max(1, (int)ceil($total / max(1, $per)));
$unlockedIds = $unlockedIds ?? [];

$qs = function (array $o = []) use ($f) {
    $q = array_filter(array_merge($f, $o), fn($v) => $v !== '' && $v !== null);
    if (($q['page'] ?? 1) == 1) unset($q['page']);
    return http_build_query($q);
};
?>
<style>
    /* Prevent text from spilling out of cards */
    .clamp-1 {
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .candidate-meta {
        min-height: 48px;
    }

    /* keeps two meta lines aligned */
</style>

<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Candidates</h1>
            <?php if ($total): ?>
                <div class="text-muted small">
                    Showing <?= number_format(min(($page - 1) * $per + 1, $total)) ?>–<?= number_format(min($page * $per, $total)) ?>
                    of <?= number_format($total) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="get" action="<?= $base ?>/candidates">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input class="form-control" name="q" value="<?= htmlspecialchars($f['q'] ?? '') ?>" placeholder="Name or skills">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">City</label>
                        <input class="form-control" name="city" value="<?= htmlspecialchars($f['city'] ?? '') ?>" placeholder="e.g., KL">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">State</label>
                        <input class="form-control" name="state" value="<?= htmlspecialchars($f['state'] ?? '') ?>" placeholder="e.g., Selangor">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Min Experience</label>
                        <input class="form-control" type="number" min="0" step="1" name="min_exp" value="<?= htmlspecialchars($f['min_exp'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Verified</label>
                        <?php $v = $f['verified'] ?? ''; ?>
                        <select class="form-select" name="verified">
                            <option value="">Any</option>
                            <option value="1" <?= $v === '1' ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Premium</label>
                        <?php $p = $f['premium'] ?? ''; ?>
                        <select class="form-select" name="premium">
                            <option value="">Any</option>
                            <option value="1" <?= $p === '1' ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Per Page</label>
                        <?php $perSel = (int)($f['per'] ?? 12); ?>
                        <select class="form-select" name="per">
                            <?php foreach ([12, 24, 36, 48] as $n): ?>
                                <option value="<?= $n ?>" <?= $perSel === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search me-1"></i>Search
                        </button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/candidates">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!$rows): ?>
            <div class="alert alert-light border">No candidates match your filters.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($rows as $r): ?>
                    <?php
                    $id      = (int)$r['candidate_id'];
                    $name    = htmlspecialchars((string)$r['full_name']);
                    $city    = trim((string)($r['city'] ?? ''));
                    $state   = trim((string)($r['state'] ?? ''));
                    $locStr  = $city || $state ? htmlspecialchars(trim($city . ($city && $state ? ', ' : '') . $state)) : '—';
                    $years   = (int)($r['experience_years'] ?? 0);
                    $avatar  = trim((string)($r['profile_picture_url'] ?? ''));
                    $premium = !empty($r['premium_badge']);
                    $verified = !empty($r['verified_status']);
                    ?>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <div class="card h-100 shadow-sm position-relative"> <!-- position-relative for badge -->
                            <?php $isUnlocked = in_array($id, $unlockedIds, true); ?>
                            <span class="position-absolute top-0 end-0 m-2 badge <?= $isUnlocked ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <i class="bi <?= $isUnlocked ? 'bi-unlock' : 'bi-lock' ?>"></i>
                                <?= $isUnlocked ? 'Unlocked' : 'Locked' ?>
                            </span>

                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="border bg-light d-flex align-items-center justify-content-center rounded flex-shrink-0"
                                        style="width:48px;height:48px;overflow:hidden;">
                                        <?php if (!empty($avatar)): ?>
                                            <img src="<?= $base . '/' . ltrim($avatar, '/') ?>" alt="Avatar"
                                                style="max-width:100%;max-height:100%;object-fit:cover;">
                                        <?php else: ?>
                                            <span class="text-muted small">Avatar</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="ms-2 w-100">
                                        <div class="fw-semibold text-truncate" title="<?= $name ?>">
                                            <?= $name ?>
                                        </div>
                                        <div class="mt-1 small d-flex flex-wrap gap-1">
                                            <?php if ($premium): ?>
                                                <span class="badge rounded-pill text-bg-warning text-dark">⭐ Premium</span>
                                            <?php endif; ?>
                                            <?php if ($verified): ?>
                                                <span class="badge rounded-pill text-bg-success">Verified ✓</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="small text-muted candidate-meta">
                                            <div class="clamp-1 text-break">
                                                <i class="bi bi-geo-alt me-1"></i><?= $locStr ?>
                                            </div>
                                            <div><i class="bi bi-briefcase me-1"></i><?= $years ?> yrs exp</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="small text-muted clamp-2">
                                    Basic summary visible. Full resume, contact &amp; experience are <?= $isUnlocked ? 'available.' : 'locked.' ?>
                                </div>
                            </div>

                            <div class="card-footer d-flex justify-content-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/candidates/<?= $id ?>">View</a>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="mt-3" aria-label="Candidates pagination">
                    <ul class="pagination justify-content-center">
                        <?php $prev = max(1, $page - 1);
                        $next = min($pages, $page + 1); ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base ?>/candidates?<?= $qs(['page' => 1]) ?>">First</a>
                        </li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base ?>/candidates?<?= $qs(['page' => $prev]) ?>">Prev</a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($pages, $page + 2);
                        if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $base ?>/candidates?<?= $qs(['page' => $i]) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;
                        if ($end < $pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; ?>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base ?>/candidates?<?= $qs(['page' => $next]) ?>">Next</a>
                        </li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base ?>/candidates?<?= $qs(['page' => $pages]) ?>">Last</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>