<?php

/**
 * Plain template tags for theme code, alongside the REST endpoint in
 * Frontend\MapRestController and the [sc_map] shortcode — all three
 * ultimately call Frontend\MarkerListing::query(), so a theme calling
 * these directly, the shortcode, and an external caller hitting the
 * REST API always agree on what a marker list contains.
 */

use SCMaps\Frontend\MapShortcode;
use SCMaps\Frontend\MarkerListing;

if (! function_exists('scm_get_markers')) {
    /**
     * Every marker with a location set, across every configured type
     * by default — the plugin's stable data API for theme code that
     * wants to render its own markup rather than the plugin's own
     * Leaflet display.
     *
     * @param array{type?: string, taxonomy?: string, term?: string, limit?: int} $args
     * @return array<int, array<string, mixed>>
     */
    function scm_get_markers(array $args = []): array
    {
        return MarkerListing::query($args);
    }
}

if (! function_exists('scm_render_map')) {
    /**
     * The plugin's own Leaflet map as an HTML string — same markup
     * [sc_map] renders, for theme code that wants to place it outside
     * post content (e.g. a flexible-content section).
     *
     * @param array<string, mixed> $args Same attributes [sc_map] accepts: type, taxonomy, term, limit, height, zoom, center_lat, center_lng.
     */
    function scm_render_map(array $args = []): string
    {
        return MapShortcode::render($args);
    }
}

if (! function_exists('scm_the_map')) {
    /**
     * Echoes scm_render_map() — for direct use in a template file.
     *
     * @param array<string, mixed> $args
     */
    function scm_the_map(array $args = []): void
    {
        echo scm_render_map($args); // phpcs:ignore WordPress.Security.EscapeOutput -- already-escaped HTML from MapShortcode::render().
    }
}
