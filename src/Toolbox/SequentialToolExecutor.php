<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Platform\Result\ToolCall;

/**
 * Executes the requested tool calls one after another.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SequentialToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private readonly ToolboxInterface $toolbox,
    ) {
    }

    /**
     * @param ToolCall[] $toolCalls
     *
     * @return ToolResult[]
     */
    public function execute(array $toolCalls): array
    {
        $results = [];
        foreach ($toolCalls as $toolCall) {
            $results[] = $this->toolbox->execute($toolCall);
        }

        return $results;
    }
}
