<?php

namespace App\Enums;

enum UserRole: string
{
    case Employee = 'employee';
    case Admin = 'admin';
    case Client = 'client';
}
