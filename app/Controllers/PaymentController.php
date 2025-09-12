<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use PDO;
use Throwable;

final class PaymentController
{
    /* ----------------------- Config helpers ----------------------- */
    private function cfg(): array
    {
        static $cfg = null;
        if ($cfg !== null) return $cfg;

        $root  = dirname(__DIR__, 2);
        $paths = [
            $root . '/config/config.php',     // your actual path
            $root . '/app/config/config.php', // fallback if you ever move it
            $root . '/config.php',            // last resort
        ];
        foreach ($paths as $p) {
            if (is_file($p)) {
                $data = require $p;
                return $cfg = is_array($data) ? $data : [];
            }
        }
        throw new \RuntimeException('Config file not found under /config or /app/config.');
    }

    private function cfgGet(string $dotKey, $default = null)
    {
        $node = $this->cfg();
        foreach (explode('.', $dotKey) as $k) {
            if (!is_array($node) || !array_key_exists($k, $node)) return $default;
            $node = $node[$k];
        }
        return $node;
    }

    /* ----------------------- UI helpers ----------------------- */
    private function flash(string $t, string $m): void
    {
        $_SESSION['flash'] = ['type' => $t, 'message' => $m];
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

    private function isDev(): bool
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        $env = strtolower((string)($this->cfgGet('app.env', getenv('APP_ENV')) ?? ''));
        $isEnvDev = in_array($env, ['dev', 'local', 'development'], true);

        $host = $_SERVER['HTTP_HOST']   ?? '';
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        $isLocalHost = (bool)preg_match('/(^|\.)(localhost|127\.0\.0\.1)(:\d+)?$/i', $host);
        $isLocalIP   = in_array($ip, ['127.0.0.1', '::1'], true);

        return $cached = ($isEnvDev || $isLocalHost || $isLocalIP);
    }

    private function absBaseUrl(): string
    {
        $base   = defined('BASE_URL') ? BASE_URL : '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return rtrim($scheme . '://' . $host . $base, '/');
    }

    private function stripeSecret(): string
    {
        // tolerate either key naming
        return (string)($this->cfgGet('stripe.secret_key', $this->cfgGet('stripe.secret', '')) ?? '');
    }
    private function stripePublishable(): string
    {
        return (string)($this->cfgGet('stripe.publishable_key', $this->cfgGet('stripe.publishable', '')) ?? '');
    }
    private function stripeCurrency(): string
    {
        return strtolower((string)($this->cfgGet('stripe.currency', 'myr') ?? 'myr'));
    }

    /* ----------------------- Credits ----------------------- */

    /** GET /credits */
    public function showCredits(array $params = []): void
    {
        Auth::requireRole(['Employer', 'Recruiter']);

        $root  = dirname(__DIR__, 2);
        $title = 'Buy Credits — HireMe';

        $viewFile = $root . '/app/Views/payment/credits.php';
        $errors   = $_SESSION['errors'] ?? [];
        unset($_SESSION['errors']);

        $csrf = $this->csrf();
        $publishableKey = $this->stripePublishable();
        require $root . '/app/Views/layout.php';
    }

    /** POST /credits/checkout */
    public function checkoutCredits(array $params = []): void
    {
        Auth::requireRole(['Employer', 'Recruiter']);
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/credits');
        }

        $qty = (int)($_POST['credits_qty'] ?? 0);
        $allowed = [5, 10, 50, 100, 250, 500];
        if (!in_array($qty, $allowed, true)) {
            $_SESSION['errors'] = ['credits_qty' => 'Please choose a valid credit amount.'];
            $this->redirect('/credits');
        }

        // Require Stripe SDK to be loaded via composer autoload (e.g. in public/index.php)
        \Stripe\Stripe::setApiKey($this->stripeSecret());

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $role   = (string)($_SESSION['user']['role'] ?? '');
        $amountSen = $qty * 100; // RM1 per credit (Stripe expects MYR in sen)

        $origin = $this->absBaseUrl();

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'currency' => $this->stripeCurrency(),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $this->stripeCurrency(),
                    'unit_amount' => $amountSen,
                    'product_data' => [
                        'name' => "HireMe Credits x{$qty}",
                        'description' => '1 credit = RM1',
                    ],
                ],
            ]],
            'success_url' => $this->absBaseUrl() . '/credits/success?sid={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $this->absBaseUrl() . '/credits/cancel',
            'metadata' => [
                'purpose'   => 'credits',
                'user_id'   => (string)$userId,
                'user_role' => $role,
                'credits'   => (string)$qty,
            ],
        ]);

        // Log
        $pdo = DB::conn();
        $now = date('Y-m-d H:i:s');
        $st = $pdo->prepare("
          INSERT INTO stripe_payments
            (user_id,user_role,purpose,credits,amount,currency,session_id,status,created_at,updated_at)
          VALUES
            (:uid,:role,'credits',:credits,:amount,:cur,:sid,'created',:ca,:ua)
        ");
        $st->execute([
            ':uid' => $userId,
            ':role' => $role,
            ':credits' => $qty,
            ':amount'  => $amountSen,
            ':cur'     => $this->stripeCurrency(),
            ':sid'     => $session->id,
            ':ca'      => $now,
            ':ua'      => $now,
        ]);

        header('Location: ' . $session->url, true, 303);
        exit;
    }

    private function viewPath(string $rel): string
    {
        $root = dirname(__DIR__, 2);
        $candidates = [
            $root . '/app/Views' . $rel, // preferred
            $root . '/view'      . $rel, // legacy
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) return $p;
        }
        // final fallback: just return the preferred path (layout will error if missing)
        return $root . '/app/Views' . $rel;
    }


    /** GET /credits/success */
    public function creditsSuccess(array $params = []): void
    {
        \App\Core\Auth::requireRole(['Employer', 'Recruiter']);

        $sid = (string)($_GET['sid'] ?? '');
        if ($sid === '') {
            $this->flash('warning', 'Missing Checkout Session ID.');
            $this->redirect('/credits');
        }

        $cfg = $this->cfg();
        \Stripe\Stripe::setApiKey((string)$cfg['stripe']['secret_key']);

        try {
            $session = \Stripe\Checkout\Session::retrieve([
                'id'     => $sid,
                'expand' => ['payment_intent'],
            ]);
        } catch (\Throwable $e) {
            $this->flash('danger', 'Could not verify payment with Stripe.');
            $this->redirect('/credits');
        }

        // Consider it paid only if Stripe says so
        $isPaid = ((string)$session->payment_status === 'paid');

        $pdo = \App\Core\DB::conn();

        // We logged this when creating the checkout session
        $st  = $pdo->prepare("SELECT * FROM stripe_payments WHERE session_id = :sid LIMIT 1");
        $st->execute([':sid' => $sid]);
        $row = $st->fetch();

        if (!$row) {
            $this->flash('danger', 'Payment session not found. Please contact support.');
            $this->redirect('/credits');
        }

        // If already processed, just show success page
        if ((string)$row['status'] === 'paid') {
            $root     = dirname(__DIR__, 2);
            $title    = 'Payment Successful — HireMe';
            $viewFile = $this->viewPath('/payment/success.php');
            require $root . '/app/Views/layout.php';
            return;
        }

        if (!$isPaid) {
            // Mark not paid for visibility
            $pdo->prepare("UPDATE stripe_payments SET status='not_paid', payload=:p, updated_at=NOW() WHERE session_id=:sid")
                ->execute([
                    ':p'   => json_encode($session->toArray(), JSON_UNESCAPED_SLASHES),
                    ':sid' => $sid
                ]);
            $this->flash('warning', 'Payment not completed.');
            $this->redirect('/credits');
        }

        // Proceed to finalize
        $pdo->beginTransaction();
        try {
            $paymentIntent = (string)($session->payment_intent->id ?? $session->payment_intent ?? '');
            $amountTotal   = (int)($session->amount_total ?? 0); // sen
            $currency      = (string)($session->currency ?? 'myr');

            // 1) Update payment row as paid (store serializable payload!)
            $pdo->prepare("
            UPDATE stripe_payments
               SET status='paid',
                   payment_intent=:pi,
                   amount=:amt,
                   currency=:cur,
                   payload=:payload,
                   updated_at=NOW()
             WHERE session_id=:sid
        ")->execute([
                ':pi'      => $paymentIntent,
                ':amt'     => $amountTotal,
                ':cur'     => $currency,
                ':payload' => json_encode($session->toArray(), JSON_UNESCAPED_SLASHES),
                ':sid'     => $sid,
            ]);

            // 2) Apply credits exactly once
            $credits = (int)($row['credits'] ?? 0);
            $userId  = (int)$row['user_id'];
            $role    = (string)$row['user_role'];

            if ($credits > 0 && in_array($role, ['Employer', 'Recruiter'], true)) {
                [$table, $pk] = $this->tableAndPkForRole($role);

                $pdo->prepare("
    UPDATE {$table}
       SET credits_balance = COALESCE(credits_balance, 0) + :c,
           updated_at = NOW()
     WHERE {$pk} = :id
     LIMIT 1
")->execute([':c' => $credits, ':id' => $userId]);

                // (optional) refresh session balance for navbar
                $sel = $pdo->prepare("SELECT credits_balance FROM {$table} WHERE {$pk} = :id LIMIT 1");
                $sel->execute([':id' => $userId]);
                $latest = (int)($sel->fetchColumn() ?: 0);
                $_SESSION['user']['credits_balance'] = $latest;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            // Show the real DB error if you're in dev
            $isDev = $this->isDev();
            $msg   = $isDev ? ('Payment processed but we could not finalize credits: ' . $e->getMessage())
                : 'Payment processed but we could not finalize credits. Please contact support.';
            $this->flash('danger', $msg);
            $this->redirect('/credits');
        }

        // Render success page
        $root     = dirname(__DIR__, 2);
        $title    = 'Payment Successful — HireMe';
        $viewFile = $this->viewPath('/payment/success.php');
        require $root . '/app/Views/layout.php';
    }




    /** GET /credits/cancel */
    public function creditsCancel(array $params = []): void
    {
        Auth::requireRole(['Employer', 'Recruiter']);
        $this->flash('warning', 'Payment canceled.');
        $this->redirect('/credits');
    }

    /* ----------------------- Webhook ----------------------- */

    /** POST /webhooks/stripe */
    public function webhook(array $params = []): void
    {
        $payload = file_get_contents('php://input') ?: '';
        $sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret  = (string)($this->cfgGet('stripe.webhook_secret', '') ?? '');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig, $secret);
        } catch (\UnexpectedValueException) {
            http_response_code(400);
            echo 'Invalid payload';
            return;
        } catch (\Stripe\Exception\SignatureVerificationException) {
            http_response_code(400);
            echo 'Bad signature';
            return;
        }

        if ($event->type === 'checkout.session.completed') {
            /** @var \Stripe\Checkout\Session $session */
            $session = $event->data->object;

            $purpose   = (string)($session->metadata->purpose ?? '');
            $userId    = (int)($session->metadata->user_id ?? 0);
            $userRole  = (string)($session->metadata->user_role ?? '');
            $credits   = (int)($session->metadata->credits ?? 0);
            $amount    = (int)$session->amount_total;
            $currency  = (string)$session->currency;
            $sid       = (string)$session->id;
            $pi        = (string)($session->payment_intent ?? '');

            $pdo = DB::conn();
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    UPDATE stripe_payments
                       SET status='paid', payment_intent=:pi, amount=:amt, currency=:cur, payload=:payload, updated_at=NOW()
                     WHERE session_id=:sid
                ")->execute([
                    ':pi' => $pi,
                    ':amt' => $amount,
                    ':cur' => $currency,
                    ':payload' => $payload,
                    ':sid' => $sid,
                ]);

                if ($purpose === 'credits' && $credits > 0 && in_array($userRole, ['Employer', 'Recruiter'], true)) {
                    $table = ($userRole === 'Employer') ? 'employers' : 'recruiters';
                    $pdo->prepare("UPDATE {$table}
                                      SET credits_balance = COALESCE(credits_balance,0) + :c, updated_at=NOW()
                                    WHERE {$table}_id=:id
                                    LIMIT 1")
                        ->execute([':c' => $credits, ':id' => $userId]);
                }

                if ($purpose === 'premium' && $userRole === 'Candidate') {
                    $pdo->prepare("UPDATE candidates
                                      SET premium_badge=1, premium_badge_date=NOW(), updated_at=NOW()
                                    WHERE candidate_id=:id
                                    LIMIT 1")
                        ->execute([':id' => $userId]);
                }

                $pdo->commit();
            } catch (Throwable) {
                $pdo->rollBack();
                http_response_code(500);
                echo 'Webhook processing error';
                return;
            }
        }

        http_response_code(200);
        echo 'ok';
    }

    /* ----------------------- Premium badge ----------------------- */

    /** GET /premium */
    public function showPremium(array $params = []): void
    {
        Auth::requireRole('Candidate');

        // If returning from Stripe with a session id, finalize now
        $sid = (string)($_GET['sid'] ?? '');
        if ($sid !== '') {
            $res = $this->finalizeCheckoutBySessionId($sid);
            if ($res['ok']) {
                $this->flash('success', 'Payment successful. Premium activated!');
            } else {
                $this->flash('warning', 'We could not confirm your payment automatically. If you completed payment, your badge will appear soon.');
            }
        }

        $pdo  = DB::conn();
        $id   = (int)($_SESSION['user']['id'] ?? 0);

        $st = $pdo->prepare("SELECT candidate_id, full_name, premium_badge FROM candidates WHERE candidate_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $me = $st->fetch() ?: [];

        $root   = dirname(__DIR__, 2);
        $title  = 'Go Premium — HireMe';
        $viewFile = $root . '/app/Views/payment/premium.php';

        $price  = 50.00;
        $csrf   = $this->csrf();
        $isDev  = $this->isDev();

        require $root . '/app/Views/layout.php';
    }

    /** POST /premium/pay */
    public function payPremium(array $params = []): void
    {
        Auth::requireRole('Candidate');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/premium');
        }

        \Stripe\Stripe::setApiKey($this->stripeSecret());

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $role   = (string)($_SESSION['user']['role'] ?? 'Candidate');
        $amountSen = 5000; // RM50.00

        $origin = $this->absBaseUrl();

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'currency' => $this->stripeCurrency(),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $this->stripeCurrency(),
                    'unit_amount' => $amountSen,
                    'product_data' => [
                        'name' => 'HireMe Premium Badge',
                        'description' => 'One-time premium badge (instant activation).',
                    ],
                ],
            ]],
            // After payment, land back on /premium; badge is applied via webhook
            'success_url' => $this->absBaseUrl() . '/premium?sid={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $this->absBaseUrl() . '/premium',
            'metadata' => [
                'purpose'   => 'premium',
                'user_id'   => (string)$userId,
                'user_role' => $role,
            ],
        ]);

        // Log
        $pdo = DB::conn();
        $now = date('Y-m-d H:i:s');
        $st = $pdo->prepare("
            INSERT INTO stripe_payments
                (user_id,user_role,purpose,credits,amount,currency,session_id,status,created_at,updated_at)
            VALUES
                (:uid,:role,'premium',NULL,:amount,:cur,:sid,'created',:ca,:ua)
        ");
        $st->execute([
            ':uid' => $userId,
            ':role' => $role,
            ':amount'  => $amountSen,
            ':cur'     => $this->stripeCurrency(),
            ':sid'     => $session->id,
            ':ca'      => $now,
            ':ua'      => $now,
        ]);

        header('Location: ' . $session->url, true, 303);
        exit;
    }

    /** POST /premium/unset  (dev helper) */
    public function revokePremium(array $params = []): void
    {
        Auth::requireRole('Candidate');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/premium');
        }
        if (!$this->isDev()) {
            $this->flash('danger', 'Dev-only action.');
            $this->redirect('/premium');
        }

        $pdo = DB::conn();
        $id  = (int)($_SESSION['user']['id'] ?? 0);
        $pdo->prepare("UPDATE candidates SET premium_badge=0, premium_badge_date=NULL, updated_at=NOW() WHERE candidate_id=:id LIMIT 1")
            ->execute([':id' => $id]);

        $_SESSION['user']['premium_badge'] = 0;
        $this->flash('success', 'Premium badge revoked (dev).');
        $this->redirect('/premium');
    }

    private function finalizeCheckoutBySessionId(string $sid): array
    {
        // Return shape: ['ok'=>bool, 'purpose'=>'credits|premium', 'updated'=>bool, 'message'=>string, 'credits'=>int]
        if ($sid === '') return ['ok' => false, 'purpose' => '', 'updated' => false, 'message' => 'Missing session id'];

        \Stripe\Stripe::setApiKey($this->stripeSecret());

        try {
            /** @var \Stripe\Checkout\Session $session */
            $session = \Stripe\Checkout\Session::retrieve($sid, []);
        } catch (\Throwable $e) {
            return ['ok' => false, 'purpose' => '', 'updated' => false, 'message' => 'Unable to retrieve Stripe session'];
        }

        // Only finalize if paid
        $paid = ($session->payment_status === 'paid');
        if (!$paid) {
            return ['ok' => false, 'purpose' => '', 'updated' => false, 'message' => 'Payment not completed'];
        }

        $purpose  = (string)($session->metadata->purpose ?? '');
        $userId   = (int)($session->metadata->user_id ?? 0);
        $userRole = (string)($session->metadata->user_role ?? '');
        $credits  = (int)($session->metadata->credits ?? 0);
        $amount   = (int)$session->amount_total;
        $currency = (string)$session->currency;
        $pi       = (string)($session->payment_intent ?? '');
        $payload  = json_encode($session);

        $pdo = DB::conn();
        $pdo->beginTransaction();
        try {
            // 1) Upsert/update the stripe_payments row and detect if we already processed
            $row = $pdo->prepare("SELECT status FROM stripe_payments WHERE session_id=:sid LIMIT 1");
            $row->execute([':sid' => $sid]);
            $status = (string)($row->fetchColumn() ?: '');

            // Update (or insert in case webhook never created it for some reason)
            if ($status === '') {
                $now = date('Y-m-d H:i:s');
                $ins = $pdo->prepare("
                INSERT INTO stripe_payments
                    (user_id,user_role,purpose,credits,amount,currency,session_id,payment_intent,status,payload,created_at,updated_at)
                VALUES
                    (:uid,:role,:purpose,:credits,:amount,:cur,:sid,:pi,'paid',:payload,:ca,:ua)
            ");
                $ins->execute([
                    ':uid' => $userId,
                    ':role' => $userRole,
                    ':purpose' => $purpose,
                    ':credits' => $credits,
                    ':amount' => $amount,
                    ':cur' => $currency,
                    ':sid' => $sid,
                    ':pi' => $pi,
                    ':payload' => $payload,
                    ':ca' => $now,
                    ':ua' => $now,
                ]);
                $alreadyProcessed = false;
            } else {
                $alreadyProcessed = ($status === 'paid');
                // Bring row to paid
                $pdo->prepare("
                UPDATE stripe_payments
                   SET status='paid', payment_intent=:pi, amount=:amt, currency=:cur, payload=:payload, updated_at=NOW()
                 WHERE session_id=:sid
            ")->execute([
                    ':pi' => $pi,
                    ':amt' => $amount,
                    ':cur' => $currency,
                    ':payload' => $payload,
                    ':sid' => $sid
                ]);
            }

            // 2) Apply side effects if not already applied
            $applied = false;
            if (!$alreadyProcessed) {
                if ($purpose === 'credits' && $credits > 0 && in_array($userRole, ['Employer', 'Recruiter'], true)) {
                    $table = ($userRole === 'Employer') ? 'employers' : 'recruiters';
                    $pdo->prepare("UPDATE {$table}
                                  SET credits_balance = COALESCE(credits_balance,0) + :c, updated_at=NOW()
                                WHERE {$table}_id=:id
                                LIMIT 1")
                        ->execute([':c' => $credits, ':id' => $userId]);
                    // reflect in session if current user
                    if (!empty($_SESSION['user']) && (int)$_SESSION['user']['id'] === $userId && $_SESSION['user']['role'] === $userRole) {
                        $_SESSION['user']['credits_balance'] = (int)(($_SESSION['user']['credits_balance'] ?? 0) + $credits);
                    }
                    $applied = true;
                } elseif ($purpose === 'premium' && $userRole === 'Candidate') {
                    $pdo->prepare("UPDATE candidates
                                  SET premium_badge=1, premium_badge_date=NOW(), updated_at=NOW()
                                WHERE candidate_id=:id
                                LIMIT 1")
                        ->execute([':id' => $userId]);
                    if (!empty($_SESSION['user']) && (int)$_SESSION['user']['id'] === $userId && $_SESSION['user']['role'] === 'Candidate') {
                        $_SESSION['user']['premium_badge'] = 1;
                    }
                    $applied = true;
                }
            }

            $pdo->commit();
            return [
                'ok' => true,
                'purpose' => $purpose,
                'updated' => $applied && !$alreadyProcessed,
                'message' => $alreadyProcessed ? 'Already processed.' : 'Processed.',
                'credits' => $credits,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'purpose' => $purpose, 'updated' => false, 'message' => 'DB error'];
        }
    }

    private function tableAndPkForRole(string $role): array
    {
        if ($role === 'Employer')  return ['employers',  'employer_id'];
        if ($role === 'Recruiter') return ['recruiters', 'recruiter_id'];
        throw new \RuntimeException('Unsupported role for credits top-up: ' . $role);
    }
}
