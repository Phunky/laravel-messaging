<?php

namespace Phunky\LaravelMessaging\Concerns;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Phunky\LaravelMessaging\Models\Message;

trait RegistersMessagingExtensionResources
{
    protected function registerMessagingMigrations(Application $app, string $migrationPath): void
    {
        $app->afterResolving('migrator', function ($migrator) use ($migrationPath): void {
            $migrator->path($migrationPath);
        });
    }

    protected function registerMessageMacro(string $name, object|callable $macro): void
    {
        if (! Message::hasMacro($name)) {
            Message::macro($name, $macro);
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function deleteRelatedModelsWhenMessageDeleted(string $modelClass, string $foreignKey = 'message_id'): void
    {
        Message::deleted(function (Message $message) use ($modelClass, $foreignKey): void {
            $modelClass::query()
                ->where($foreignKey, $message->getKey())
                ->delete();
        });
    }
}
