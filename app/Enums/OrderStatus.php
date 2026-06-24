<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Exception = 'exception';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
