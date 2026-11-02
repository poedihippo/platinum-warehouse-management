<?php

namespace App\Enums;

enum BatchSource: string
{
    use BaseEnum;

    case UPLOAD = 'upload';

    case IMPORT = 'import';
}
