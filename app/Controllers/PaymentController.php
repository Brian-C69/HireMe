<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;

final class PaymentController
{
    private function flash(string $t, string $m): void
    {
        $_SESSION['flash'] = ['type' => $t, 'message' => $m];
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

    /** GET /premium — product page */
    public function showPremium(array $params = []): void
    {
        Auth::requireRole('Candidate');

        $pdo = DB::conn();
        $id  = (int)($_SESSION['user']['id'] ?? 0);
        $st  = $pdo->prepare("SELECT premium_badge, premium_badge_date FROM candidates WHERE candidate_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $me  = $st->fetch() ?: [];

        $root   = dirname(__DIR__, 2);
        $title  = 'Go Premium — HireMe';
        $viewFile = $root . '/app/Views/payment/premium.php';
        $price  = 50.00; // RM50
        $csrf   = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    /** POST /premium/pay — mock “success”, mark premium + record payment/billing */
    public function payPremium(array $params = []): void
    {
        Auth::requireRole('Candidate');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/premium');
        }

        $pdo = DB::conn();
        $id  = (int)($_SESSION['user']['id'] ?? 0);

        // Already premium?
        $cur = $pdo->prepare("SELECT premium_badge FROM candidates WHERE candidate_id=:id LIMIT 1");
        $cur->execute([':id' => $id]);
        $isPremium = (bool)($cur->fetchColumn());

        if ($isPremium) {
            $this->flash('info', 'You already have a Premium badge.');
            $this->redirect('/premium');
        }

        $now   = date('Y-m-d H:i:s');
        $price = 50.00;
        $txid  = 'PM-' . strtoupper(bin2hex(random_bytes(6)));

        $pdo->beginTransaction();
        try {
            // Payments
            $pdo->prepare("
              INSERT INTO payments (user_type, user_id, amount, purpose, payment_method, transaction_status, transaction_id, created_at)
              VALUES ('Candidate', :uid, :amt, 'Premium Badge', 'Test', 'Success', :tx, :ts)
            ")->execute([':uid' => $id, ':amt' => $price, ':tx' => $txid, ':ts' => $now]);

            // Billing (optional)
            $pdo->prepare("
              INSERT INTO billing (user_id, user_type, transaction_type, amount, payment_method, transaction_date, status, reference_number, created_at, updated_at)
              VALUES (:uid, 'Candidate', 'Premium Badge Purchase', :amt, 'Test', :ts, 'Completed', :tx, :ts, :ts)
            ")->execute([':uid' => $id, ':amt' => $price, ':tx' => $txid, ':ts' => $now]);

            // Candidate flag
            $pdo->prepare("UPDATE candidates SET premium_badge=1, premium_badge_date=:d, updated_at=:d WHERE candidate_id=:id")
                ->execute([':d' => $now, ':id' => $id]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('danger', 'Payment failed. Please try again.');
            $this->redirect('/premium');
        }

        $this->flash('success', 'Premium badge activated!');
        $this->redirect('/premium');
    }
}
