<?php

namespace App\Services\Privacy;

final class NullIncidentMutationBarrier implements IncidentMutationBarrier
{
    public function reached(string $point): void {}
}
