<?php

namespace SilverShop\CmsMenu\Tests\Stub;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Minimal stand-in for an order model, so {@link \SilverShop\CmsMenu\AttentionBadge}'s status
 * counting can be exercised without silvershop/core installed.
 */
class StubOrder extends DataObject implements TestOnly
{
    private static $table_name = 'CmsMenu_Test_StubOrder';

    private static $db = [
        'Status' => 'Varchar(20)',
    ];
}
