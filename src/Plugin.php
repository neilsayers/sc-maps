<?php

namespace SCMaps;

use SCMaps\Admin\DocumentationPage;
use SCMaps\Admin\LocationsMenu;
use SCMaps\Admin\MarkerTypesPage;
use SCMaps\Admin\TaxonomiesPage;
use SCMaps\Frontend\MapRestController;
use SCMaps\Frontend\MapShortcode;
use SCMaps\MetaBoxes\MarkerDetailsMetaBox;
use SCMaps\PostTypes\MarkerPostTypes;
use SCMaps\Settings\Settings;
use SCMaps\Setup\UpdateChecker;
use SCMaps\Taxonomies\MarkerTaxonomies;

/**
 * Composes the plugin's features and wires them into WordPress.
 *
 * To grow the plugin (marker clustering, a distance/"near me" search,
 * an import tool, ...) write a class implementing Contracts\Hookable
 * and add it to the list in boot().
 */
final class Plugin
{
    private static ?self $instance = null;

    private Settings $settings;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->settings = new Settings();
    }

    public function boot(): void
    {
        $features = [
            new UpdateChecker(),
            new MarkerTypesPage($this->settings),
            new TaxonomiesPage($this->settings),
            new LocationsMenu(),
            new DocumentationPage(),
            new MapRestController($this->settings),
            new MapShortcode($this->settings),
            new MarkerPostTypes($this->settings),
            new MarkerTaxonomies($this->settings),
            new MarkerDetailsMetaBox($this->settings),
        ];

        foreach ($features as $feature) {
            $feature->register();
        }
    }

    public function settings(): Settings
    {
        return $this->settings;
    }
}
