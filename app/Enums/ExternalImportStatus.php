<?php

namespace App\Enums;

enum ExternalImportStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Exception = 'exception';
    case Failed = 'failed';
}
