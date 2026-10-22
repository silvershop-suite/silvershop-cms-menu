<?php

namespace SilverShop\CmsMenu;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;

/**
 * Config-driven count provider for the "orders needing attention" badge on the Orders menu item.
 *
 * Shop-agnostic by design: it references the Order model by its string class name and guards with
 * `is_subclass_of()`, so this module carries no hard dependency on silvershop/core — if the model
 * isn't installed the count is simply 0. Everything is overridable in YAML:
 *
 *     SilverShop\CmsMenu\AttentionBadge:
 *       order_class: 'SilverShop\Model\Order'
 *       status_field: 'Status'
 *       attention_statuses: ['Unpaid', 'Paid', 'Processing']
 *
 * To count something else entirely, point the menu item's `badge:` at your own callable instead —
 * the badge mechanism accepts any `Class::method` string (see {@link CmsMenu::badgeCount()}).
 */
class AttentionBadge
{
    use Configurable;

    /**
     * The DataObject subclass to count. String (not a ::class reference) so the module stays
     * decoupled from silvershop/core.
     *
     * @config
     */
    private static string $order_class = 'SilverShop\\Model\\Order';

    /**
     * The status field on that model.
     *
     * @config
     */
    private static string $status_field = 'Status';

    /**
     * The statuses that count as "needs attention". Default = placed-but-not-yet-fulfilled orders
     * (excludes Sent/Complete/Cancelled and the Cart pseudo-status).
     *
     * @config
     * @var array<int, string>
     */
    private static array $attention_statuses = ['Unpaid', 'Paid', 'Processing'];

    /**
     * Count orders whose status is in {@link self::$attention_statuses}. Returns 0 (no badge) when
     * the model is absent or the query fails.
     */
    public static function orderAttentionCount(): int
    {
        $class = (string) static::config()->get('order_class');
        if ($class === '' || !is_subclass_of($class, DataObject::class)) {
            return 0;
        }

        $field = (string) static::config()->get('status_field');
        $statuses = array_values(array_filter(
            (array) static::config()->get('attention_statuses'),
            'is_string'
        ));
        if ($field === '' || $statuses === []) {
            return 0;
        }

        try {
            /** @var class-string<DataObject> $class */
            return (int) DataObject::get($class)->filter([$field => $statuses])->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
