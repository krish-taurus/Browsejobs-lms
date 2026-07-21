<?php

declare(strict_types=1);

namespace App\Enums;

enum VideoSource: string
{
    case Url = 'url';             // external embed (YouTube/Vimeo/etc.)
    case Recording = 'recording'; // a Zoom recording from the library
    case Upload = 'upload';       // a file on the Space (reserved for direct upload)
}
