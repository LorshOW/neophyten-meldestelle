<?php

namespace App\Support;

/**
 * Risk options and the priority rule, transcribed from the reference monitor
 * application (invasive-pflanzen-monitor.html, RISK_OPTIONS / PRIO_HIGH /
 * PRIO_MED / computePriority).
 *
 * The same six options are answered twice: once by the reporting person in the
 * Kobo form, and again by the office as its own assessment. Only the office
 * assessment drives the priority.
 */
final class RiskAssessment
{
    public const HUMAN = 'Gefährdung von Menschen möglich';
    public const ANIMAL = 'Gefährdung von Tieren möglich';
    public const PROTECTED_AREA = 'Naturschutzgebiet / Schutzgebiet';
    public const SENSITIVE_NATURE = 'Geschützte oder sensible Natur im Umfeld';
    public const NONE = 'Keine Gefährdung erkennbar';
    public const UNKNOWN = 'Nicht einschätzbar';

    /** Raises the priority to "Hoch". */
    public const PRIORITY_HIGH_TRIGGERS = [self::HUMAN, self::ANIMAL];

    /** Raises the priority to "Zeitnah". */
    public const PRIORITY_MEDIUM_TRIGGERS = [self::PROTECTED_AREA, self::SENSITIVE_NATURE];

    /** @return list<string> In the order the monitor presents them. */
    public static function options(): array
    {
        return [
            self::HUMAN,
            self::ANIMAL,
            self::PROTECTED_AREA,
            self::SENSITIVE_NATURE,
            self::NONE,
            self::UNKNOWN,
        ];
    }

    /** @return array<string, string> */
    public static function selectOptions(): array
    {
        return array_combine(self::options(), self::options());
    }

    /**
     * computePriority() from the reference application.
     *
     * @param  list<string>|null  $risks
     */
    public static function computePriority(?array $risks): string
    {
        $risks ??= [];

        foreach ($risks as $risk) {
            if (in_array($risk, self::PRIORITY_HIGH_TRIGGERS, true)) {
                return Priority::HIGH;
            }
        }

        foreach ($risks as $risk) {
            if (in_array($risk, self::PRIORITY_MEDIUM_TRIGGERS, true)) {
                return Priority::SOON;
            }
        }

        return Priority::NORMAL;
    }
}
