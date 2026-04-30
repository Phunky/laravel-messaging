<?php

namespace Phunky\LaravelMessaging;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Phunky\LaravelMessaging\Contracts\MessagingExtension;
use Phunky\LaravelMessaging\Services\MessagingService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MessagingServiceProvider extends PackageServiceProvider
{
    /**
     * @var array<int, class-string<MessagingExtension>>|null
     */
    protected ?array $cachedExtensions = null;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-messaging')
            ->hasConfigFile('messaging')
            ->hasMigrations(
                '2025_04_08_100000_create_conversations_table',
                '2025_04_08_100001_create_participants_table',
                '2025_04_08_100002_create_messages_table',
                '2025_04_08_100003_create_events_table',
                '2025_04_08_100004_add_last_activity_at_to_conversations_table',
            )
            ->runsMigrations();
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(MessagingService::class, fn (Application $app) => new MessagingService);

        $this->app->singleton(MessengerManager::class, fn (Application $app) => new MessengerManager(
            $app->make(MessagingService::class)
        ));
    }

    public function bootingPackage(): void
    {
        foreach ($this->getValidatedExtensionClasses() as $extensionClass) {
            if (! $this->app->bound($extensionClass)) {
                $this->app->singleton($extensionClass);
            }

            $this->app->make($extensionClass)->register($this->app);
        }
    }

    public function packageBooted(): void
    {
        foreach ($this->getValidatedExtensionClasses() as $extensionClass) {
            $this->app->make($extensionClass)->boot($this->app);
        }
    }

    /**
     * @return array<int, class-string<MessagingExtension>>
     */
    protected function getValidatedExtensionClasses(): array
    {
        if ($this->cachedExtensions !== null) {
            return $this->cachedExtensions;
        }

        return $this->cachedExtensions = $this->validatedExtensionClasses();
    }

    /**
     * @return array<int, class-string<MessagingExtension>>
     */
    protected function validatedExtensionClasses(): array
    {
        $extensions = config('messaging.extensions', []);
        $classes = [];

        foreach ($extensions as $extension) {
            if (! is_string($extension) || ! class_exists($extension)) {
                throw new InvalidArgumentException('Invalid messaging extension: ['.json_encode($extension).'].');
            }

            if (! is_subclass_of($extension, MessagingExtension::class)) {
                throw new InvalidArgumentException(sprintf(
                    'Messaging extension [%s] must implement %s.',
                    $extension,
                    MessagingExtension::class
                ));
            }

            $classes[] = $extension;
        }

        return $classes;
    }
}
