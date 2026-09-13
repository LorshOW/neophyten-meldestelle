<?php

namespace App\Listeners;

use App\Events\InspectionCompleted;
use App\Events\VorgangAssigned;
use App\Events\VorgangCreated;
use App\Events\VorgangStatusChanged;
use App\Models\NotificationRule;
use App\Services\Mail\NotificationDispatcher;

/**
 * Single place where domain events become emails.
 */
class SendWorkflowNotifications
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handleVorgangCreated(VorgangCreated $event): void
    {
        $this->dispatcher->dispatchFor(
            NotificationRule::EVENT_VORGANG_CREATED,
            $event->vorgang,
        );
    }

    public function handleStatusChanged(VorgangStatusChanged $event): void
    {
        $this->dispatcher->dispatchFor(
            NotificationRule::EVENT_STATUS_CHANGED,
            $event->vorgang,
            $event->to,
        );
    }

    public function handleAssigned(VorgangAssigned $event): void
    {
        $this->dispatcher->dispatchFor(
            NotificationRule::EVENT_VORGANG_ASSIGNED,
            $event->vorgang,
        );
    }

    public function handleInspectionCompleted(InspectionCompleted $event): void
    {
        $vorgang = $event->inspection->vorgang;

        if ($vorgang === null) {
            return;
        }

        $this->dispatcher->dispatchFor(
            NotificationRule::EVENT_INSPECTION_COMPLETED,
            $vorgang,
            extra: [
                'ergebnis' => (string) ($event->inspection->recommended_measure
                    ?: $event->inspection->confirmation
                    ?: '–'),
                'bearbeiter' => (string) $event->inspection->inspector?->name,
            ],
        );
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            VorgangCreated::class => 'handleVorgangCreated',
            VorgangStatusChanged::class => 'handleStatusChanged',
            VorgangAssigned::class => 'handleAssigned',
            InspectionCompleted::class => 'handleInspectionCompleted',
        ];
    }
}
