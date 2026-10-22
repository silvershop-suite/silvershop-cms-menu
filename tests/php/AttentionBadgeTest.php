<?php

declare(strict_types=1);

namespace SilverShop\CmsMenu\Tests;

use SilverShop\CmsMenu\AttentionBadge;
use SilverShop\CmsMenu\Tests\Stub\StubOrder;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * Proves the attention-count badge is shop-agnostic: it returns 0 (no badge) when the configured
 * order model is absent or the config is blank, and otherwise counts only the configured statuses —
 * all without a hard dependency on silvershop/core (exercised here against a {@link StubOrder}).
 */
class AttentionBadgeTest extends SapphireTest
{
    protected $usesDatabase = true;

    /**
     * @var array<int, class-string>
     */
    protected static $extra_dataobjects = [
        StubOrder::class,
    ];

    public function testReturnsZeroWhenModelClassIsAbsent(): void
    {
        // The default order_class (SilverShop\Model\Order) is not installed in this clean-room.
        Config::modify()->set(AttentionBadge::class, 'order_class', 'SilverShop\\Model\\Order');

        $this->assertSame(0, AttentionBadge::orderAttentionCount());
    }

    public function testReturnsZeroForBlankConfig(): void
    {
        Config::modify()->set(AttentionBadge::class, 'order_class', '');
        $this->assertSame(0, AttentionBadge::orderAttentionCount(), 'no model class');

        Config::modify()->set(AttentionBadge::class, 'order_class', StubOrder::class);
        Config::modify()->set(AttentionBadge::class, 'attention_statuses', []);
        $this->assertSame(0, AttentionBadge::orderAttentionCount(), 'no statuses to count');
    }

    public function testCountsOnlyTheConfiguredStatuses(): void
    {
        Config::modify()->set(AttentionBadge::class, 'order_class', StubOrder::class);
        Config::modify()->set(AttentionBadge::class, 'status_field', 'Status');
        Config::modify()->set(AttentionBadge::class, 'attention_statuses', ['Unpaid', 'Paid', 'Processing']);

        foreach (['Unpaid', 'Paid', 'Processing', 'Sent', 'Cart'] as $status) {
            $order = StubOrder::create();
            $order->Status = $status;
            $order->write();
        }

        // Unpaid + Paid + Processing are counted; Sent and Cart are not.
        $this->assertSame(3, AttentionBadge::orderAttentionCount());
    }
}
