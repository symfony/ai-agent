<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('tool_scalar_float', 'A tool with float parameters')]
final class ToolScalarFloat
{
    public function __invoke(float $height, ?float $weight = null): string
    {
        return \sprintf('Height: %.2fm, Weight: %.2fkg', $height, $weight);
    }
}
