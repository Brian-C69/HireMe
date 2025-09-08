<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use PDO;

final class JobController
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
    private function redirect(string $path): void
    {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . $path, true, 302);
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

    /** GET /jobs/create (Employer only for now) */
    public function create(array $params = []): void
    {
        Auth::requireRole('Employer');

        $pdo = DB::conn();
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $employer = $pdo->query("SELECT * FROM employers WHERE employer_id = " . (int)$uid)->fetch() ?: [];

        // Micro Interview question bank (8 defaults, active)
        $qbank = $pdo->query("SELECT id, prompt FROM micro_questions WHERE active = 1 ORDER BY id ASC")->fetchAll() ?: [];

        $root   = dirname(__DIR__, 2);
        $title  = 'Post a Job — HireMe';
        $viewFile = $root . '/app/Views/jobs/create.php';
        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        $csrf   = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    /** POST /jobs (Employer only for now) */
    public function store(array $params = []): void
    {
        Auth::requireRole('Employer');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/jobs/create');
        }

        $title   = trim((string)($_POST['job_title'] ?? ''));
        $desc    = trim((string)($_POST['job_description'] ?? ''));
        $loc     = trim((string)($_POST['job_location'] ?? ''));
        $langs   = trim((string)($_POST['job_languages'] ?? ''));
        $salary  = (string)($_POST['salary'] ?? '');
        $empType = trim((string)($_POST['employment_type'] ?? 'Full-time'));

        // Micro Interview selection (must be exactly 3)
        $chosen = array_values(array_filter((array)($_POST['mi_questions'] ?? []), fn($v) => ctype_digit((string)$v)));
        $chosen = array_unique(array_map('intval', $chosen));

        $errors = [];
        if ($title === '') $errors['job_title'] = 'Job title is required.';
        if ($desc  === '') $errors['job_description'] = 'Description is required.';
        if ($salary !== '' && !is_numeric($salary)) $errors['salary'] = 'Salary must be numeric (e.g., 3500).';
        if (count($chosen) !== 3) $errors['mi_questions'] = 'Please select exactly 3 questions.';

        if ($errors) {
            $this->setErrors($errors);
            $this->setOld($_POST);
            $this->redirect('/jobs/create');
        }

        $pdo = DB::conn();
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            // Insert job
            $sql = "INSERT INTO job_postings
              (company_id, recruiter_id, job_title, job_description, job_requirements, job_location, job_languages,
               employment_type, salary_range_min, salary_range_max, application_deadline, date_posted, status,
               number_of_positions, required_experience, education_level, created_at, updated_at)
              VALUES
              (:cid, NULL, :title, :desc, :reqs, :loc, :langs, :etype, :smin, NULL, NULL, :posted, 'Open',
               1, NULL, NULL, :ca, :ua)";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':cid'    => $uid,
                ':title'  => $title,
                ':desc'   => $desc,
                ':reqs'   => null,
                ':loc'    => $loc ?: null,
                ':langs'  => $langs ?: null,
                ':etype'  => $empType ?: 'Full-time',
                ':smin'   => ($salary === '' ? null : number_format((float)$salary, 2, '.', '')),
                ':posted' => $now,
                ':ca'     => $now,
                ':ua'     => $now,
            ]);

            $jobId = (int)$pdo->lastInsertId();

            // Attach 3 selected micro questions
            $ins = $pdo->prepare("INSERT INTO job_micro_questions (job_posting_id, question_id) VALUES (:jid, :qid)");
            foreach ($chosen as $qid) {
                $ins->execute([':jid' => $jobId, ':qid' => $qid]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('danger', 'Could not create job.');
            $this->redirect('/jobs/create');
        }

        $this->flash('success', 'Job created.');
        $this->redirect('/jobs/' . $jobId);
    }

    /** GET /jobs/{id} (public) */
    public function show(array $params = []): void
    {
        $id  = (int)($params['id'] ?? 0);
        $pdo = DB::conn();

        $st = $pdo->prepare("
          SELECT jp.*, e.company_name, e.company_logo
          FROM job_postings jp
          JOIN employers e ON e.employer_id = jp.company_id
          WHERE jp.job_posting_id = :id LIMIT 1
        ");
        $st->execute([':id' => $id]);
        $job = $st->fetch();

        if (!$job) {
            http_response_code(404);
            $root = dirname(__DIR__, 2);
            require $root . '/app/Views/errors/404.php';
            return;
        }

        // Load the attached 3 micro questions
        $qs = $pdo->prepare("
          SELECT mq.id, mq.prompt
          FROM job_micro_questions jmq
          JOIN micro_questions mq ON mq.id = jmq.question_id
          WHERE jmq.job_posting_id = :id
          ORDER BY mq.id ASC
        ");
        $qs->execute([':id' => $id]);
        $questions = $qs->fetchAll() ?: [];

        $root   = dirname(__DIR__, 2);
        $title  = htmlspecialchars($job['job_title']) . ' — ' . $job['company_name'] . ' | HireMe';
        $viewFile = $root . '/app/Views/jobs/show.php';
        require $root . '/app/Views/layout.php';
    }

    /** GET /jobs — with search, filters, pagination */
    public function index(array $params = []): void
    {
        $pdo = DB::conn();

        $q        = trim((string)($_GET['q'] ?? ''));
        $loc      = trim((string)($_GET['location'] ?? ''));
        $type     = trim((string)($_GET['type'] ?? ''));
        $company  = trim((string)($_GET['company'] ?? ''));
        $minSal   = (string)($_GET['min_salary'] ?? '');
        $langs    = trim((string)($_GET['languages'] ?? ''));
        $perPage  = max(1, min(50, (int)($_GET['per'] ?? 10)));
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $offset   = ($page - 1) * $perPage;

        $where  = ["jp.status = 'Open'"];
        $bind   = [];

        if ($q !== '') {
            $where[] = "(jp.job_title LIKE :q OR jp.job_description LIKE :q OR e.company_name LIKE :q)";
            $bind[':q'] = "%$q%";
        }
        if ($loc !== '') {
            $where[] = "jp.job_location LIKE :loc";
            $bind[':loc'] = "%$loc%";
        }
        if ($type !== '') {
            $where[] = "jp.employment_type = :type";
            $bind[':type'] = $type;
        }
        if ($company !== '') {
            $where[] = "e.company_name LIKE :company";
            $bind[':company'] = "%$company%";
        }
        if ($minSal !== '' && is_numeric($minSal)) {
            $where[] = "jp.salary_range_min >= :minsal";
            $bind[':minsal'] = (float)$minSal;
        }
        if ($langs !== '') {
            $where[] = "jp.job_languages LIKE :langs";
            $bind[':langs'] = "%$langs%";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sqlCount = "SELECT COUNT(*) FROM job_postings jp JOIN employers e ON e.employer_id = jp.company_id $whereSql";
        $st = $pdo->prepare($sqlCount);
        $st->execute($bind);
        $total = (int)($st->fetchColumn() ?: 0);

        $sql = "
          SELECT jp.*, e.company_name, e.company_logo
          FROM job_postings jp
          JOIN employers e ON e.employer_id = jp.company_id
          $whereSql
          ORDER BY jp.date_posted DESC, jp.job_posting_id DESC
          LIMIT :limit OFFSET :offset
        ";
        $st = $pdo->prepare($sql);
        foreach ($bind as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $st->execute();
        $jobs = $st->fetchAll() ?: [];

        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        $root   = dirname(__DIR__, 2);
        $title  = 'Jobs — HireMe';
        $viewFile = $root . '/app/Views/jobs/index.php';
        $filters = ['q' => $q, 'location' => $loc, 'type' => $type, 'company' => $company, 'min_salary' => $minSal, 'languages' => $langs, 'per' => $perPage, 'page' => $page];
        require $root . '/app/Views/layout.php';
    }

    public function mine(array $params = []): void
    {
        \App\Core\Auth::requireRole(['Employer', 'Recruiter']);

        $pdo  = DB::conn();
        $uid  = (int)($_SESSION['user']['id'] ?? 0);
        $role = $_SESSION['user']['role'] ?? '';
        $stat = trim((string)($_GET['status'] ?? '')); // optional filter

        $where = [];
        $bind = [];
        if ($role === 'Employer') {
            $where[] = 'jp.company_id = :me';
            $bind[':me'] = $uid;
        }
        if ($role === 'Recruiter') {
            $where[] = 'jp.recruiter_id = :me';
            $bind[':me'] = $uid;
        }
        if ($stat !== '') {
            $where[] = 'jp.status = :st';
            $bind[':st'] = $stat;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "
          SELECT jp.*, e.company_name, e.company_logo
          FROM job_postings jp
          JOIN employers e ON e.employer_id = jp.company_id
          $whereSql
          ORDER BY jp.date_posted DESC, jp.job_posting_id DESC
          LIMIT 200
        ";
        $st = $pdo->prepare($sql);
        $st->execute($bind);
        $jobs = $st->fetchAll() ?: [];

        $root = dirname(__DIR__, 2);
        $title = 'My Jobs — HireMe';
        $viewFile = $root . '/app/Views/jobs/mine.php';
        $statuses = $this->statusOptions();
        require $root . '/app/Views/layout.php';
    }

    /** POST /jobs/{id}/status — change status (Open, Paused, Suspended, Fulfilled, Closed, Deleted) */
    public function updateStatus(array $params = []): void
    {
        \App\Core\Auth::requireRole(['Employer', 'Recruiter']);
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/jobs/mine');
        }

        $id = (int)($params['id'] ?? 0);
        $new = trim((string)($_POST['status'] ?? ''));
        if (!in_array($new, $this->statusOptions(), true)) {
            $this->flash('danger', 'Invalid status.');
            $this->redirect('/jobs/mine');
        }

        $pdo = DB::conn();
        if (!$this->ownJob($pdo, $id)) {
            $this->flash('danger', 'Not authorized.');
            $this->redirect('/jobs/mine');
        }

        $pdo->prepare("UPDATE job_postings SET status=:s, updated_at=:u WHERE job_posting_id=:id")
            ->execute([':s' => $new, ':u' => date('Y-m-d H:i:s'), ':id' => $id]);

        $this->flash('success', 'Status updated.');
        $this->redirect('/jobs/mine');
    }

    /** POST /jobs/{id}/delete — soft delete (status=Deleted) */
    public function destroy(array $params = []): void
    {
        \App\Core\Auth::requireRole(['Employer', 'Recruiter']);
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/jobs/mine');
        }

        $id = (int)($params['id'] ?? 0);
        $pdo = DB::conn();
        if (!$this->ownJob($pdo, $id)) {
            $this->flash('danger', 'Not authorized.');
            $this->redirect('/jobs/mine');
        }

        $pdo->prepare("UPDATE job_postings SET status='Deleted', updated_at=:u WHERE job_posting_id=:id")
            ->execute([':u' => date('Y-m-d H:i:s'), ':id' => $id]);

        $this->flash('success', 'Job deleted.');
        $this->redirect('/jobs/mine');
    }

    /** Allowed statuses */
    private function statusOptions(): array
    {
        return ['Open', 'Paused', 'Suspended', 'Fulfilled', 'Closed', 'Deleted'];
    }

    /** Ensure current user owns this job */
    private function ownJob(PDO $pdo, int $jobId): ?array
    {
        if ($jobId <= 0) return null;
        $st = $pdo->prepare("SELECT * FROM job_postings WHERE job_posting_id=:id LIMIT 1");
        $st->execute([':id' => $jobId]);
        $job = $st->fetch();
        if (!$job) return null;

        $role = $_SESSION['user']['role'] ?? '';
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        if ($role === 'Employer'  && (int)$job['company_id']   === $uid) return $job;
        if ($role === 'Recruiter' && (int)($job['recruiter_id'] ?? 0) === $uid) return $job;
        return null;
    }
}
