<?php

declare(strict_types=1);

namespace SilverShop\CmsMenu\Tests;

use SilverShop\CmsMenu\CmsMenu;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers the badge helpers and the badge-callable merge: resolving a count callable to an int
 * (with the blank / missing / throwing guards), the formatted label with the `badge_max` cap, and
 * {@link CmsMenu::badgeCallables()} merging the rich `menu` tree over the flat `menu_badges` map.
 */
class CmsMenuTest extends SapphireTest
{
    public static function countZero(): int
    {
        return 0;
    }

    public static function countFive(): int
    {
        return 5;
    }

    public static function countHundredTwenty(): int
    {
        return 120;
    }

    public static function countThrows(): int
    {
        throw new \RuntimeException('boom');
    }

    public function testBadgeCountResolvesNumericCallables(): void
    {
        $this->assertSame(5, CmsMenu::badgeCount(self::class . '::countFive'));
        $this->assertSame(0, CmsMenu::badgeCount(self::class . '::countZero'));
    }

    public function testBadgeCountIsZeroForBlankMissingOrThrowingCallable(): void
    {
        $this->assertSame(0, CmsMenu::badgeCount(''), 'blank');
        $this->assertSame(0, CmsMenu::badgeCount('Not\\A\\Class::nope'), 'not callable');
        $this->assertSame(0, CmsMenu::badgeCount(self::class . '::countThrows'), 'throwing callable');
    }

    public function testBadgeLabelAppliesMaxCap(): void
    {
        Config::modify()->set(CmsMenu::class, 'badge_max', 99);

        $this->assertSame('', CmsMenu::badgeLabel(self::class . '::countZero'), 'zero shows no badge');
        $this->assertSame('5', CmsMenu::badgeLabel(self::class . '::countFive'));
        $this->assertSame('99+', CmsMenu::badgeLabel(self::class . '::countHundredTwenty'), 'over cap → N+');
    }

    public function testBadgeLabelIsUncappedWhenMaxIsZero(): void
    {
        Config::modify()->set(CmsMenu::class, 'badge_max', 0);

        $this->assertSame('120', CmsMenu::badgeLabel(self::class . '::countHundredTwenty'));
    }

    public function testBadgeCallablesMergeTreeOverMap(): void
    {
        Config::modify()->set(CmsMenu::class, 'menu', [
            ['group' => 'Shop', 'children' => [
                ['section' => 'My\\OrdersAdmin', 'badge' => 'Tree::count'],
                'My\\PlainAdmin',
            ]],
            ['section' => 'My\\TopAdmin', 'badge' => 'Top::count'],
        ]);
        Config::modify()->set(CmsMenu::class, 'menu_badges', [
            'My\\OrdersAdmin' => 'Map::shouldNotWin',
            'My\\OtherAdmin' => 'Map::count',
        ]);

        $map = CmsMenu::badgeCallables();

        $this->assertSame('Tree::count', $map['My\\OrdersAdmin'], 'the tree wins over menu_badges');
        $this->assertSame('Top::count', $map['My\\TopAdmin']);
        $this->assertSame('Map::count', $map['My\\OtherAdmin']);
        $this->assertArrayNotHasKey('My\\PlainAdmin', $map, 'a child without a badge is not listed');
    }
}
