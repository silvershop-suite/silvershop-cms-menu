# silvershop-suite/cms-menu

Grouped, collapsible **CMS left menu** for the SilverShop suite (Silverstripe CMS 6 / admin 3).
It nests the stock flat admin menu into **config-driven groups** with **icons**, a dependency-free
**chevron toggle**, **active-group auto-expand**, and **count badges** (e.g. orders needing
attention) — while leaving the rest of the native sidebar (logo, version indicator, collapse
toggle) untouched.

Shop-agnostic and native-blend: it overrides **only** the innermost `LeftAndMain_MenuList.ss`
include and styles its own `.cms-menu__group*` markup, so it reads as part of the stock admin (and
inherits its dark mode). Adopted from [`xddesigners/silverstripe-better-menu`](https://github.com/xddesigners/silverstripe-better-menu)
(BSD-3) — the grouping engine, the vanilla-JS toggle and the badge mechanism are preserved; the
Font Awesome loader is trimmed in favour of plain icon class strings (Lucide `.ss-icon-*` by
default).

## Install

```bash
composer require silvershop-suite/cms-menu
```

Then `dev/build?flush=1`. The module ships the SilverShop menu information architecture as config;
out of the box it groups whatever suite sections are installed and leaves everything else native.

> **Mutually exclusive with `xddesigners/silverstripe-better-menu`.** Both override the same
> `LeftAndMain_MenuList.ss` include and add a `LeftAndMain` extension — install one or the other,
> not both. cms-menu is the suite's replacement for better-menu.

## Icons

Icons are plain class strings rendered verbatim by the stock `menu_icon_class` mechanism — no font
is loaded by this module. The default IA uses **Lucide `.ss-icon-*`** glyphs, provided by
`silvershop-suite/cms-ui` today (splitting into `silvershop-suite/cms-icons`). That provider is a `suggest`, not
a hard `require`: without it the menu still groups and functions — the icons just render as tofu.
You may equally use stock `font-icon-*` names or any other webfont class in the config.

## Configuration

The menu is an ordered tree of **group** and **section** nodes:

```yaml
SilverShop\CmsMenu\CmsMenu:
  menu:
    - group: 'Shop'                       # collapsible parent (when 2+ children are present)
      icon: 'ss-icon-shopping-bag'
      priority: 100                       # group order (DESC)
      children:
        - section: 'SilverShop\Admin\OrdersAdmin'
          icon: 'ss-icon-shopping-cart'
          badge: 'SilverShop\CmsMenu\AttentionBadge::orderAttentionCount'
    - section: 'SilverStripe\Admin\SecurityAdmin'   # bare section = native row, just re-iconed
      icon: 'ss-icon-shield-check'
```

- A **group** with 2+ present children becomes a collapsible parent; with 0–1 present it collapses
  to an inline row (or nothing), so a site shows only what it installed.
- **Unlisted** sections keep their native position and icon.
- **Order within a group** follows config order, or set `alphabetical: true` on the group.
- A module can append its own section into a group from its own YAML (config arrays merge), or via
  the legacy `LeftAndMain.menu_groups` format (also read, for grouped-cms-menu compatibility).

### Count badges

A section's `badge:` is any `Class::method` callable string returning a count (int, numeric string,
or anything `Countable` / an `SS_List`). `0` shows no badge; counts over `badge_max` render as
`99+`. Badges render server-side on every menu navigation; opt into live polling with
`badge_poll_interval` (seconds).

The shipped **orders-needing-attention** badge is fully config-driven and carries no hard dependency
on silvershop/core (it resolves the model by string name and no-ops if absent):

```yaml
SilverShop\CmsMenu\AttentionBadge:
  order_class: 'SilverShop\Model\Order'
  status_field: 'Status'
  attention_statuses: ['Unpaid', 'Paid', 'Processing']   # placed, not yet fulfilled/cancelled
```

To count something else, point the item's `badge:` at your own callable instead.

### Appearance

```yaml
SilverShop\CmsMenu\CmsMenu:
  icon_color: '#005a93'        # menu icon colour (empty = inherit the sidebar default)
  icon_opacity: 0.8
  badge_color: '#e74c3c'       # badge pill background
  badge_text_color: '#ffffff'
  profile_icon: 'ss-icon-circle-user'   # top-of-menu profile/logout icons (swapped client-side)
  logout_icon: 'ss-icon-log-out'
```

## How it works

- **`LeftAndMainMenuExtension`** sets each section's `menu_icon_class` from config (onInit), emits
  the icon/badge colour CSS (onAfterInit), and exposes **`GroupedMainMenu()`** — which re-groups the
  native `MainMenu()` into collapsible parents via `GroupedList`.
- **`LeftAndMain_MenuList.ss`** (the only overridden template) loops `$GroupedMainMenu`.
- **`client/js/cms-menu.js`** binds the chevron toggle and re-binds after each PJAX menu swap
  (MutationObserver). Collapse state is ephemeral — the active group auto-expands from the server
  render on every navigation.
- **`BadgeController`** (`admin/cms-menu/badges`) serves live counts for polling (auth-guarded,
  limited to sections the user can see).

## License

BSD-3-Clause. Grouping engine, toggle and badge mechanism adopted from
`xddesigners/silverstripe-better-menu` (BSD-3, © Remy Vaartjes / XD).
