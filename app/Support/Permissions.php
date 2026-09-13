<?php

namespace App\Support;

final class Permissions
{
    // Vorgänge
    public const VORGANG_VIEW_ANY = 'vorgang.view_any';
    public const VORGANG_VIEW_ASSIGNED = 'vorgang.view_assigned';
    public const VORGANG_UPDATE = 'vorgang.update';
    public const VORGANG_DELETE = 'vorgang.delete';
    public const VORGANG_ASSIGN = 'vorgang.assign';
    public const VORGANG_TRANSITION = 'vorgang.transition';
    public const VORGANG_TRANSITION_FIELD = 'vorgang.transition_field';
    public const VORGANG_NOTE_CREATE = 'vorgang.note_create';

    // Vor-Ort-Kontrolle
    public const INSPECTION_CREATE = 'inspection.create';
    public const INSPECTION_UPDATE = 'inspection.update';

    // Anhänge
    public const ATTACHMENT_UPLOAD = 'attachment.upload';
    public const ATTACHMENT_DELETE = 'attachment.delete';

    // Verwaltung
    public const KOBO_VIEW_RAW = 'kobo.view_raw';
    public const KOBO_REPROCESS = 'kobo.reprocess';
    public const USER_MANAGE = 'user.manage';
    public const ROLE_MANAGE = 'role.manage';
    public const WORKFLOW_MANAGE = 'workflow.manage';
    public const SPECIES_MANAGE = 'species.manage';
    public const EMAIL_TEMPLATE_MANAGE = 'email_template.manage';
    public const NOTIFICATION_RULE_MANAGE = 'notification_rule.manage';
    public const SETTINGS_MANAGE = 'settings.manage';
    public const EMAIL_LOG_VIEW = 'email_log.view';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::VORGANG_VIEW_ANY => 'Alle Vorgänge sehen',
            self::VORGANG_VIEW_ASSIGNED => 'Zugewiesene Vorgänge sehen',
            self::VORGANG_UPDATE => 'Vorgänge bearbeiten',
            self::VORGANG_DELETE => 'Vorgänge löschen',
            self::VORGANG_ASSIGN => 'Vorgänge zuweisen',
            self::VORGANG_TRANSITION => 'Status ändern (Innendienst)',
            self::VORGANG_TRANSITION_FIELD => 'Status ändern (Außendienst)',
            self::VORGANG_NOTE_CREATE => 'Notizen schreiben',
            self::INSPECTION_CREATE => 'Vor-Ort-Kontrolle erfassen',
            self::INSPECTION_UPDATE => 'Vor-Ort-Kontrolle bearbeiten',
            self::ATTACHMENT_UPLOAD => 'Fotos hochladen',
            self::ATTACHMENT_DELETE => 'Anhänge löschen',
            self::KOBO_VIEW_RAW => 'Kobo-Rohdaten ansehen',
            self::KOBO_REPROCESS => 'Kobo-Rohdaten neu verarbeiten',
            self::USER_MANAGE => 'Benutzer verwalten',
            self::ROLE_MANAGE => 'Rollen verwalten',
            self::WORKFLOW_MANAGE => 'Workflow verwalten',
            self::SPECIES_MANAGE => 'Pflanzenarten verwalten',
            self::EMAIL_TEMPLATE_MANAGE => 'E-Mail-Vorlagen verwalten',
            self::NOTIFICATION_RULE_MANAGE => 'Benachrichtigungsregeln verwalten',
            self::SETTINGS_MANAGE => 'Systemeinstellungen verwalten',
            self::EMAIL_LOG_VIEW => 'E-Mail-Protokoll ansehen',
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::labels());
    }

    /**
     * Permissions granted to each role on seed. Super-Admin is handled by a
     * Gate::before rule instead and therefore not listed.
     *
     * @return array<string, list<string>>
     */
    public static function byRole(): array
    {
        $office = [
            self::VORGANG_VIEW_ANY,
            self::VORGANG_UPDATE,
            self::VORGANG_ASSIGN,
            self::VORGANG_TRANSITION,
            // The office can also do anything the field can, so it holds the
            // field permission too.
            self::VORGANG_TRANSITION_FIELD,
            self::VORGANG_NOTE_CREATE,
            self::INSPECTION_CREATE,
            self::INSPECTION_UPDATE,
            self::ATTACHMENT_UPLOAD,
            self::KOBO_VIEW_RAW,
            self::SPECIES_MANAGE,
            self::EMAIL_LOG_VIEW,
        ];

        return [
            Roles::ADMIN => array_merge($office, [
                self::VORGANG_DELETE,
                self::ATTACHMENT_DELETE,
                self::KOBO_REPROCESS,
                self::USER_MANAGE,
                self::ROLE_MANAGE,
                self::WORKFLOW_MANAGE,
                self::EMAIL_TEMPLATE_MANAGE,
                self::NOTIFICATION_RULE_MANAGE,
            ]),
            Roles::INNENDIENST => $office,
            Roles::AUSSENDIENST => [
                self::VORGANG_VIEW_ASSIGNED,
                self::VORGANG_TRANSITION_FIELD,
                self::VORGANG_NOTE_CREATE,
                self::INSPECTION_CREATE,
                self::INSPECTION_UPDATE,
                self::ATTACHMENT_UPLOAD,
            ],
        ];
    }
}
