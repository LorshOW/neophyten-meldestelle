<?php

namespace App\Events;

use App\Models\User;
use App\Models\Vorgang;
use App\Models\WorkflowStatus;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VorgangStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Vorgang $vorgang,
        public readonly ?WorkflowStatus $from,
        public readonly WorkflowStatus $to,
        public readonly ?User $actor = null,
        public readonly ?string $note = null,
    ) {
    }
}
