<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

use Symfony\AI\Agent\Exception\MaxIterationsExceededException;

/**
 * Budget of tool calling rounds belonging to one outermost agent call, shared with its nested calls.
 *
 * It is created when the outermost call starts and carried into the streaming listener, so that agent
 * calls started before their stream is consumed do not spend each other's budget.
 *
 * @author Ousama Ben Younes <benyounes.ousama@gmail.com>
 *
 * @internal
 */
final class ToolCallBudget
{
    private int $iterations = 0;

    public function __construct(
        private readonly ?int $maxToolCalls,
    ) {
    }

    /**
     * @throws MaxIterationsExceededException when the configured number of tool calling rounds is exhausted
     */
    public function consume(): void
    {
        if (null === $this->maxToolCalls) {
            return;
        }

        ++$this->iterations;

        if ($this->iterations > $this->maxToolCalls) {
            throw new MaxIterationsExceededException($this->maxToolCalls);
        }
    }
}
