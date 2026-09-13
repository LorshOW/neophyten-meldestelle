<?php

namespace App\Support;

/**
 * PRIORITY_OPTIONS / PRIORITY_HEX from the reference monitor application.
 */
final class Priority
{
    public const HIGH = 'Hoch';
    public const SOON = 'Zeitnah';
    public const NORMAL = 'Normal';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::HIGH => self::HIGH,
            self::SOON => self::SOON,
            self::NORMAL => self::NORMAL,
        ];
    }

    /** Filament colour names closest to the monitor's hex palette. */
    public static function color(string $priority): string
    {
        return match ($priority) {
            self::HIGH => 'danger',
            self::SOON => 'warning',
            default => 'gray',
        };
    }
}
