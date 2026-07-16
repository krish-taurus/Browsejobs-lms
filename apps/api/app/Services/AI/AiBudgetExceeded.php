<?php

declare(strict_types=1);

namespace App\Services\AI;

use RuntimeException;

/**
 * Thrown when a student's per-day AI token budget is exhausted (CLAUDE.md).
 */
final class AiBudgetExceeded extends RuntimeException {}
