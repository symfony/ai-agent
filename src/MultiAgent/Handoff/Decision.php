<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\MultiAgent\Handoff;

/**
 * Represents the orchestrator's decision on which agent should handle a request.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final readonly class Decision
{
    /**
     * @param string $agentName The name of the selected agent, or empty string if no specific agent is selected
     * @param string $reasoning The reasoning behind the selection
     */
    public function __construct(
        public string $agentName,
        public string $reasoning = 'No reasoning provided',
    ) {
    }

    /**
     * Checks if a specific agent was selected.
     */
    public function hasAgent(): bool
    {
        return '' !== $this->agentName;
    }
}
