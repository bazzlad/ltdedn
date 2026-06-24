<?php

namespace App\Enums;

enum StorefrontConnectionStatus: string
{
    case NeedsSetup = 'needs_setup';
    case WaitingForOAuth = 'waiting_for_oauth';
    case WebhookReady = 'webhook_ready';
    case Testing = 'testing';
    case Ready = 'ready';
    case Failed = 'failed';
}
