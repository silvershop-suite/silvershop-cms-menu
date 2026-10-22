<?php

namespace SilverShop\CmsMenu\Extension;

use SilverShop\CmsMenu\CmsMenu;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\GroupedList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\View\Requirements;

/**
 * SilverShop CMS menu — groups and restyles the native CMS left menu.
 *
 * Adopted from xddesigners/silverstripe-better-menu (BSD-3): the grouping engine ({@link
 * self::GroupedMainMenu()}), the `LeftAndMain_MenuList.ss` override (only that one include, so the
 * native sidebar — collapse toggle + version indicator — stays intact), the dependency-free chevron
 * toggle and the count badges are all preserved. Trimmed: no Font Awesome loader / Pro-kit coupling
 * — per-section icons are plain class strings from `menu_icon_class` (Lucide `.ss-icon-*` by
 * default), set here from config in onInit(). The tuned icon sizing/alignment and badge styling are
 * kept verbatim, re-pointed from `fa-*` to `ss-icon-*`.
 *
 * Works out of the box with just the icon styling (no grouping needed). When groups are configured,
 * a group with 2+ present items becomes a collapsible parent; single-item/ungrouped items render as
 * plain native rows, so the menu stays native when unused.
 *
 * @extends Extension<LeftAndMain>
 */
class LeftAndMainMenuExtension extends Extension
{
    /**
     * Apply per-section icons (menu_icon_class) from the config before the menu is built.
     */
    protected function onInit(): void
    {
        foreach ($this->collectMenu()['items'] as $class => $opts) {
            if (!empty($opts['icon']) && is_string($class)) {
                Config::modify()->set($class, 'menu_icon_class', $opts['icon']);
            }
        }
    }

    protected function onAfterInit(): void
    {
        // Icon alignment (kept verbatim from better-menu's tuned look). Re-pointed to ss-icon-*:
        // the native .menu__icon is absolutely positioned, so a webfont glyph needs this to sit
        // flush-left and vertically centred in the row.
        $decls = 'top:0!important;bottom:0!important;height:auto!important;margin-top:0!important;'
            . 'width:1.25em!important;font-size:16px!important;text-align:left!important;'
            . 'display:flex!important;align-items:center!important;justify-content:flex-start!important;';

        // Global icon colour / opacity.
        $color = $this->safeColor((string) CmsMenu::config()->get('icon_color'));
        if ($color !== '') {
            $decls .= 'color:' . $color . '!important;';
        }
        $opacity = CmsMenu::config()->get('icon_opacity');
        if ($opacity !== null) {
            $decls .= 'opacity:' . (float) $opacity . '!important;';
        }

        $cssOut = '.cms-menu__list li .menu__icon[class*="ss-icon-"]{' . $decls . '}'
            // Left-align the profile name with the menu titles.
            . '.cms-login-status__profile-link{padding-left:40px!important;}'
            . '.cms-login-status__profile-text{padding-left:0!important;}';

        // Count-badge colours (the badge structure lives in the stylesheet; colour follows config).
        $badgeColor = $this->safeColor((string) CmsMenu::config()->get('badge_color'));
        $badgeText = $this->safeColor((string) CmsMenu::config()->get('badge_text_color'));
        $badgeDecls = ($badgeColor !== '' ? 'background-color:' . $badgeColor . ';' : '')
            . ($badgeText !== '' ? 'color:' . $badgeText . ';' : '');
        if ($badgeDecls !== '') {
            $cssOut .= '.cms-menu__list .cms-menu__badge{' . $badgeDecls . '}';
        }

        // Per-item colours / opacity, keyed by the menu item id (#Menu-<Code>). `color` is the base
        // colour the item's icon AND text both follow; `icon_color` / `text_color` override just one
        // of them. (Opacity applies to the icon.)
        foreach ($this->collectMenu()['items'] as $class => $opts) {
            $base = $this->safeColor((string) ($opts['color'] ?? ''));
            $iconColor = $this->safeColor((string) ($opts['icon_color'] ?? '')) ?: $base;
            $textColor = $this->safeColor((string) ($opts['text_color'] ?? '')) ?: $base;

            $iconCss = '';
            if ($iconColor !== '') {
                $iconCss .= 'color:' . $iconColor . '!important;';
            }
            if (isset($opts['opacity']) && $opts['opacity'] !== '') {
                $iconCss .= 'opacity:' . (float) $opts['opacity'] . '!important;';
            }

            $code = str_replace('\\', '-', (string) $class);
            if ($iconCss !== '') {
                $cssOut .= '#Menu-' . $code . ' .menu__icon{' . $iconCss . '}';
            }
            if ($textColor !== '') {
                $cssOut .= '#Menu-' . $code . ' .text{color:' . $textColor . '!important;}';
            }
            $badgeOverride = $this->safeColor((string) ($opts['badge_color'] ?? ''));
            if ($badgeOverride !== '') {
                $cssOut .= '#Menu-' . $code . ' .cms-menu__badge{background-color:' . $badgeOverride . '!important;}';
            }
        }

        Requirements::customCSS($cssOut, 'cms-menu-style');

        // Live badge polling (opt-in): expose the endpoint + interval to the client JS.
        $interval = (int) CmsMenu::config()->get('badge_poll_interval');
        if ($interval > 0) {
            $url = Controller::join_links(Director::baseURL(), 'admin/cms-menu/badges');
            Requirements::customScript(
                'window.__cmsMenuBadge=' . json_encode(['url' => $url, 'interval' => $interval]) . ';',
                'cms-menu-badge-cfg'
            );
        }

        // Swap the hardcoded profile + logout icons for the configured icon classes.
        $swaps = array_filter([
            '.cms-login-status__profile-icon' => trim((string) CmsMenu::config()->get('profile_icon')),
            '.cms-login-status__logout-link .font-icon-logout' => trim((string) CmsMenu::config()->get('logout_icon')),
        ]);
        if ($swaps) {
            Requirements::customScript(
                '(function(){var m=' . json_encode($swaps) . ';function a(){'
                . 'Object.keys(m).forEach(function(sel){var el=document.querySelector(sel);if(!el)return;'
                . 'Array.prototype.slice.call(el.classList).forEach(function(x){'
                . 'if(x.indexOf("font-icon")===0)el.classList.remove(x);});'
                . 'm[sel].split(/\\s+/).forEach(function(x){if(x)el.classList.add(x);});});}'
                . 'if(document.readyState!=="loading"){a();}'
                . 'else{document.addEventListener("DOMContentLoaded",a);}})();',
                'cms-menu-swap'
            );
        }
    }

    /**
     * Grouped version of the native CMS main menu, rendered by this module's
     * LeftAndMain_MenuList.ss override. With no groups configured every item is "its own group"
     * and renders as a normal row — i.e. the menu looks native (just restyled). Groups with 2+
     * present items become collapsible parents.
     */
    public function GroupedMainMenu(): ArrayList
    {
        $items = $this->getOwner()->MainMenu();
        $model = $this->collectMenu();
        $groups = $model['groups'];

        // Per-item badge counts, keyed by the dashed menu code (matches $item->Code).
        $badgeByCode = [];
        foreach ($model['items'] as $class => $opts) {
            if (!empty($opts['badge'])) {
                $badgeByCode[str_replace('\\', '-', (string) $class)] = $opts['badge'];
            }
        }

        // Map each configured menu-item Code -> group + sort metadata.
        $itemsToGroup = [];
        foreach ($groups as $group) {
            if (empty($group['codes'])) {
                continue;
            }
            $priority = $group['priority'] ?? $group['groupSort'];
            $itemSort = 0;
            foreach ($group['codes'] as $code) {
                $itemsToGroup[$code] = [
                    'Group' => $group['title'],
                    'Priority' => $priority,
                    'SortOrder' => $itemSort++,
                ];
            }
        }

        // Tag each live menu item with its group (or itself when ungrouped) and its badge.
        foreach ($items as $item) {
            $item->Badge = isset($badgeByCode[$item->Code])
                ? CmsMenu::badgeLabel((string) $badgeByCode[$item->Code])
                : '';
            if (isset($itemsToGroup[$item->Code])) {
                $item->Group = $itemsToGroup[$item->Code]['Group'];
                $item->Priority = $itemsToGroup[$item->Code]['Priority'];
                $item->SortOrder = $itemsToGroup[$item->Code]['SortOrder'];
            } else {
                $item->Group = $item->Code;
                $priority = $item->MenuItem->priority ?? null;
                $item->Priority = is_numeric($priority) ? $priority : -1;
                $item->SortOrder = 0;
            }
        }

        // Look up a group's config (icon, alphabetical) by title.
        $byTitle = [];
        foreach ($groups as $group) {
            $byTitle[$group['title']] = $group;
        }

        $result = ArrayList::create();
        $grouped = GroupedList::create($items->sort(['Priority' => 'DESC']))->groupBy('Group');

        foreach ($grouped as $groupName => $children) {
            if ($children->count() > 1 && isset($byTitle[$groupName])) {
                $cfg = $byTitle[$groupName];
                $active = false;
                foreach ($children as $child) {
                    if ($child->LinkingMode === 'current') {
                        $active = true;
                    }
                }
                $code = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', (string) $groupName), '_');
                $sortedChildren = !empty($cfg['alphabetical'])
                    ? $children->sort('Title')
                    : $children->sort('SortOrder');
                $result->push(ArrayData::create([
                    'Title' => _t('SilverShop\\CmsMenu\\Group.' . $code, $groupName),
                    'IconClass' => $cfg['icon'] ?? 'font-icon-menu-modeladmin',
                    'Code' => DBField::create_field(DBText::class, $code),
                    'Link' => $children->first()->Link,
                    'LinkingMode' => $active ? 'current' : 'link',
                    'Children' => $sortedChildren,
                ]));
            } else {
                $result->push($children->first());
            }
        }

        return $result;
    }

    /**
     * Normalise the module config into a single model:
     *   ['groups' => [ ['title','icon','priority','alphabetical','codes'[],'groupSort'] ],
     *    'items'  => [ '<Class>' => ['icon','color','icon_color','text_color','opacity','badge'] ] ]
     * Reads the rich `CmsMenu.menu` tree, the legacy `LeftAndMain.menu_groups`, and the simple
     * `CmsMenu.menu_icons` / `CmsMenu.menu_badges` maps.
     *
     * @return array{groups: array<int, array<string, mixed>>, items: array<string, array<string, mixed>>}
     */
    protected function collectMenu(): array
    {
        $groups = [];
        $items = [];
        $groupSort = 0;

        // 1) Rich tree: ordered list of group/section nodes.
        foreach ((array) CmsMenu::config()->get('menu') as $node) {
            if (!is_array($node)) {
                continue;
            }
            if (isset($node['group'])) {
                $codes = [];
                foreach (($node['children'] ?? []) as $child) {
                    if (is_string($child)) {
                        $class = $child;
                        $opts = [];
                    } elseif (is_array($child) && isset($child['section'])) {
                        $class = $child['section'];
                        $opts = $child;
                    } else {
                        continue;
                    }
                    $codes[] = str_replace('\\', '-', (string) $class);
                    $items[$class] = $this->mergeItem($items[$class] ?? [], $opts);
                }
                $groups[] = [
                    'title' => (string) $node['group'],
                    'icon' => $node['icon'] ?? null,
                    'priority' => $node['priority'] ?? null,
                    'alphabetical' => (bool) ($node['alphabetical'] ?? false),
                    'codes' => $codes,
                    'groupSort' => $groupSort--,
                ];
            } elseif (isset($node['section'])) {
                $items[$node['section']] = $this->mergeItem($items[$node['section']] ?? [], $node);
            }
        }

        // 2) Legacy LeftAndMain.menu_groups (grouped-cms-menu compatibility).
        foreach ((array) Config::inst()->get(LeftAndMain::class, 'menu_groups') as $title => $settings) {
            $codes = $settings['items'] ?? [];
            if (!is_array($codes) || !count($codes)) {
                continue;
            }
            $groups[] = [
                'title' => (string) $title,
                'icon' => $settings['icon_class'] ?? null,
                'priority' => $settings['priority'] ?? null,
                'alphabetical' => (bool) ($settings['alphabetical'] ?? false),
                'codes' => $codes,
                'groupSort' => $groupSort--,
            ];
        }

        // 3) Simple menu_icons map (icon only), as a fallback for un-styled items.
        foreach ((array) CmsMenu::config()->get('menu_icons') as $class => $icon) {
            if (is_string($class) && is_string($icon) && $icon !== '' && empty($items[$class]['icon'])) {
                $items[$class]['icon'] = $icon;
            }
        }

        // 4) Simple menu_badges map (class => count callable).
        foreach ((array) CmsMenu::config()->get('menu_badges') as $class => $callable) {
            if (is_string($class) && is_string($callable) && $callable !== '' && empty($items[$class]['badge'])) {
                $items[$class]['badge'] = $callable;
            }
        }

        return ['groups' => $groups, 'items' => $items];
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $opts
     * @return array<string, mixed>
     */
    private function mergeItem(array $existing, array $opts): array
    {
        foreach (['icon', 'color', 'icon_color', 'text_color', 'opacity', 'badge', 'badge_color'] as $k) {
            if (array_key_exists($k, $opts) && $opts[$k] !== null && $opts[$k] !== '') {
                $existing[$k] = $opts[$k];
            }
        }
        return $existing;
    }

    private function safeColor(string $color): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9#(),.%\s\/-]/', '', trim($color));
    }
}
