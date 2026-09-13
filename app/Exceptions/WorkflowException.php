<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a status change is not permitted. The message is shown to the
 * user, so it is written in German and states what is missing.
 */
class WorkflowException extends RuntimeException
{
    public static function noTransition(string $from, string $to): self
    {
        return new self("Der Übergang von „{$from}“ nach „{$to}“ ist nicht vorgesehen.");
    }

    public static function notPermitted(string $label): self
    {
        return new self("Für „{$label}“ fehlt die nötige Berechtigung.");
    }

    public static function assigneeRequired(string $status): self
    {
        return new self("Für den Status „{$status}“ muss der Vorgang einer Person zugewiesen sein.");
    }

    public static function inspectionRequired(string $status): self
    {
        return new self("Für den Status „{$status}“ muss eine abgeschlossene Vor-Ort-Kontrolle vorliegen.");
    }

    public static function noteRequired(string $label): self
    {
        return new self("Für „{$label}“ ist eine Begründung erforderlich.");
    }
}
