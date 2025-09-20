<?php

use App\Repositories\BillingRepository;
use App\Repositories\PaymentRepository;
use App\Services\AccountService;
use App\Services\PaymentService;
use PHPUnit\Framework\TestCase;

class PaymentServiceTest extends TestCase
{
    private PaymentService $payments;
    private AccountService $accounts;
    private BillingRepository $billing;
    private PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $container = $GLOBALS['app']->container();
        $this->payments = $container->make(PaymentService::class);
        $this->accounts = $container->make(AccountService::class);
        $this->billing = $container->make(BillingRepository::class);
        $this->paymentRepository = $container->make(PaymentRepository::class);
    }

    public function test_it_records_charges_and_updates_credit_balances(): void
    {
        $employer = $this->accounts->registerEmployer([
            'name' => 'Payments Employer',
            'email' => 'payments-' . uniqid('', true) . '@example.com',
            'password' => 'secret',
            'company_name' => 'Payments LLC',
        ]);

        $billing = $this->billing->forUser($employer->getKey());
        $this->assertNotNull($billing);
        $initialCredits = (int) $billing->getAttribute('credits_balance');

        $payload = [
            'amount' => 4999,
            'currency' => 'usd',
            'credits' => 5,
            'description' => 'Professional credits pack',
        ];

        $charge = $this->payments->charge($employer->getKey(), $payload);

        $this->assertSame('succeeded', $charge['status']);
        $this->assertSame($payload['amount'], $charge['amount']);
        $this->assertSame($payload['currency'], $charge['currency']);

        $updatedBilling = $this->billing->forUser($employer->getKey());
        $this->assertNotNull($updatedBilling);
        $this->assertSame($initialCredits + $payload['credits'], (int) $updatedBilling->getAttribute('credits_balance'));

        $records = $this->paymentRepository->forUser($employer->getKey());
        $this->assertNotEmpty($records);

        /** @var App\Models\Payment $latest */
        $latest = $records[0];
        $this->assertSame($charge['id'], $latest->getAttribute('reference'));
        $this->assertSame('stripe', $latest->getAttribute('provider'));
        $this->assertSame('succeeded', $latest->getAttribute('status'));
        $this->assertSame($payload['amount'], (int) $latest->getAttribute('amount'));

        $meta = $latest->getAttribute('meta');
        $this->assertIsArray($meta);
        $this->assertSame($payload['credits'], (int) ($meta['credits'] ?? 0));
    }
}
