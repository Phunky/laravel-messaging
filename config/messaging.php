<?php

use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;
use Phunky\LaravelMessaging\Models\Participant;

return [

    /*
    |--------------------------------------------------------------------------
    | Table prefix
    |--------------------------------------------------------------------------
    | Prefix applied to all package database tables (core and extension tables
    | that use messaging_table()).
    */
    'table_prefix' => 'messaging_',

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    | Cursor pagination is recommended for chat interfaces.
    */
    'pagination' => [
        'type' => 'cursor', // 'cursor' | 'offset'
        'per_page' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    | When enabled, core messaging events and BroadcastableMessagingEvent
    | subclasses implement ShouldBroadcast on private channels:
    | {channel_prefix}.conversation.{conversationId}
    */
    'broadcasting' => [
        'enabled' => false,
        'channel_prefix' => 'messaging',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    | Swap out core models with your own implementations.
    | Each replacement must implement the corresponding contract.
    | Extensions may document an additional `models.group` (or similar) key.
    */
    'models' => [
        'conversation' => Conversation::class,
        'participant' => Participant::class,
        'message' => Message::class,
        'event' => MessagingEvent::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Extensions (optional)
    |--------------------------------------------------------------------------
    | Leave empty to use core messaging only. Each entry must implement
    | MessagingExtension — register classes here only for optional add-on
    | packages you have installed (reactions, media, groups, custom, etc.).
    */
    'extensions' => [
        // \LaravelMessagingReactions\ReactionsExtension::class,
        // \LaravelMessagingAttachments\AttachmentExtension::class,
        // \LaravelMessagingGroups\GroupsExtension::class,
    ],

];
