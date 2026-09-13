<?php

namespace App\Events;

use App\Models\User;
use App\Models\Vorgang;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VorgangAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Vorgang $vorgang,
        public readonly User $assignee,
        public readonly ?User $actor = null,
    ) {
    }
}
