<?php

namespace App\Services\Privacy;

final class NullDispositionExecutionBarrier implements DispositionExecutionBarrier
{
    public function reached(string $point): void
    {
        // Production execution has no synchronization side effects.
    }
}
