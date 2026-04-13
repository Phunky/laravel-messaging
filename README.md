# Laravel Messaging

Wire up conversations between any Eloquent models. Users, teams, orders, support tickets — anything. Laravel Messaging handles conversations, participants, messages, and a lifecycle event system.

## Installation

```bash
composer require phunky/laravel-messaging
```

Optionally publish the config and migrations:

```bash
php artisan vendor:publish --tag="messaging-config"
php artisan vendor:publish --tag="messaging-migrations"
php artisan migrate
```

The `Messenger` facade is auto-discovered.

## Making models messageable

Any model that participates in conversations needs to implement `Messageable` and pull in the `HasMessaging` trait:

```php
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Traits\HasMessaging;

class User extends Authenticatable implements Messageable
{
    use HasMessaging;
}

class Order extends Model implements Messageable
{
    use HasMessaging;
}
```

Participant types mix freely. A `Customer` and an `Order` can share a conversation.

## Usage

### Sending messages

```php
use Phunky\LaravelMessaging\Facades\Messenger;

// Two users
Messenger::conversation($userA, $userB)->as($userA)->send('Hello');

// Cross-type (customer ↔ order)
Messenger::conversation($customer, $order)->as($customer)->send('Where is my order?');

// More than two participants
Messenger::conversation($userA, $userB, $userC)->as($userA)->send('Hey everyone');

// With metadata
Messenger::conversation($userA, $userB)->as($userA)->send('Hello', meta: ['source' => 'web']);

// Fetch messages without creating a conversation
Messenger::conversation($userA, $userB)->messages();
```

### Receipts and read state

```php
Messenger::conversation($userA, $userB)->as($userB)->unreadCount();
Messenger::conversation($userA, $userB)->as($userB)->markAllRead();

Messenger::message($id)->as($userB)->received();
Messenger::message($id)->as($userB)->read();

// Inbox — participating conversations, most recent first, with unread_count
Messenger::conversationsFor($userA)->with('latestMessage')->get();
```

### Events, edits, deletes

```php
// Custom per-message event — idempotent per participant + event name
Messenger::message($id)->as($userB)->recordEvent('message.voice.first_play', ['seconds' => 42]);

// Conversation-scoped events
Messenger::conversation($userA, $userB)->as($userB)->recordInviteAccepted(['invite_id' => 1]);
Messenger::conversation($userA, $userB)->as($userB)->recordMemberLeft();

// Edit and delete (sender only)
Messenger::message($id)->as($userA)->edit('Updated text');
Messenger::message($id)->as($userA)->delete();
```

Direct access via the trait:

```php
$userA->conversations;
$userA->messages;
```

Event name constants live on `Phunky\LaravelMessaging\MessagingEventName`.

## Extensions

The core ships with nothing beyond the basics. Install only what you need:

| Package | What it adds |
|---|---|
| `phunky/laravel-messaging-reactions` | Per-message reactions |
| `phunky/laravel-messaging-attachments` | File and link attachments |
| `phunky/laravel-messaging-groups` | Group conversations |

Register extensions in `config/messaging.php`:

```php
'extensions' => [
    \LaravelMessagingReactions\ReactionsExtension::class,
    \LaravelMessagingAttachments\AttachmentExtension::class,
    \LaravelMessagingGroups\GroupsExtension::class,
],
```

### Writing your own extension

Implement `Phunky\LaravelMessaging\Contracts\MessagingExtension`:

- **`register(Application $app)`** — bind services, register macros, define relationships
- **`boot(Application $app)`** — listen to lifecycle events, add migrations

Extensions can listen to `MessageSending`, `MessageSent`, `ConversationCreated`, etc., register macros on `Message`, and add their own migrations (the table prefix is applied automatically).

## Intercepting sends

`MessageSending` fires before the message hits the database and outside the transaction. Listeners can mutate the body, append metadata, or cancel the send entirely:

```php
use Phunky\LaravelMessaging\Events\MessageSending;

Event::listen(MessageSending::class, function (MessageSending $event) {
    $event->body = ai_moderate($event->body);
    $event->meta['moderated'] = true;

    if (contains_prohibited_content($event->body)) {
        $event->cancel(); // throws MessageRejectedException at the call site
    }
});
```

## Broadcasting

Set `broadcasting.enabled = true` in the config. Events that implement `ShouldBroadcast` — `ConversationCreated`, `MessageSent`, `MessageEdited`, `MessageDeleted`, `MessageReceived`, `MessageRead`, `AllMessagesRead` — will broadcast on private channels in the format `{channel_prefix}.conversation.{conversationId}` (default prefix: `messaging`). Authorize those channels in `routes/channels.php`. `MessageSending` is never broadcast.

## Lifecycle events

| Event | Fired | Properties |
|---|---|---|
| `ConversationCreated` | New conversation created | `$conversation`, `$participants` |
| `MessageSending` | Before persistence | `$conversation`, `$sender`, `$body`, `$meta` |
| `MessageSent` | After persistence | `$message`, `$conversation` |
| `MessageEdited` | Body updated | `$message`, `$originalBody` |
| `MessageDeleted` | Soft-deleted | `$message`, `$conversation` |
| `MessageReceived` | First `message.received` recorded | `$messagingEvent`, `$message`, `$participant` |
| `MessageRead` | First `message.read` recorded | `$messagingEvent`, `$message`, `$participant` |
| `AllMessagesRead` | After `markAllRead()` | `$conversation`, `$reader`, `$count` |

## Custom models

Swap any model in `config/messaging.php` under `models`. Extend the package's base classes rather than replacing them — this keeps foreign keys and contracts intact. If your class name implies a different table name, set `protected $table = messaging_table('conversations')` to respect the configured prefix.

## Table prefix

Defaults to `messaging_`. Change it in config. Use the `messaging_table('conversations')` helper anywhere you need the full table name.

## License

MIT
