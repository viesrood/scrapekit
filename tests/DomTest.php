<?php

declare(strict_types=1);

namespace viesrood\scrapekit\tests;

use PHPUnit\Framework\TestCase;
use viesrood\scrapekit\dom\Crawler;

final class DomTest extends TestCase
{
    public function testHtmlQueriesAndTraversal(): void
    {
        $crawler = new Crawler(
            '<main><article class="card featured"><a href="/one"> One </a></article><article class="card">Two</article></main>',
        );

        self::assertSame(2, $crawler->find('.card')->count());
        self::assertSame('One', trim($crawler->text('.card a')));
        self::assertSame('/one', $crawler->attr('a', 'href'));
        self::assertTrue($crawler->first('.card')->hasClass('featured'));
        self::assertSame('main', $crawler->first('.card')?->parent()?->nodeName());
    }

    public function testXmlUsesXmlParser(): void
    {
        $crawler = new Crawler(
            '<?xml version="1.0"?><feed><Item id="1">First</Item><Item id="2">Second</Item></feed>',
            format: 'xml',
        );

        self::assertSame(['First', 'Second'], $crawler->findXPath('//Item')->texts());
        self::assertSame('2', $crawler->findXPath('//Item')->last()?->attr('id'));
    }

    public function testInvalidSelectorReturnsEmptyList(): void
    {
        $crawler = new Crawler('<p>Test</p>');

        self::assertCount(0, $crawler->find('['));
    }
}
