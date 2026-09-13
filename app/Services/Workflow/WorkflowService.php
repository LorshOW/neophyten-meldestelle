<?php

namespace App\Services\Workflow;

use App\Events\VorgangAssigned;
use App\Events\VorgangStatusChanged;
use App\Exceptions\WorkflowException;
use App\Models\User;
use App\Models\Vorgang;
use App\Models\VorgangStatusHistory;
use App\Models\WorkflowStatus;
use App\Models\WorkflowTransition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only way a Vorgang changes status.
 *
 * Guards, audit trail and events all live here, so no caller can move a case
 * without leaving a record or skipping a precondition.
 */
class WorkflowService
{
    /**
     * Transitions a user may actually perform right now.
     *
     * @return Collection<int, WorkflowTransition>
     */
    public function availableTransitions(Vorgang $vorgang, ?User $user = null): Collection
    {
        return WorkflowTransition::query()
            ->active()
            ->availableFrom($vorgang->status_id)
            ->with('toStatus')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (WorkflowTransition $t) => $t->toStatus?->is_active)
            ->filter(fn (WorkflowTransition $t) => $this->userMayUse($t, $user))
            ->values();
    }

    /**
     * Moves a Vorgang into a new status.
     *
     * @throws WorkflowException
     */
    public function transition(
        Vorgang $vorgang,
        WorkflowStatus $target,
        ?User $actor = null,
        ?string $note = null,
    ): Vorgang {
        $from = $vorgang->status;

        if ($from !== null && $from->is($target)) {
            return $vorgang;
        }

        $transition = $this->findTransition($vorgang->status_id, $target);

        if ($transition === null) {
            throw WorkflowException::noTransition($from?->name ?? '—', $target->name);
        }

        if (! $this->userMayUse($transition, $actor)) {
            throw WorkflowException::notPermitted($transition->label);
        }

        if ($transition->requires_note && blank($note)) {
            throw WorkflowException::noteRequired($transition->label);
        }

        $this->assertGuards($vorgang, $target);

        DB::transaction(function () use ($vorgang, $target, $actor, $note, $from): void {
            $vorgang->status_id = $target->getKey();

            if ($target->is_terminal) {
                $vorgang->closed_at = now();
                $vorgang->closed_by_id = $actor?->getKey();
            } else {
                // Re-opening a closed case clears the closing stamp.
                $vorgang->closed_at = null;
                $vorgang->closed_by_id = null;
            }

            $vorgang->save();

            VorgangStatusHistory::create([
                'vorgang_id' => $vorgang->getKey(),
                'from_status_id' => $from?->getKey(),
                'to_status_id' => $target->getKey(),
                'user_id' => $actor?->getKey(),
                'note' => $note,
            ]);
        });

        $vorgang->refresh()->load('status');

        VorgangStatusChanged::dispatch($vorgang, $from, $target, $actor, $note);

        return $vorgang;
    }

    /** Assigns a Vorgang and records who did it. */
    public function assign(Vorgang $vorgang, User $assignee, ?User $actor = null): Vorgang
    {
        $changed = $vorgang->assigned_to_id !== $assignee->getKey();

        $vorgang->forceFill([
            'assigned_to_id' => $assignee->getKey(),
            'assigned_by_id' => $actor?->getKey(),
            'assigned_at' => now(),
        ])->save();

        if ($changed) {
            VorgangAssigned::dispatch($vorgang->refresh(), $assignee, $actor);
        }

        return $vorgang;
    }

    private function findTransition(?int $fromStatusId, WorkflowStatus $target): ?WorkflowTransition
    {
        return WorkflowTransition::query()
            ->active()
            ->where('to_status_id', $target->getKey())
            ->where(function ($query) use ($fromStatusId): void {
                $query->where('from_status_id', $fromStatusId)->orWhereNull('from_status_id');
            })
            // A specific rule wins over the wildcard rule.
            ->orderByRaw('from_status_id is null')
            ->first();
    }

    private function userMayUse(WorkflowTransition $transition, ?User $user): bool
    {
        if (blank($transition->required_permission)) {
            return true;
        }

        // No user means a system-driven transition (console, job).
        if ($user === null) {
            return true;
        }

        return $user->can($transition->required_permission);
    }

    /** @throws WorkflowException */
    private function assertGuards(Vorgang $vorgang, WorkflowStatus $target): void
    {
        if ($target->requires_assignee && $vorgang->assigned_to_id === null) {
            throw WorkflowException::assigneeRequired($target->name);
        }

        if ($target->requires_inspection && ! $vorgang->hasCompletedInspection()) {
            throw WorkflowException::inspectionRequired($target->name);
        }
    }
}
