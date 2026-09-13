<?php

namespace App\Support;

/**
 * HANDLUNG_OPTIONS from the reference monitor application. The empty string is
 * the "not yet decided" state and is stored as null here.
 */
final class ActionNeeded
{
    public const YES = 'Ja';
    public const NO = 'Nein';
    public const TO_CHECK = 'Zu prüfen';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::YES => self::YES,
            self::NO => self::NO,
            self::TO_CHECK => self::TO_CHECK,
        ];
    }

    public static function color(?string $value): string
    {
        return match ($value) {
            self::YES => 'danger',
            self::NO => 'success',
            self::TO_CHECK => 'warning',
            default => 'gray',
        };
    }
}
