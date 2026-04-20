<?php

namespace App\Enums;

enum ResponseType: string
{
    case Scale1To5 = 'scale_1_5';
    case YesNo = 'yes_no';
    case FreeText = 'free_text';
}
