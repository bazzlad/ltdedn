<?php

namespace App\Enums;

enum StorefrontPlatform: string
{
    case Shopify = 'shopify';
    case Squarespace = 'squarespace';
    case LegacyOrderDesk = 'orderdesk';
    case Pipe17 = 'pipe17';
}
