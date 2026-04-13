<?php

namespace Phunky\LaravelMessaging;

use Phunky\LaravelMessaging\Models\MessagingEvent;

/**
 * Core event name strings stored in {@see MessagingEvent::$event}.
 */
final class MessagingEventName
{
    public const MessageReceived = 'message.received';

    public const MessageRead = 'message.read';

    public const ConversationInviteAccepted = 'conversation.invite.accepted';

    public const ConversationMemberLeft = 'conversation.member.left';
}
