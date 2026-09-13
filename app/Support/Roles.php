<?php

namespace App\Support;

/**
 * Role and permission names.
 *
 * Kept as constants so a typo is a fatal error rather than a silently failing
 * authorisation check.
 */
final class Roles
{
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const INNENDIENST = 'innendienst';
    public const AUSSENDIENST = 'aussendienst';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::SUPER_ADMIN => 'Super-Admin',
            self::ADMIN => 'Administration',
            self::INNENDIENST => 'Innendienst',
            self::AUSSENDIENST => 'Außendienst',
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::labels());
    }
}
