<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Models\Billing;
use App\Models\Candidate;
use App\Models\Employer;
use App\Services\Modules\PaymentBillingService;
use App\Services\Payment\Observers\AccountingNotifier;
use App\Services\Payment\Observers\InvoiceStatusUpdater;
use App\Services\Payment\Observers\SubscriptionStateManager;
use App\Services\Payment\PaymentProcessor;
use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$pdo = $capsule->getConnection()->getPdo();
$pdo->exec('CREATE TABLE payments (
    payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_type TEXT,
    user_id INTEGER,
    amount REAL,
    purpose TEXT,
    payment_method TEXT,
    transaction_status TEXT,
    transaction_id TEXT,
    created_at TEXT,
    updated_at TEXT
)');
$pdo->exec('CREATE TABLE billing (
    billing_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    user_type TEXT,
    transaction_type TEXT,
    amount REAL,
    payment_method TEXT,
    transaction_date TEXT,
    status TEXT,
    reference_number TEXT,
    created_at TEXT,
    updated_at TEXT
)');
$pdo->exec('CREATE TABLE employers (
    employer_id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT,
    email TEXT,
    credits_balance INTEGER,
    created_at TEXT,
    updated_at TEXT
)');
$pdo->exec('CREATE TABLE recruiters (
    recruiter_id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT,
    email TEXT,
    credits_balance INTEGER,
    created_at TEXT,
    updated_at TEXT
)');
$pdo->exec('CREATE TABLE candidates (
    candidate_id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT,
    email TEXT,
    password_hash TEXT,
    premium_badge INTEGER,
    premium_badge_date TEXT,
    created_at TEXT,
    updated_at TEXT
)');

$timestamp = date('Y-m-d H:i:s');
$pdo->exec("INSERT INTO employers (employer_id, company_name, email, credits_balance, created_at, updated_at) VALUES (1, 'Acme Corp', 'acme@example.com', 0, '$timestamp', '$timestamp')");
$pdo->exec("INSERT INTO candidates (candidate_id, full_name, email, password_hash, premium_badge, created_at, updated_at) VALUES (2, 'Jane Candidate', 'jane@example.com', 'secret', 0, '$timestamp', '$timestamp')");

$logFile = tempnam(sys_get_temp_dir(), 'acct');

$processor = new PaymentProcessor();
$invoiceUpdater = new InvoiceStatusUpdater();
$subscriptionManager = new SubscriptionStateManager();
$accountingNotifier = new AccountingNotifier($logFile);

$processor->attach(PaymentProcessor::EVENT_INVOICE_PAID, $invoiceUpdater);
$processor->attach(PaymentProcessor::EVENT_PAYMENT_FAILED, $invoiceUpdater);
$processor->attach(PaymentProcessor::EVENT_PAYMENT_REFUNDED, $invoiceUpdater);
$processor->attach(PaymentProcessor::EVENT_INVOICE_PAID, $subscriptionManager);
$processor->attach(PaymentProcessor::EVENT_PAYMENT_REFUNDED, $subscriptionManager);
$processor->attach(PaymentProcessor::EVENT_INVOICE_PAID, $accountingNotifier);
$processor->attach(PaymentProcessor::EVENT_PAYMENT_FAILED, $accountingNotifier);
$processor->attach(PaymentProcessor::EVENT_PAYMENT_REFUNDED, $accountingNotifier);

$service = new PaymentBillingService($processor);

$successRequest = new App\Core\Request([], [
    'user_id' => 1,
    'user_type' => 'Employer',
    'amount' => 4999,
    'purpose' => 'credits',
    'payment_method' => 'stripe',
    'transaction_status' => 'succeeded',
    'transaction_id' => 'pi_success_001',
    'metadata' => ['credits' => 5],
]);

$success = $service->handle('charge', null, $successRequest);

assert($success['event'] === PaymentProcessor::EVENT_INVOICE_PAID, 'Successful payments should emit the invoice_paid event.');
assert(isset($success['payment']['transaction_status']) && $success['payment']['transaction_status'] === 'Success', 'Payment record should be normalised to Success status.');

$billing = Billing::query()->where('user_id', 1)->where('user_type', 'Employer')->first();
assert($billing instanceof Billing, 'Billing record should be created for the employer.');
assert($billing->getAttribute('status') === 'paid', 'Billing record should be marked as paid.');

$employer = Employer::find(1);
assert($employer !== null, 'Employer should exist.');
assert((int) $employer->getAttribute('credits_balance') === 5, 'Employer credits should increase by the purchased amount.');

$logContents = file_get_contents($logFile) ?: '';
assert(str_contains($logContents, '"event":"invoice_paid"'), 'Accounting export should log successful payments.');

$failureRequest = new App\Core\Request([], [
    'user_id' => 2,
    'user_type' => 'Candidate',
    'amount' => 5000,
    'purpose' => 'premium',
    'payment_method' => 'stripe',
    'transaction_status' => 'failed',
    'transaction_id' => 'pi_fail_001',
]);

$failure = $service->handle('charge', null, $failureRequest);

assert($failure['event'] === PaymentProcessor::EVENT_PAYMENT_FAILED, 'Failed payments should emit the payment_failed event.');

$failedBilling = Billing::query()->where('user_id', 2)->where('user_type', 'Candidate')->orderByDesc('transaction_date')->first();
assert($failedBilling instanceof Billing, 'Billing record should be created for failed candidate payment.');
assert($failedBilling->getAttribute('status') === 'failed', 'Billing record should be flagged as failed.');

$candidate = Candidate::find(2);
assert($candidate !== null && (int) $candidate->getAttribute('premium_badge') === 0, 'Premium badge should not be activated on failed payment.');

$logContents = file_get_contents($logFile) ?: '';
assert(str_contains($logContents, '"event":"payment_failed"'), 'Accounting export should log failed payments.');

$premiumSuccessRequest = new App\Core\Request([], [
    'user_id' => 2,
    'user_type' => 'Candidate',
    'amount' => 2999,
    'purpose' => 'premium badge',
    'payment_method' => 'stripe',
    'transaction_status' => 'paid',
    'transaction_id' => 'pi_candidate_premium_001',
]);

$premiumSuccess = $service->handle('charge', null, $premiumSuccessRequest);

assert($premiumSuccess['event'] === PaymentProcessor::EVENT_INVOICE_PAID, 'Candidate premium purchases should emit invoice_paid.');

$candidateBilling = Billing::query()
    ->where('user_id', 2)
    ->where('user_type', 'Candidate')
    ->where('reference_number', 'pi_candidate_premium_001')
    ->first();

assert($candidateBilling instanceof Billing, 'Candidate premium payment should create a billing record.');
assert($candidateBilling->getAttribute('status') === 'paid', 'Candidate premium billing should be marked paid.');

$candidate = Candidate::find(2);
assert($candidate !== null && (int) $candidate->getAttribute('premium_badge') === 1, 'Premium badge should activate after successful payment.');

$premiumRefundRequest = new App\Core\Request([], [
    'user_id' => 2,
    'user_type' => 'Candidate',
    'amount' => 2999,
    'purpose' => 'premium badge',
    'payment_method' => 'stripe',
    'transaction_status' => 'refund',
    'transaction_id' => 'pi_candidate_premium_001',
    'billing_id' => $candidateBilling->getKey(),
]);

$premiumRefund = $service->handle('charge', null, $premiumRefundRequest);

assert($premiumRefund['event'] === PaymentProcessor::EVENT_PAYMENT_REFUNDED, 'Candidate premium refunds should emit payment_refunded.');

$candidateRefundBilling = Billing::find($candidateBilling->getKey());
assert($candidateRefundBilling instanceof Billing && $candidateRefundBilling->getAttribute('status') === 'refunded', 'Candidate refund should mark billing as refunded.');
assert((float) $candidateRefundBilling->getAttribute('amount') < 0, 'Candidate refund amount should be negative.');

$candidate = Candidate::find(2);
assert($candidate !== null && (int) $candidate->getAttribute('premium_badge') === 0, 'Premium badge should deactivate after refund.');

$refundRequest = new App\Core\Request([], [
    'user_id' => 1,
    'user_type' => 'Employer',
    'amount' => 4999,
    'purpose' => 'credits',
    'payment_method' => 'stripe',
    'transaction_status' => 'refunded',
    'transaction_id' => 'pi_success_001',
    'metadata' => ['credits' => 5],
    'billing_id' => $billing->getKey(),
]);

$refund = $service->handle('charge', null, $refundRequest);

assert($refund['event'] === PaymentProcessor::EVENT_PAYMENT_REFUNDED, 'Refunded payments should emit the payment_refunded event.');

$refundedBilling = Billing::find($billing->getKey());
assert($refundedBilling instanceof Billing && $refundedBilling->getAttribute('status') === 'refunded', 'Refunded billing record should be marked as refunded.');
assert((float) $refundedBilling->getAttribute('amount') < 0, 'Refunded billing amount should be negative to indicate reversal.');

$employer = Employer::find(1);
assert($employer !== null && (int) $employer->getAttribute('credits_balance') === 0, 'Employer credits should revert after refund.');

$logContents = file_get_contents($logFile) ?: '';
assert(str_contains($logContents, '"event":"payment_refunded"'), 'Accounting export should log refunded payments.');

unlink($logFile);

echo "Payment processor observer tests passed\n";
