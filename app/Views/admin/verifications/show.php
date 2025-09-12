<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$row  = $row ?? null;
$csrf = htmlspecialchars($_SESSION['csrf'] ?? '');
if ($row) {
    $name = htmlspecialchars((string)$row['full_name']);
    $email = htmlspecialchars((string)$row['email']);
    $doc  = htmlspecialchars((string)$row['document_path']);
    $status = (string)$row['status'];
    $submitted = !empty($row['submitted_at']) ? date('d/m/Y H:i', strtotime((string)$row['submitted_at'])) : '';
}
?>
<section class="py-4">
    <div class="container" style="max-width: 920px;">
        <h1 class="h4 mb-3">Verification — <?= $name ?></h1>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-2"><strong>Candidate:</strong> <?= $name ?></div>
                        <div class="mb-2"><strong>Email:</strong> <?= $email ?></div>
                        <div class="mb-2"><strong>Status:</strong> <?= htmlspecialchars($status) ?></div>
                        <div class="mb-2"><strong>Submitted:</strong> <?= htmlspecialchars($submitted) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-2 bg-light">
                            <a href="<?= $base . '/' . ltrim($doc, '/') ?>" target="_blank" rel="noopener">Open document</a>
                            <div class="small text-muted mt-2">If it’s an image/PDF, your browser should preview it.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($status === 'Pending'): ?>
            <div class="d-flex gap-2">
                <form action="<?= $base ?>/admin/verifications/<?= (int)$row['id'] ?>/approve" method="post">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <button class="btn btn-success" type="submit">Approve</button>
                </form>

                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#reject-modal">Reject</button>
                <div class="modal fade" id="reject-modal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form class="modal-content" action="<?= $base ?>/admin/verifications/<?= (int)$row['id'] ?>/reject" method="post">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Reject verification</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label">Reason (optional)</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Explain the reason..."></textarea>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-danger" type="submit">Reject</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/verifications">Back</a>
        <?php endif; ?>
    </div>
</section>