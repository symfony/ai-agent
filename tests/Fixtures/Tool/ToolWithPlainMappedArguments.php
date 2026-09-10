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
use Symfony\AI\Agent\Toolbox\Attribute\MapToolArguments;
use Symfony\Component\Validator\Constraints as Assert;

#[AsTool('tool_with_plain_mapped_arguments', 'A mapped tool without schema providers')]
final class ToolWithPlainMappedArguments
{
    public function __invoke(
        #[MapToolArguments]
        PlainRecipe $recipe,
    ): string {
        return \sprintf('Ingredient: %s', $recipe->ingredient);
    }
}

final class PlainRecipe
{
    public function __construct(
        #[Assert\Choice(choices: ['flour', 'sugar', 'butter'], message: 'The value must be one of {{ choices }}.')]
        public string $ingredient,
        public int $servingSize = 1,
    ) {
    }
}
