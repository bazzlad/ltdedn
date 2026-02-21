<?php

namespace App\Enums;

enum ProductSaleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}
