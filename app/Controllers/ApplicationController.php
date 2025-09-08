<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use PDO;

final class ApplicationController
{
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
        $job = $pdo->prepare("
    SELECT jp.*, e.company_name, e.company_logo
    FROM job_postings jp
    JOIN employers e ON e.employer_id = jp.company_id
    WHERE jp.job_posting_id = :id AND jp.status='Open' LIMIT 1
    ");
        $job->execute([':id' => $jobId]);
        $job = $job->fetch();
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

        $root = dirname(__DIR__, 2);
        $title = 'Apply — ' . $job['job_title'];
        $viewFile = $root . '/app/Views/applications/create.php';
        $errors = $this->takeErrors();
        $old = $this->takeOld();
        $csrf = $this->csrf();

        require $root . '/app/Views/layout.php';
    }

    /** POST /applications */
    public function store(array $params = []): void
    {
        \App\Core\Auth::requireRole('Candidate');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/jobs');
        }

        $jobId = (int)($_POST['job_id'] ?? 0);
        $pdo   = DB::conn();
        $cid   = (int)($_SESSION['user']['id'] ?? 0);

        // Duplicate check (block repeat)
        $du = $pdo->prepare("SELECT applicant_id, application_status, application_date
                         FROM applications WHERE candidate_id=:cid AND job_posting_id=:jid LIMIT 1");
        $du->execute([':cid' => $cid, ':jid' => $jobId]);
        if ($du->fetch()) {
            $this->flash('info', 'You have already applied to this job.');
            $this->redirect('/jobs/' . $jobId);
        }

        // Load 3 questions
        $qrows = $pdo->prepare("
      SELECT mq.id, mq.prompt
      FROM job_micro_questions jmq
      JOIN micro_questions mq ON mq.id = jmq.question_id
      WHERE jmq.job_posting_id = :id
      ORDER BY mq.id ASC
    ");
        $qrows->execute([':id' => $jobId]);
        $qrows = $qrows->fetchAll() ?: [];

        // Validate answers
        $answers = [];
        foreach ($qrows as $q) {
            $key = 'answer_' . $q['id'];
            $txt = trim((string)($_POST[$key] ?? ''));
            $answers[] = ['qid' => (int)$q['id'], 'text' => $txt];
        }
        $errors = [];
        foreach ($answers as $a) {
            if ($a['text'] === '') $errors['answer_' . $a['qid']] = 'Please provide an answer.';
            elseif (mb_strlen($a['text']) > 1000) $errors['answer_' . $a['qid']] = 'Max 1000 characters.';
        }
        if ($errors) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $_POST;
            $_SESSION['open_apply_modal'] = true;
            $this->flash('danger', 'Please correct the errors below.');
            $this->redirect('/jobs/' . $jobId);
        }

        // Create application + answers
        $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');

            $ins = $pdo->prepare("INSERT INTO applications
          (candidate_id, job_posting_id, application_date, application_status, resume_url, cover_letter, notes, updated_at)
          VALUES (:cid, :jid, :ad, 'Applied', NULL, NULL, NULL, :ua)");
            $ins->execute([':cid' => $cid, ':jid' => $jobId, ':ad' => $now, ':ua' => $now]);

            $appId = (int)$pdo->lastInsertId();
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

        $this->flash('success', 'Application submitted.');
        $this->redirect('/jobs/' . $jobId);
    }

    /** GET /applications — Candidate’s applications with filters + pagination */
    public function index(array $params = []): void
    {
        Auth::requireRole('Candidate');

        $pdo = DB::conn();
        $cid = (int)($_SESSION['user']['id'] ?? 0);

        // Filters
        $status  = trim((string)($_GET['status'] ?? ''));
        $q       = trim((string)($_GET['q'] ?? ''));
        $perPage = max(1, min(50, (int)($_GET['per'] ?? 10)));
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $where = ['a.candidate_id = :cid'];
        $bind  = [':cid' => $cid];

        if ($status !== '') {
            $where[] = 'a.application_status = :st';
            $bind[':st'] = $status;
        }
        if ($q !== '') {
            $where[] = '(jp.job_title LIKE :q OR e.company_name LIKE :q)';
            $bind[':q'] = "%$q%";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $sqlCount = "
          SELECT COUNT(*) FROM applications a
          JOIN job_postings jp ON jp.job_posting_id = a.job_posting_id
          JOIN employers e     ON e.employer_id     = jp.company_id
          $whereSql
        ";
        $st = $pdo->prepare($sqlCount);
        $st->execute($bind);
        $total = (int)$st->fetchColumn();

        // Page data
        $sql = "
          SELECT a.applicant_id, a.application_status, a.application_date,
                 jp.job_posting_id, jp.job_title, jp.job_location, jp.employment_type,
                 e.company_name, e.company_logo
          FROM applications a
          JOIN job_postings jp ON jp.job_posting_id = a.job_posting_id
          JOIN employers e     ON e.employer_id     = jp.company_id
          $whereSql
          ORDER BY a.application_date DESC, a.applicant_id DESC
          LIMIT :lim OFFSET :off
        ";
        $st = $pdo->prepare($sql);
        foreach ($bind as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', $offset,  PDO::PARAM_INT);
        $st->execute();
        $applications = $st->fetchAll() ?: [];

        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) $page = $pages;

        $root    = dirname(__DIR__, 2);
        $title   = 'My Applications — HireMe';
        $viewFile = $root . '/app/Views/applications/index.php';
        $filters = ['status' => $status, 'q' => $q, 'per' => $perPage, 'page' => $page, 'total' => $total];
        require $root . '/app/Views/layout.php';
    }
}
