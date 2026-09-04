<?php

namespace SCMaps\Admin;

use SCMaps\Contracts\Hookable;

/**
 * A single top-level "Map Locations" menu that every configured
 * marker type's own post list nests under, instead of each type
 * getting its own top-level admin menu entry — see PostTypes\
 * MarkerPostTypes, which points every type's show_in_menu at
 * PARENT_SLUG. WordPress's own _add_post_type_submenus() then adds
 * exactly one submenu item per type (its "All X" list) automatically
 * — a custom show_in_menu parent only gets that, not an "Add New"
 * entry the way a post type's own default top-level menu does — so,
 * deliberately, that's the only per-type link this menu ever shows.
 * "Add New" is still one click away, from inside a type's own list.
 */
final class LocationsMenu implements Hookable
{
    public const PARENT_SLUG = 'scm-locations';

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        \add_menu_page(
            'Map Locations',
            'Map Locations',
            'edit_posts',
            self::PARENT_SLUG,
            [$this, 'renderPage'],
            'dashicons-location',
            29
        );
    }

    public function renderPage(): void
    {
        ?>
        <div class="wrap">
            <h1>Map Locations</h1>
            <p>Pick a marker type from the menu on the left to see its own list.</p>
        </div>
        <?php
    }
}
