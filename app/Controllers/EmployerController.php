<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Employer;
use Throwable;

final class EmployerController
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
        $b = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $b . $path, true, 302);
        exit;
    }
    private function csrfOk(): bool
    {
        return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
    }

    /** GET /company */
    public function edit(array $params = []): void
    {
        Auth::requireRole('Employer');

        $root = dirname(__DIR__, 2);
        $title = 'Company Profile — HireMe';
        $viewFile = $root . '/app/Views/employer/edit.php';
        $errors = $this->takeErrors();
        $old = $this->takeOld();

        $id = (int)($_SESSION['user']['id'] ?? 0);
        $employer = Employer::find($id);

        require $root . '/app/Views/layout.php';
    }

    /** POST /company */
    public function update(array $params = []): void
    {
        Auth::requireRole('Employer');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/company');
        }

        $id = (int)($_SESSION['user']['id'] ?? 0);
        $emp = Employer::find($id);
        if (!$emp) {
            $this->flash('danger', 'Employer not found.');
            $this->redirect('/company');
        }

        $section = (string)($_POST['section'] ?? 'details');
        $now = date('Y-m-d H:i:s');
        $root = dirname(__DIR__, 2);
        $logoDir = $root . '/public/assets/uploads/companies';
        if (!is_dir($logoDir)) @mkdir($logoDir, 0777, true);

        if ($section === 'logo') {
            $errors = [];
            $logoUrl = null;

            if (!empty($_FILES['company_logo']['name'])) {
                $up = $_FILES['company_logo'];
                if ($up['error'] === UPLOAD_ERR_OK) {
                    $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
                    $mime = mime_content_type($up['tmp_name']) ?: '';
                    if (!isset($ok[$mime])) {
                        $errors['company_logo'] = 'Only JPG or PNG allowed.';
                    } elseif ($up['size'] > 2 * 1024 * 1024) {
                        $errors['company_logo'] = 'Max 2MB.';
                    } else {
                        $ext = $ok[$mime];
                        $name = 'company_' . $id . '_' . time() . '.' . $ext;
                        $dest = $logoDir . '/' . $name;
                        if (!move_uploaded_file($up['tmp_name'], $dest)) {
                            $errors['company_logo'] = 'Upload failed.';
                        } else {
                            $logoUrl = '/assets/uploads/companies/' . $name;
                        }
                    }
                } else {
                    $errors['company_logo'] = 'Upload error.';
                }
            } else {
                $errors['company_logo'] = 'Please choose an image.';
            }

            if ($errors) {
                $this->setErrors($errors);
                $this->redirect('/company');
            }

            $emp->company_logo = $logoUrl;
            $emp->updated_at = $now;
            $emp->save();

            $this->flash('success', 'Company logo updated.');
            $this->redirect('/company');
        }

        // section === 'details'
        $company_name = trim((string)($_POST['company_name'] ?? ''));
        $industry = trim((string)($_POST['industry'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $contact_name = trim((string)($_POST['contact_person_name'] ?? ''));
        $contact_phone = trim((string)($_POST['contact_number'] ?? ''));
        $description = trim((string)($_POST['company_description'] ?? ''));

        $errors = [];
        if ($company_name === '') $errors['company_name'] = 'Company name is required.';
        if ($errors) {
            $this->setErrors($errors);
            $this->setOld($_POST);
            $this->redirect('/company');
        }

        $fields = [
            'company_name' => $company_name,
            'industry' => $industry ?: null,
            'location' => $location ?: null,
            'contact_person_name' => $contact_name ?: null,
            'contact_number' => $contact_phone ?: null,
            'company_description' => $description ?: null,
            'updated_at' => $now,
        ];
        $emp->fill($fields);
        $emp->save();

        $this->flash('success', 'Company profile updated.');
        $this->redirect('/company');
    }
}
