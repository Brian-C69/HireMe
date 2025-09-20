<?php

namespace App\Core\Bootstrap;

use App\Core\Application;
use App\Core\Container;
use App\Core\ErrorHandler;
use App\Core\Router;
use App\Core\Session;
use App\Core\Support\Config;
use App\Core\Support\Logger;
use App\Core\ORM\ConnectionManager;
use App\Core\ORM\EntityManager;
use App\Services\Auth\AuthService;
use App\Services\Mail\MailService;
use App\Services\Notifications\NotificationService;
use App\Services\Payments\PaymentGateway;
use Dotenv\Dotenv;

class Bootstrap
{
    public function boot(): Application
    {
        $this->loadEnv();
        $this->bootstrapConfig();

        $container = $this->buildContainer();
        $container->make(EntityManager::class);
        $router = new Router();
        $errorHandler = new ErrorHandler((bool) config('app.debug', false));

        $application = new Application($container, $router, $errorHandler);

        $container->instance(Application::class, $application);
        $container->instance(Router::class, $router);
        $container->instance(ErrorHandler::class, $errorHandler);

        $this->registerRoutes($router, $container);

        return $application;
    }

    private function loadEnv(): void
    {
        if (file_exists(base_path('.env'))) {
            Dotenv::createImmutable(base_path())->safeLoad();
        }
    }

    private function bootstrapConfig(): void
    {
        $config = Config::instance();
        $config->loadFromDirectory(config_path());
        date_default_timezone_set($config->get('app.timezone', 'UTC'));
    }

    private function buildContainer(): Container
    {
        $container = new Container();

        $container->singleton(Session::class, fn () => new Session());

        $container->singleton(ConnectionManager::class, function () {
            return new ConnectionManager(config('database'));
        });

        $container->singleton(EntityManager::class, function (Container $container) {
            return new EntityManager($container->make(ConnectionManager::class));
        });

        $container->singleton(\PDO::class, function (Container $container) {
            return $container->make(ConnectionManager::class)->connection();
        });

        $container->singleton(Logger::class, function () {
            return new Logger(storage_path('logs/app.log'));
        });

        $container->singleton(MailService::class, function (Container $container) {
            return new MailService(config('mail'), $container->make(Logger::class));
        });

        $container->singleton(NotificationService::class, function (Container $container) {
            return new NotificationService($container->make(Logger::class));
        });

        $container->singleton(PaymentGateway::class, function (Container $container) {
            return new PaymentGateway(config('services.stripe'), $container->make(Logger::class));
        });

        $container->singleton(AuthService::class, function (Container $container) {
            return new AuthService($container->make(EntityManager::class), $container->make(Session::class));
        });

        return $container;
    }

    private function registerRoutes(Router $router, Container $container): void
    {
        require base_path('routes/web.php');
        require base_path('routes/api.php');
    }
}
