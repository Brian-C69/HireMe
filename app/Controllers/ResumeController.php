<?php
// app/Controllers/ResumeController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use PDO;
use Throwable;
use Dompdf\Dompdf;
use Dompdf\Options;

final class ResumeController
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
    private function csrfOk(): bool
    {
        return isset($_POST['csrf']) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
    }

    public function edit(array $params = []): void
    {
        Auth::requireRole('Candidate');

        $root     = dirname(__DIR__, 2);
        $title    = 'Build / Update Resume — HireMe';
        $viewFile = $root . '/app/Views/resume/edit.php';

        $pdo = DB::conn();
        $uid = (int)($_SESSION['user']['id'] ?? 0);

        $candidate = $pdo->query("SELECT * FROM candidates WHERE candidate_id = " . (int)$uid)->fetch() ?: [];

        $experiences = $this->fetchAll($pdo, "SELECT * FROM candidate_experiences WHERE candidate_id = :id ORDER BY start_date DESC, id DESC", [':id' => $uid]);
        $skills      = $this->fetchAll($pdo, "SELECT * FROM candidate_skills WHERE candidate_id = :id ORDER BY level DESC, name ASC", [':id' => $uid]);
        $languages   = $this->fetchAll($pdo, "SELECT * FROM candidate_languages WHERE candidate_id = :id ORDER BY language ASC", [':id' => $uid]);
        $education   = $this->fetchAll($pdo, "SELECT * FROM candidate_education WHERE candidate_id = :id ORDER BY graduation_year DESC, id DESC", [':id' => $uid]);

        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        require $root . '/app/Views/layout.php';
    }

    public function update(array $params = []): void
    {
        Auth::requireRole('Candidate');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/resume');
        }

        $pdo = DB::conn();
        $uid = (int)($_SESSION['user']['id'] ?? 0);

        // Scalar fields from candidate table
        $full_name       = trim((string)($_POST['full_name'] ?? ''));
        $phone           = trim((string)($_POST['phone_number'] ?? ''));
        $dob             = (string)($_POST['date_of_birth'] ?? '');
        $expected_salary = (string)($_POST['expected_salary'] ?? '');
        $linkedin_url    = trim((string)($_POST['linkedin_url'] ?? ''));
        $location        = trim((string)($_POST['location'] ?? ''));
        $notice_period   = trim((string)($_POST['notice_period'] ?? ''));

        // Collections (arrays)
        $exp_company  = $_POST['exp_company']  ?? [];
        $exp_title    = $_POST['exp_title']    ?? [];
        $exp_from     = $_POST['exp_from']     ?? [];
        $exp_to       = $_POST['exp_to']       ?? [];
        $exp_desc     = $_POST['exp_desc']     ?? [];

        $skill_name   = $_POST['skill_name']   ?? [];
        $skill_level  = $_POST['skill_level']  ?? [];

        $lang_name    = $_POST['lang_name']    ?? [];
        $lang_spoken  = $_POST['lang_spoken']  ?? [];
        $lang_written = $_POST['lang_written'] ?? [];

        $edu_qual     = $_POST['edu_qual']     ?? [];
        $edu_school   = $_POST['edu_school']   ?? [];
        $edu_field    = $_POST['edu_field']    ?? [];
        $edu_year     = $_POST['edu_year']     ?? [];
        $edu_details  = $_POST['edu_details']  ?? [];

        // Validation (focused)
        $errors = [];
        if ($full_name === '') $errors['full_name'] = 'Full name is required.';
        if ($expected_salary !== '' && !is_numeric($expected_salary)) $errors['expected_salary'] = 'Salary must be numeric.';
        if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) $errors['date_of_birth'] = 'Use YYYY-MM-DD.';
        if ($linkedin_url !== '' && !filter_var($linkedin_url, FILTER_VALIDATE_URL)) $errors['linkedin_url'] = 'Invalid URL.';

        // Normalize skills levels
        $skillLevelOk = static fn($v) => is_numeric($v) && (int)$v >= 0 && (int)$v <= 100;
        foreach ((array)$skill_level as $i => $lvl) {
            if ($skill_name[$i] ?? '' === '') continue;
            if (!$skillLevelOk($lvl)) {
                $errors['skill_level_' . $i] = 'Skill level must be 0–100.';
            }
        }

        if ($errors) {
            $this->setErrors($errors);
            $this->setOld($_POST);
            $this->redirect('/resume');
        }

        $now = date('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();

            // Update candidate scalars
            $upd = $pdo->prepare("
                UPDATE candidates SET
                  full_name = :full_name,
                  phone_number = :phone,
                  date_of_birth = :dob,
                  expected_salary = :salary,
                  linkedin_url = :linkedin,
                  location = :location,
                  notice_period = :notice,
                  updated_at = :ua
                WHERE candidate_id = :id
            ");
            $upd->execute([
                ':full_name' => $full_name,
                ':phone'     => $phone ?: null,
                ':dob'       => ($dob === '' ? null : $dob),
                ':salary'    => ($expected_salary === '' ? null : number_format((float)$expected_salary, 2, '.', '')),
                ':linkedin'  => ($linkedin_url === '' ? null : $linkedin_url),
                ':location'  => ($location === '' ? null : $location),
                ':notice'    => ($notice_period === '' ? null : $notice_period),
                ':ua'        => $now,
                ':id'        => $uid,
            ]);

            // Wipe and insert experiences
            $pdo->prepare("DELETE FROM candidate_experiences WHERE candidate_id=:id")->execute([':id' => $uid]);
            $insExp = $pdo->prepare("
                INSERT INTO candidate_experiences (candidate_id, company, job_title, start_date, end_date, description, created_at, updated_at)
                VALUES (:cid, :company, :title, :from, :to, :desc, :ca, :ua)
            ");
            foreach ((array)$exp_company as $i => $company) {
                $company = trim((string)$company);
                $title   = trim((string)($exp_title[$i] ?? ''));
                if ($company === '' && $title === '') continue;
                $from = (string)($exp_from[$i] ?? '');
                $to   = (string)($exp_to[$i] ?? '');
                $desc = trim((string)($exp_desc[$i] ?? ''));
                $insExp->execute([
                    ':cid' => $uid,
                    ':company' => $company,
                    ':title' => $title,
                    ':from' => ($from === '' ? null : $from),
                    ':to'  => ($to   === '' ? null : $to),
                    ':desc' => $desc,
                    ':ca' => $now,
                    ':ua' => $now
                ]);
            }

            // Wipe and insert skills
            $pdo->prepare("DELETE FROM candidate_skills WHERE candidate_id=:id")->execute([':id' => $uid]);
            $insSkill = $pdo->prepare("
                INSERT INTO candidate_skills (candidate_id, name, level, created_at, updated_at)
                VALUES (:cid, :name, :level, :ca, :ua)
            ");
            foreach ((array)$skill_name as $i => $name) {
                $name = trim((string)$name);
                $lvl  = (int)($skill_level[$i] ?? 0);
                if ($name === '') continue;
                $lvl = max(0, min(100, $lvl));
                $insSkill->execute([':cid' => $uid, ':name' => $name, ':level' => $lvl, ':ca' => $now, ':ua' => $now]);
            }

            // Wipe and insert languages
            $pdo->prepare("DELETE FROM candidate_languages WHERE candidate_id=:id")->execute([':id' => $uid]);
            $insLang = $pdo->prepare("
                INSERT INTO candidate_languages (candidate_id, language, spoken_level, written_level, created_at, updated_at)
                VALUES (:cid, :lang, :spoken, :written, :ca, :ua)
            ");
            foreach ((array)$lang_name as $i => $lang) {
                $lang = trim((string)$lang);
                if ($lang === '') continue;
                $spoken  = (string)($lang_spoken[$i]  ?? 'Basic');
                $written = (string)($lang_written[$i] ?? 'Basic');
                $insLang->execute([':cid' => $uid, ':lang' => $lang, ':spoken' => $spoken, ':written' => $written, ':ca' => $now, ':ua' => $now]);
            }

            // Wipe and insert education
            $pdo->prepare("DELETE FROM candidate_education WHERE candidate_id=:id")->execute([':id' => $uid]);
            $insEdu = $pdo->prepare("
                INSERT INTO candidate_education (candidate_id, qualification, institution, field, graduation_year, details, created_at, updated_at)
                VALUES (:cid, :q, :inst, :field, :yr, :details, :ca, :ua)
            ");
            foreach ((array)$edu_qual as $i => $q) {
                $q = (string)$q;
                if ($q === '') continue;
                $inst = trim((string)($edu_school[$i] ?? ''));
                $field = trim((string)($edu_field[$i] ?? ''));
                $yr   = (string)($edu_year[$i] ?? '');
                $det  = trim((string)($edu_details[$i] ?? ''));
                $insEdu->execute([
                    ':cid' => $uid,
                    ':q' => $q,
                    ':inst' => $inst ?: null,
                    ':field' => $field ?: null,
                    ':yr' => ($yr === '' ? null : $yr),
                    ':details' => $det ?: null,
                    ':ca' => $now,
                    ':ua' => $now
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $this->flash('danger', 'Could not save resume.');
            $this->setOld($_POST);
            $this->redirect('/resume');
        }

        $this->flash('success', 'Resume updated.');
        $this->redirect('/resume');
    }

    public function exportPdf(array $params = []): void
    {
        Auth::requireRole('Candidate');

        $pdo = DB::conn();
        $uid = (int)($_SESSION['user']['id'] ?? 0);

        // Fetch data (same as edit())
        $candidate  = $pdo->query("SELECT * FROM candidates WHERE candidate_id = " . (int)$uid)->fetch() ?: [];
        $experiences = $this->fetchAll($pdo, "SELECT * FROM candidate_experiences WHERE candidate_id = :id ORDER BY start_date DESC, id DESC", [':id' => $uid]);
        $skills      = $this->fetchAll($pdo, "SELECT * FROM candidate_skills WHERE candidate_id = :id ORDER BY level DESC, name ASC", [':id' => $uid]);
        $languages   = $this->fetchAll($pdo, "SELECT * FROM candidate_languages WHERE candidate_id = :id ORDER BY language ASC", [':id' => $uid]);
        $education   = $this->fetchAll($pdo, "SELECT * FROM candidate_education WHERE candidate_id = :id ORDER BY graduation_year DESC, id DESC", [':id' => $uid]);

        // Render HTML from a PDF-friendly view (no external CSS)
        $root = dirname(__DIR__, 2);
        $pdfView = $root . '/app/Views/resume/pdf.php';

        // Provide base path for assets (profile photo)
        $publicPath = $root . '/public';
        $profileLocal = null;
        if (!empty($candidate['profile_picture_url'])) {
            $rel = ltrim((string)$candidate['profile_picture_url'], '/'); // assets/uploads/profiles/...
            $local = $publicPath . '/' . $rel;
            if (is_file($local)) $profileLocal = $rel; // used with dompdf chroot
        }

        // Buffer the view output
        ob_start();
        $data = [
            'candidate'     => $candidate,
            'experiences'   => $experiences,
            'skills'        => $skills,
            'languages'     => $languages,
            'education'     => $education,
            'profileLocal'  => $profileLocal,
        ];
        extract($data, EXTR_SKIP);
        require $pdfView;
        $html = ob_get_clean();

        // Dompdf boot
        $dompdfPath = $root . '/app/Lib/dompdf/autoload.inc.php';
        if (!is_file($dompdfPath)) {
            http_response_code(500);
            echo 'Dompdf not found. Place dompdf in app/Lib/dompdf/';
            return;
        }
        require_once $dompdfPath;

        $options = new Options();
        $options->set('isRemoteEnabled', true);          // allow http images if needed
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', $publicPath);            // local assets under /public
        $options->set('defaultFont', 'DejaVu Sans');     // wide unicode coverage

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $filename = 'HireMe_Resume_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)($candidate['full_name'] ?? 'Candidate')) . '_' . date('Ymd') . '.pdf';
        $download = isset($_GET['download']) && $_GET['download'] == '1';

        // Stream to browser (no file saved on server)
        $dompdf->stream($filename, ['Attachment' => $download]);
        exit;
    }

    private function fetchAll(PDO $pdo, string $sql, array $args = []): array
    {
        $st = $pdo->prepare($sql);
        $st->execute($args);
        return $st->fetchAll() ?: [];
    }
}
