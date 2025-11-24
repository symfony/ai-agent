<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Tool;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Tool\YouTubeTranscriber;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class YouTubeTranscriberTest extends TestCase
{
    public function testInvoke()
    {
        $httpClient = new MockHttpClient([
            MockResponse::fromFile(__DIR__.'/../../Fixtures/Tool/youtube-video-page.html'),
            MockResponse::fromFile(__DIR__.'/../../Fixtures/Tool/youtube-captions.json'),
            MockResponse::fromFile(__DIR__.'/../../Fixtures/Tool/youtube-transcript.xml'),
        ]);

        $transcriber = new YouTubeTranscriber($httpClient);

        $result = $transcriber('dQw4w9WgXcQ');

        $this->assertStringContainsString('Hello and welcome to this video', $result);
        $this->assertStringContainsString('Today we will learn about PHP', $result);
        $this->assertStringContainsString('Thank you for watching', $result);
    }
}
