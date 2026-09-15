<?php

namespace App\Services\Privacy;

interface IncidentMutationBarrier
{
    public const AFTER_CREATE_AUTHORITY = 'after_create_authority';

    public const AFTER_INCIDENT_LOCK = 'after_incident_lock';

    public function reached(string $point): void;
}
