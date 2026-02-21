<?php

namespace App\Enums;

enum InventoryReservationStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Expired = 'expired';
    case Released = 'released';
}
