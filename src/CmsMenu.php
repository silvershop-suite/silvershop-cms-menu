<?php

namespace SilverShop\CmsMenu;

use SilverStripe\Core\Config\Configurable;

/**
 * Configuration holder for the SilverShop grouped CMS left menu.
 *
 * Adopted from xddesigners/silverstripe-better-menu (BSD-3), trimmed for the SilverShop suite: the
 * Font Awesome loader / Pro-kit plumbing is gone — icons are plain class strings (Lucide
 * `.ss-icon-*` by default, provided by silvershop-suite/cms-ui / silvershop-suite/cms-icons), rendered verbatim
 * by the stock `menu_icon_class` mechanism. The grouping engine, count badges and the native-blend
 * styling are preserved.
 *
 * Configure in YAML (see `_config/cms-menu.yml` for the shipped suite IA):
 *
 *     SilverShop\CmsMenu\CmsMenu:
 *       menu:
 *         - group: 'Shop'
 *           icon: 'ss-icon-shopping-bag'
 *           priority: 100
 *           children:
 *             - section: 'SilverShop\Admin\OrdersAdmin'
 *               icon: 'ss-icon-shopping-cart'
 *               badge: 'SilverShop\CmsMenu\AttentionBadge::orderAttentionCount'
 */
class CmsMenu
{
    use Configurable;

    /**
     * Ordered tree of group/section nodes. A `group` node collapses its `children` into a
     * collapsible parent (when 2+ of them are actually present); a bare `section` node just
     * re-icons a native row. Unlisted sections keep their native position. Config arrays merge, so
     * a module may append its own section into a group from its own YAML.
     *
     * @config
     */
    private static array $menu = [];

    /**
     * Simple map of admin controller class => icon class (a CMS `font-icon-*` name, a Lucide
     * `ss-icon-*` name, or any other webfont class). A fallback for items not placed in `menu`.
     *
     * @config
     */
    private static array $menu_icons = [];

    /**
     * Map of admin controller class => a callable (as a string) returning the count shown in that
     * section's left-menu badge, e.g. `'SilverShop\CmsMenu\AttentionBadge::orderAttentionCount'`.
     * Invoked server-side on each menu render; `0` (or less) shows no badge. The callable may
     * return an int, a numeric string, or any Countable / SS_List (its `count()` is used).
     *
     * @config
     */
    private static array $menu_badges = [];

    /**
     * Default background colour for the count badges (any CSS colour).
     *
     * @config
     */
    private static string $badge_color = '#e74c3c';

    /**
     * Text colour for the count badges.
     *
     * @config
     */
    private static string $badge_text_color = '#ffffff';

    /**
     * Cap shown on a badge; counts above it render as "<max>+" (e.g. 120 → "99+"). 0 disables it.
     *
     * @config
     */
    private static int $badge_max = 99;

    /**
     * Seconds between live badge refreshes (client-side polling of the badge endpoint). `0`
     * (default) disables polling — badges then refresh only when the CMS menu re-renders (on
     * navigation). When > 0, the module's {@link \SilverShop\CmsMenu\Control\BadgeController} is
     * polled.
     *
     * @config
     */
    private static int $badge_poll_interval = 0;

    /**
     * Replace the profile (user) icon at the top of the CMS menu with these icon classes
     * (e.g. `ss-icon-circle-user`). Empty leaves the built-in icon. That icon isn't a menu
     * icon-class, so it's swapped client-side.
     *
     * @config
     */
    private static string $profile_icon = '';

    /**
     * Replace the logout (exit) icon at the top of the CMS menu with these icon classes
     * (e.g. `ss-icon-log-out`). Empty leaves the built-in icon. Swapped client-side like the
     * profile icon.
     *
     * @config
     */
    private static string $logout_icon = '';

    /**
     * Global colour for the menu icons (any CSS colour). Empty leaves the CMS default (inherited
     * sidebar colour). Defaults to the SilverStripe dark-blue header colour.
     *
     * @config
     */
    private static string $icon_color = '';

    /**
     * Global opacity for the menu icons (0–1). Null leaves it as-is.
     *
     * @config
     */
    private static ?float $icon_opacity = null;

    /**
     * Map of admin controller class => count callable, merged from the `menu` tree (`badge`) and
     * the `menu_badges` map (tree wins). Shared by the menu renderer and the polling endpoint.
     *
     * @return array<string, string>
     */
    public static function badgeCallables(): array
    {
        $map = [];
        foreach ((array) static::config()->get('menu') as $node) {
            if (!is_array($node)) {
                continue;
            }
            if (isset($node['group'])) {
                foreach (($node['children'] ?? []) as $child) {
                    if (is_array($child) && isset($child['section'], $child['badge'])) {
                        $map[$child['section']] = $child['badge'];
                    }
                }
            } elseif (isset($node['section'], $node['badge'])) {
                $map[$node['section']] = $node['badge'];
            }
        }
        foreach ((array) static::config()->get('menu_badges') as $class => $callable) {
            if (is_string($class) && is_string($callable) && $callable !== '' && empty($map[$class])) {
                $map[$class] = $callable;
            }
        }
        return $map;
    }

    /**
     * Formatted badge label for a count callable ('' when there's nothing to show), applying the
     * `badge_max` cap (e.g. 120 → "99+").
     */
    public static function badgeLabel(string $callable): string
    {
        $n = static::badgeCount($callable);
        if ($n <= 0) {
            return '';
        }
        $max = (int) static::config()->get('badge_max');
        return ($max > 0 && $n > $max) ? $max . '+' : (string) $n;
    }

    /**
     * Resolve a count callable to an int. Accepts an int / numeric, or any Countable / SS_List
     * (its count() is used). A non-callable or throwing callable resolves to 0.
     */
    public static function badgeCount(string $callable): int
    {
        if ($callable === '' || !is_callable($callable)) {
            return 0;
        }
        try {
            $result = call_user_func($callable);
        } catch (\Throwable $e) {
            return 0;
        }
        if (is_numeric($result)) {
            return (int) $result;
        }
        if (is_object($result) && method_exists($result, 'count')) {
            return (int) $result->count();
        }
        if (is_countable($result)) {
            return count($result);
        }
        return 0;
    }
}
