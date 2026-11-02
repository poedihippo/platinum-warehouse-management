<?php

namespace App\Enums;

enum DocumentType: string
{
    use BaseEnum;

    case SO = 'so';

    case DO = 'do';
}
