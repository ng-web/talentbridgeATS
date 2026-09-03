<?php

namespace App\Services\Privacy;

interface DispositionExecutionBarrier
{
    public const BEFORE_CATEGORY_FENCE = 'before_category_fence';

    public const BEFORE_SUBJECT_FENCE = 'before_subject_fence';

    public const BEFORE_AUTHORITY_FENCE = 'before_authority_fence';

    public const BEFORE_DESTRUCTIVE_MUTATION = 'before_destructive_mutation';

    public function reached(string $point): void;
}
