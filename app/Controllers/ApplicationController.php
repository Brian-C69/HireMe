<?php

/** File: app/Controllers/ApplicationController.php */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use PDO;

final class ApplicationController
{
    /* ---------- small helpers ---------- */
    private function flash(string $t, string $m): void
    {
        $_SESSION['flash'] = ['type' => $t, 'message' => $m];
    }
    private function setErrors(array $e): void
    {
        $_SESSION['errors'] = $e;
    }
    private function takeErrors(): array
    {
        $e = $_SESSION['errors'] ?? [];
        unset($_SESSION['errors']);
        return $e;
    }
    private function setOld(array $o): void
    {
        $_SESSION['old'] = $o;
    }
    private function takeOld(): array
    {
        $o = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);
        return $o;
    }
    private function redirect(string $p): void
    {
        $b = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $b . $p, true, 302);
        exit;
    }
    private function csrf(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf'];
    }
    private function csrfOk(): bool
    {
        return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
    }
    private function isWithdrawable(string $status): bool
    {
        return in_array($status, ['Applied', 'Reviewed'], true);
    }

    /** WHY: central audit logger */
    private function logHistory(PDO $pdo, int $applicationId, ?string $old, string $new, string $byType, ?int $byId, ?string $note = null): void
    {
        $ins = $pdo->prepare("
          INSERT INTO application_status_history
            (application_id, old_status, new_status, changed_by_type, changed_by_id, note, created_at)
          VALUES (:aid,:old,:new,:typ,:uid,:note,:ts)
        ");
        $ins->execute([
            ':aid' => $applicationId,
            ':old' => $old,
            ':new' => $new,
            ':typ' => $byType,
            ':uid' => $byId,
            ':note' => $note,
            ':ts' => date('Y-m-d H:i:s'),
        ]);
    }

    /** GET /applications/create?job=ID or /jobs/{id}/apply */
    public function create(array $params = []): void
    {
        Auth::requireRole('Candidate');

        $jobId = (int)($_GET['job'] ?? ($params['id'] ?? 0));
        if ($jobId <= 0) {
            $this->flash('danger', 'Invalid job.');
            $this->redirect('/jobs');
        }

        $pdo = DB::conn();
        $st = $pdo->prepare("
          SELECT jp.*, e.company_name, e.company_logo
          FROM job_postings jp
          JOIN employers e ON e.employer_id = jp.company_id
          WHERE jp.job_posting_id = :id AND jp.status='Open' LIMIT 1
        ");
        $st->execute([':id' => $jobId]);
        $job = $st->fetch();
        if (!$job) {
            $this->flash('danger', 'Job not found.');
            $this->redirect('/jobs');
        }

        $qs = $pdo->prepare("
          SELECT mq.id, mq.prompt
          FROM job_micro_questions jmq
          JOIN micro_questions mq ON mq.id = jmq.question_id
          WHERE jmq.job_posting_id = :id
          ORDER BY mq.id ASC
        ");
        $qs->execute([':id' => $jobId]);
        $questions = $qs->fetchAll() ?: [];

        $root   = dirname(__DIR__, 2);
        $title  = 'Apply — ' . $job['job_title'];
        $viewFile = $root . '/app/Views/applications/create.php';
        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        $csrf   = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    /** POST /applications (apply; supports re-apply ONLY after Withdrawn) */
    public function store(array $params = []): void
    {
        Auth::requireRole('Candidate');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/jobs');
        }

        $pdo   = DB::conn();
        $cid   = (int)($_SESSION['user']['id'] ?? 0);
        $jobId = (int)($_POST['job_id'] ?? 0);

        // Load questions
        $qst = $pdo->prepare("
          SELECT mq.id, mq.prompt
          FROM job_micro_questions jmq
          JOIN micro_questions mq ON mq.id = jmq.question_id
          WHERE jmq.job_posting_id = :jid
          ORDER BY mq.id ASC
        ");
        $qst->execute([':jid' => $jobId]);
        $qrows = $qst->fetchAll() ?: [];

        // Validate answers
        $answers = [];
        $errors = [];
        foreach ($qrows as $q) {
            $qid = (int)$q['id'];
            $txt = trim((string)($_POST['answer_' . $qid] ?? ''));
            $answers[] = ['qid' => $qid, 'text' => $txt];
            if ($txt === '') {
                $errors['answer_' . $qid] = 'Please provide an answer.';
            } elseif (mb_strlen($txt) > 1000) {
                $errors['answer_' . $qid] = 'Max 1000 characters.';
            }
        }
        if ($errors) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $_POST;
            $_SESSION['open_apply_modal'] = true;
            $this->redirect('/jobs/' . $jobId);
        }

        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            // Check existing application
            $find = $pdo->prepare("
              SELECT applicant_id, application_status
              FROM applications
              WHERE candidate_id = :cid AND job_posting_id = :jid
              LIMIT 1
            ");
            $find->execute([':cid' => $cid, ':jid' => $jobId]);
            $existing = $find->fetch();

            $reapplied = false;
            if ($existing && (string)$existing['application_status'] !== 'Withdrawn') {
                $pdo->rollBack();
                $this->flash('warning', 'You have already applied to this job.');
                $this->redirect('/jobs/' . $jobId);
            }

            if (!$existing) {
                // New application
                $ins = $pdo->prepare("
                  INSERT INTO applications
                    (candidate_id, job_posting_id, application_date, application_status, resume_url, cover_letter, notes, updated_at)
                  VALUES (:cid,:jid,:ad,'Applied',NULL,NULL,NULL,:ua)
                ");
                $ins->execute([':cid' => $cid, ':jid' => $jobId, ':ad' => $now, ':ua' => $now]);
                $appId = (int)$pdo->lastInsertId();

                // History: NULL -> Applied
                $this->logHistory($pdo, $appId, null, 'Applied', 'Candidate', $cid, null);
            } else {
                // Re-apply from Withdrawn
                $appId = (int)$existing['applicant_id'];
                $oldStatus = (string)$existing['application_status']; // Withdrawn
                $pdo->prepare("UPDATE applications SET application_status='Applied', application_date=:d, updated_at=:u WHERE applicant_id=:id")
                    ->execute([':d' => $now, ':u' => $now, ':id' => $appId]);
                $pdo->prepare("DELETE FROM application_answers WHERE application_id=:id")
                    ->execute([':id' => $appId]);

                // History: Withdrawn -> Applied
                $this->logHistory($pdo, $appId, $oldStatus, 'Applied', 'Candidate', $cid, 'Re-applied after Withdrawn');
                $reapplied = true;
            }

            // Answers
            $ans = $pdo->prepare("INSERT INTO application_answers (application_id, question_id, answer_text, created_at)
                                VALUES (:aid,:qid,:txt,:ts)");
            foreach ($answers as $a) {
                $ans->execute([':aid' => $appId, ':qid' => $a['qid'], ':txt' => $a['text'], ':ts' => $now]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('danger', 'Could not submit application.');
            $this->redirect('/jobs/' . $jobId);
        }

        unset($_SESSION['open_apply_modal']);  // prevent modal from reopening
        $this->flash('success', $reapplied ? 'Re-applied successfully.' : 'Application submitted.');
        $this->redirect('/jobs/' . $jobId);
    }

    /** POST /applications/{id}/withdraw */
    public function withdraw(array $params = []): void
    {
        Auth::requireRole('Candidate');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/applications');
        }

        $appId = (int)($params['id'] ?? 0);
        $cid  = (int)($_SESSION['user']['id'] ?? 0);
        if ($appId <= 0 || $cid <= 0) {
            $this->flash('danger', 'Invalid request.');
            $this->redirect('/applications');
        }

        $pdo = DB::conn();
        $st = $pdo->prepare("
          SELECT a.applicant_id, a.application_status, a.job_posting_id
          FROM applications a
          WHERE a.applicant_id = :id AND a.candidate_id = :cid
          LIMIT 1
        ");
        $st->execute([':id' => $appId, ':cid' => $cid]);
        $app = $st->fetch();
        if (!$app) {
            $this->flash('danger', 'Application not found.');
            $this->redirect('/applications');
        }

        $old = (string)$app['application_status'];
        if (!$this->isWithdrawable($old)) {
            $this->flash('warning', 'This application cannot be withdrawn.');
            $this->redirect('/applications');
        }

        $pdo->prepare("UPDATE applications SET application_status='Withdrawn', updated_at=:u WHERE applicant_id=:id")
            ->execute([':u' => date('Y-m-d H:i:s'), ':id' => $appId]);

        // History: Applied/Reviewed -> Withdrawn
        $this->logHistory($pdo, (int)$app['applicant_id'], $old, 'Withdrawn', 'Candidate', $cid, null);

        $this->flash('success', 'Application withdrawn.');
        $jid = (int)$app['job_posting_id'];
        $this->redirect($jid ? '/jobs/' . $jid : '/applications');
    }

    /** GET /applications — Candidate’s applications with filters + pagination */
    public function index(array $params = []): void
    {
        Auth::requireRole('Candidate');
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

        $pdo = DB::conn();
        $cid = (int)($_SESSION['user']['id'] ?? 0);

        $status = trim((string)($_GET['status'] ?? ''));
        $q      = trim((string)($_GET['q'] ?? ''));
        $per    = max(1, min(50, (int)($_GET['per'] ?? 10)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $off    = ($page - 1) * $per;

        $where = ['a.candidate_id = :cid'];
        $bind = [':cid' => $cid];
        if ($status !== '') {
            $where[] = 'a.application_status = :st';
            $bind[':st'] = $status;
        }
        if ($q !== '') {
            $where[] = '(jp.job_title LIKE :q OR e.company_name LIKE :q)';
            $bind[':q'] = "%$q%";
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $c = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN job_postings jp ON jp.job_posting_id=a.job_posting_id JOIN employers e ON e.employer_id=jp.company_id $whereSql");
        $c->execute($bind);
        $total = (int)$c->fetchColumn();

        $p = $pdo->prepare("
          SELECT a.applicant_id, a.application_status, a.application_date,
                 jp.job_posting_id, jp.job_title, jp.job_location, jp.employment_type,
                 e.company_name, e.company_logo
          FROM applications a
          JOIN job_postings jp ON jp.job_posting_id = a.job_posting_id
          JOIN employers e     ON e.employer_id     = jp.company_id
          $whereSql
          ORDER BY a.application_date DESC, a.applicant_id DESC
          LIMIT :lim OFFSET :off
        ");
        foreach ($bind as $k => $v) $p->bindValue($k, $v);
        $p->bindValue(':lim', $per, PDO::PARAM_INT);
        $p->bindValue(':off', $off, PDO::PARAM_INT);
        $p->execute();
        $applications = $p->fetchAll() ?: [];

        $pages = max(1, (int)ceil($total / $per));
        if ($page > $pages) $page = $pages;

        $root    = dirname(__DIR__, 2);
        $title   = 'My Applications — HireMe';
        $viewFile = $root . '/app/Views/applications/index.php';
        $filters = ['status' => $status, 'q' => $q, 'per' => $per, 'page' => $page, 'total' => $total];
        require $root . '/app/Views/layout.php';
    }
}
