<?php

namespace SCMaps\PostTypes;

use SCMaps\Admin\LocationsMenu;
use SCMaps\Contracts\Hookable;
use SCMaps\Settings\Settings;

/**
 * Registers every configured marker type as its own post type. Nothing
 * is registered until at least one type has been created via the
 * admin screen — see Admin\MarkerTypesPage.
 */
final class MarkerPostTypes implements Hookable
{
    /**
     * A flush must happen on the request *after* a new type is first
     * registered — flushing during the same request that saves it is
     * too early, since register_post_type() for it hasn't run yet at
     * that point in the request lifecycle (init fires before the
     * admin-post.php handler that saves it). Admin\MarkerTypesPage
     * sets this transient instead of flushing directly.
     */
    public const FLUSH_TRANSIENT = 'scm_flush_rewrite_rules';

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('init', [$this, 'registerPostTypes']);
    }

    public function registerPostTypes(): void
    {
        foreach ($this->settings->allMarkerTypes() as $markerType) {
            \register_post_type($markerType['post_type'], [
                'labels' => $this->buildLabels($markerType['label_singular'], $markerType['label_plural']),
                'public' => true,
                'has_archive' => true,
                'show_in_rest' => false,
                'show_in_menu' => LocationsMenu::PARENT_SLUG,
                'menu_icon' => 'dashicons-location',
                'rewrite' => ['slug' => $markerType['slug']],
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            ]);
        }

        if (\get_transient(self::FLUSH_TRANSIENT)) {
            \delete_transient(self::FLUSH_TRANSIENT);
            \flush_rewrite_rules();
        }
    }

    private function buildLabels(string $singular, string $plural): array
    {
        return [
            'name' => $plural,
            'singular_name' => $singular,
            'add_new' => \sprintf('Add New %s', $singular),
            'add_new_item' => \sprintf('Add New %s', $singular),
            'edit_item' => \sprintf('Edit %s', $singular),
            'new_item' => \sprintf('New %s', $singular),
            'view_item' => \sprintf('View %s', $singular),
            'view_items' => \sprintf('View %s', $plural),
            'search_items' => \sprintf('Search %s', $plural),
            'not_found' => \sprintf('No %s found', \strtolower($plural)),
            'not_found_in_trash' => \sprintf('No %s found in Trash', \strtolower($plural)),
            'all_items' => \sprintf('All %s', $plural),
            'archives' => \sprintf('%s Archives', $singular),
            'menu_name' => $plural,
            'name_admin_bar' => $singular,
        ];
    }
}
