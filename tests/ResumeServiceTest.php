<?php

declare(strict_types=1);

namespace {
    require __DIR__ . '/../vendor/autoload.php';

    spl_autoload_register(function (string $class): void {
        $prefix = 'App\\';
        $baseDir = __DIR__ . '/../app/';
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

    if (!defined('HIREME_TEST_STORAGE')) {
        define('HIREME_TEST_STORAGE', sys_get_temp_dir() . '/hireme-test-storage-' . bin2hex(random_bytes(4)));
    }

    if (!function_exists('storage_path')) {
        function storage_path(string $path = ''): string
        {
            $base = HIREME_TEST_STORAGE;
            if (!is_dir($base) && !mkdir($base, 0775, true) && !is_dir($base)) {
                throw new RuntimeException(sprintf('Unable to create storage directory at %s.', $base));
            }

            if ($path === '') {
                return $base;
            }

            $normalised = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);

            return $base . DIRECTORY_SEPARATOR . $normalised;
        }
    }
}

namespace App\Core\ORM {
    if (!class_exists(EntityManager::class)) {
        class EntityManager
        {
            /**
             * @template T
             * @param callable():T $callback
             * @return T
             */
            public function transaction(callable $callback)
            {
                return $callback();
            }
        }
    }
}

namespace Tests\Doubles {
    final class FakeEntityManager extends \App\Core\ORM\EntityManager
    {
        /**
         * @template T
         * @param callable():T $callback
         * @return T
         */
        public function transaction(callable $callback)
        {
            return $callback();
        }
    }

    final class FakeModel
    {
        private int $id;

        /** @var array<string, mixed> */
        private array $attributes;

        /**
         * @param array<string, mixed> $attributes
         */
        public function __construct(int $id, array $attributes)
        {
            $this->id = $id;
            $this->attributes = $attributes;
        }

        public function getKey(): int
        {
            return $this->id;
        }

        /**
         * @return array<string, mixed>
         */
        public function toArray(): array
        {
            return ['id' => $this->id] + $this->attributes;
        }

        public function getAttribute(string $key): mixed
        {
            return $this->attributes[$key] ?? null;
        }

        public function setAttribute(string $key, mixed $value): void
        {
            $this->attributes[$key] = $value;
        }
    }
}

namespace App\Repositories {
    use App\Models\Resume as ResumeModel;
    use Tests\Doubles\FakeEntityManager;
    use Tests\Doubles\FakeModel;

    final class ResumeRepository
    {
        private int $increment = 1;

        /** @var array<int, ResumeModel> */
        public array $created = [];

        public ?ResumeModel $lastCreated = null;

        public function __construct(private FakeEntityManager $entityManager)
        {
        }

        /**
         * @param array<string, mixed> $attributes
         */
        public function create(array $attributes): ResumeModel
        {
            $resume = new ResumeModel();
            foreach ($attributes as $key => $value) {
                $resume->setAttribute($key, $value);
            }

            $resume->setAttribute('resume_id', $this->increment++);
            $this->created[] = $resume;
            $this->lastCreated = $resume;

            return $resume;
        }
    }

    final class ResumeBuilderRepository
    {
        private int $increment = 1;

        /** @var array<int, FakeModel> */
        public array $created = [];

        public ?FakeModel $lastCreated = null;

        public function __construct(private FakeEntityManager $entityManager)
        {
        }

        /**
         * @param array<string, mixed> $attributes
         */
        public function create(array $attributes): FakeModel
        {
            $model = new FakeModel($this->increment++, $attributes);
            $this->created[] = $model;
            $this->lastCreated = $model;

            return $model;
        }
    }

    final class ResumeUnlockRepository
    {
        public function __construct(private FakeEntityManager $entityManager)
        {
        }
    }

    final class BillingRepository
    {
        public function __construct(private FakeEntityManager $entityManager)
        {
        }
    }
}

namespace App\Services\Notifications {
    final class NotificationService
    {
        /** @var array<int, array{userId: int, message: string, data: array<string, mixed>}> */
        public array $notifications = [];

        public function __construct(...$args)
        {
        }

        /**
         * @param array<string, mixed> $data
         */
        public function notify(int $userId, string $message, array $data = []): void
        {
            $this->notifications[] = [
                'userId' => $userId,
                'message' => $message,
                'data' => $data,
            ];
        }
    }
}

namespace {
    use Tests\Doubles\FakeEntityManager;
    use Tests\Doubles\FakeModel;
    use App\Repositories\ResumeRepository;
    use App\Repositories\ResumeBuilderRepository;
    use App\Repositories\ResumeUnlockRepository;
    use App\Repositories\BillingRepository;
    use App\Services\Notifications\NotificationService as FakeNotificationService;
    use App\Services\ResumeService;
    use App\Services\Resume\Builder\ProfileDirector;
    use App\Services\Resume\Builder\HtmlProfileBuilder;
    use App\Services\Resume\Builder\JsonProfileBuilder;

    $entityManager = new FakeEntityManager();
    $resumes = new ResumeRepository($entityManager);
    $builders = new ResumeBuilderRepository($entityManager);
    $unlocks = new ResumeUnlockRepository($entityManager);
    $billing = new BillingRepository($entityManager);
    $notifications = new FakeNotificationService();

    $service = new ResumeService(
        $entityManager,
        $resumes,
        $builders,
        $unlocks,
        $billing,
        $notifications,
        new ProfileDirector()
    );

    $data = [
        'name' => 'Test Candidate',
        'headline' => 'Automation Engineer',
        'email' => 'test@example.com',
        'summary' => 'Building resilient systems.',
        'experience' => [
            [
                'role' => 'QA Specialist',
                'company' => 'Quality Matters',
                'period' => '2020-2024',
                'description' => 'Automated regression suites.',
            ],
        ],
        'skills' => ['PHP', 'Testing', 'Automation'],
        'format' => 'html',
    ];

    $resume = $service->generate(501, $data);

    assert($resume instanceof \App\Models\Resume, 'Generated resume should be represented by a Resume model stub.');

    $relativePath = $resume->getAttribute('file_path');
    assert(is_string($relativePath) && str_starts_with($relativePath, 'resumes/'), 'Generated path should point to the resumes directory.');

    $fullPath = storage_path($relativePath);
    assert(is_file($fullPath), 'Generated resume file should exist on disk.');

    $storedHtml = file_get_contents($fullPath);
    $expectedHtml = (new ProfileDirector())->buildFullProfile(new HtmlProfileBuilder(), $data);
    assert($storedHtml === $expectedHtml, 'Stored resume should match the builder output.');

    $builderRecord = $builders->lastCreated;
    assert($builderRecord instanceof FakeModel, 'Builder repository should capture a record.');
    assert($builderRecord->getAttribute('generated_path') === $relativePath, 'Builder record should record the generated path.');

    $content = $resume->getAttribute('content');
    $decoded = json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);
    assert($decoded['format'] === 'html');
    assert($decoded['variant'] === 'full');

    $notification = $notifications->notifications[0] ?? null;
    assert($notification !== null, 'Notification should be recorded for generated resumes.');
    assert($notification['userId'] === 501);
    assert($notification['message'] === 'Resume generated');
    assert($notification['data']['path'] === $relativePath);
    assert($notification['data']['format'] === 'html');
    assert($notification['data']['resume_id'] === $resume->getKey());

    if (is_file($fullPath)) {
        unlink($fullPath);
    }

    $resumesDir = dirname($fullPath);
    $storageBase = dirname($resumesDir);

    $jsonData = [
        'name' => 'API Consumer',
        'title' => 'Integration Specialist',
        'email' => 'api@example.com',
        'phone' => '+44 1234 567890',
        'summary' => 'Shipping integrations to production.',
        'experience' => [
            [
                'role' => 'Developer Advocate',
                'company' => 'Webhook Works',
                'period' => '2019-2022',
                'description' => 'Built sample apps for partners.',
            ],
            [
                'role' => 'Solutions Engineer',
                'company' => 'API Ventures',
                'period' => '2022-2024',
                'description' => 'Guided enterprise implementations.',
            ],
        ],
        'skills' => ['REST', 'GraphQL', 'Postman', 'OAuth', 'OpenAPI', 'SDK Development'],
        'format' => 'json',
        'variant' => 'preview',
    ];

    $jsonResume = $service->generate(502, $jsonData);

    assert($jsonResume instanceof \App\Models\Resume, 'JSON resume should be represented by a Resume model stub.');

    $jsonRelativePath = $jsonResume->getAttribute('file_path');
    assert(is_string($jsonRelativePath) && str_starts_with($jsonRelativePath, 'resumes/'), 'JSON resume path should point to the resumes directory.');
    assert(str_ends_with($jsonRelativePath, '.json'), 'JSON resumes should be saved with a .json extension.');

    $jsonFullPath = storage_path($jsonRelativePath);
    assert(is_file($jsonFullPath), 'JSON resume file should exist on disk.');

    $storedJson = file_get_contents($jsonFullPath);
    $expectedJson = (new ProfileDirector())->buildPreview(new JsonProfileBuilder(), $jsonData);
    assert($storedJson === $expectedJson, 'Stored JSON resume should match the preview builder output.');

    $jsonProfile = json_decode((string) $storedJson, true, 512, JSON_THROW_ON_ERROR);
    assert(count($jsonProfile['experience']) === 1, 'Preview resumes should include a single experience entry.');
    assert(count($jsonProfile['skills']) === 5, 'Preview resumes should include up to five skills.');

    $jsonBuilderRecord = $builders->lastCreated;
    assert($jsonBuilderRecord instanceof FakeModel, 'Builder repository should capture JSON resume generation.');
    assert($jsonBuilderRecord->getAttribute('generated_path') === $jsonRelativePath);

    $jsonContent = $jsonResume->getAttribute('content');
    $jsonDecoded = json_decode((string) $jsonContent, true, 512, JSON_THROW_ON_ERROR);
    assert($jsonDecoded['format'] === 'json');
    assert($jsonDecoded['variant'] === 'preview');

    $jsonNotification = $notifications->notifications[1] ?? null;
    assert($jsonNotification !== null, 'Second notification should be recorded for JSON resumes.');
    assert($jsonNotification['userId'] === 502);
    assert($jsonNotification['data']['format'] === 'json');
    assert($jsonNotification['data']['variant'] === 'preview');
    assert($jsonNotification['data']['path'] === $jsonRelativePath);

    if (is_file($jsonFullPath)) {
        unlink($jsonFullPath);
    }

    $resumesDir = dirname($jsonFullPath);
    $storageBase = dirname($resumesDir);

    if (is_dir($resumesDir)) {
        @rmdir($resumesDir);
    }

    if (is_dir($storageBase)) {
        @rmdir($storageBase);
    }

    echo "ResumeService tests passed\n";
}
