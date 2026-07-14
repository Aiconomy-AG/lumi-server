<?php

namespace App\Services;

class PhoneNumberService
{
    public function normalize(?string $value): ?string
    {
        $normalized = preg_replace('/[\s().-]+/', '', trim((string) $value));

        if ($normalized === null || ! preg_match('/^\+[1-9]\d{7,14}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }
}
