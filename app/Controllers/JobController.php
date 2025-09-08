<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use PDO;
use Throwable;

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

    /** GET /jobs (public list) */
    public function index(array $params = []): void
    {
        $pdo = DB::conn();
        $sql = "
        SELECT jp.*, e.company_name, e.company_logo
        FROM job_postings jp
        JOIN employers e ON e.employer_id = jp.company_id
        WHERE jp.status = 'Open'
        ORDER BY jp.date_posted DESC, jp.job_posting_id DESC
        LIMIT 50";
        $jobs = $pdo->query($sql)->fetchAll() ?: [];

        $root = dirname(__DIR__, 2);
        $title = 'Jobs — HireMe';
        $viewFile = $root . '/app/Views/jobs/index.php';
        require $root . '/app/Views/layout.php';
    }

    /** GET /jobs/create (Employer only) */
    public function create(array $params = []): void
    {
        \App\Core\Auth::requireRole('Employer');

        $pdo = DB::conn();
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $employer = $pdo->query("SELECT * FROM employers WHERE employer_id = " . (int)$uid)->fetch() ?: [];

        $root = dirname(__DIR__, 2);
        $title = 'Post a Job — HireMe';
        $viewFile = $root . '/app/Views/jobs/create.php';
        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        $csrf   = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    /** POST /jobs (Employer only) */
    public function store(array $params = []): void
    {
        \App\Core\Auth::requireRole('Employer');
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
        $date    = date('Y-m-d H:i:s');

        $errors = [];
        if ($title === '') $errors['job_title'] = 'Job title is required.';
        if ($desc  === '') $errors['job_description'] = 'Description is required.';
        if ($salary !== '' && !is_numeric($salary)) $errors['salary'] = 'Salary must be numeric (e.g., 3500).';

        if ($errors) {
            $this->setErrors($errors);
            $this->setOld($_POST);
            $this->redirect('/jobs/create');
        }

        $pdo = DB::conn();
        $uid = (int)($_SESSION['user']['id'] ?? 0);

        $sql = "INSERT INTO job_postings
          (company_id, recruiter_id, job_title, job_description, job_requirements, job_location, job_languages, employment_type, salary_range_min, salary_range_max, application_deadline, date_posted, status, number_of_positions, required_experience, education_level, created_at, updated_at)
          VALUES
          (:cid, NULL, :title, :desc, :reqs, :loc, :langs, :etype, :smin, NULL, NULL, :posted, 'Open', 1, NULL, NULL, :ca, :ua)";
        $st = $pdo->prepare($sql);
        $now = $date;
        $st->execute([
            ':cid'    => $uid,
            ':title'  => $title,
            ':desc'   => $desc,
            ':reqs'   => null,
            ':loc'    => $loc ?: null,
            ':langs'  => $langs ?: null,           // requires 006 migration; else set to null and keep languages inside desc
            ':etype'  => $empType ?: 'Full-time',
            ':smin'   => ($salary === '' ? null : number_format((float)$salary, 2, '.', '')),
            ':posted' => $date,
            ':ca'     => $now,
            ':ua'     => $now,
        ]);

        $id = (int)$pdo->lastInsertId();
        $this->flash('success', 'Job created.');
        $this->redirect('/jobs/' . $id);
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

        $root = dirname(__DIR__, 2);
        $title = htmlspecialchars($job['job_title']) . ' — ' . $job['company_name'] . ' | HireMe';
        $viewFile = $root . '/app/Views/jobs/show.php';
        require $root . '/app/Views/layout.php';
    }
}
