<?php
declare(strict_types=1);

final class ValidationService
{
    public static function requiredString(array $src, string $key): string
    {
        $value = trim((string) ($src[$key] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException($key . ' is required');
        }
        return $value;
    }
}
