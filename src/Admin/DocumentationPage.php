<?php

namespace SCMaps\Admin;

use SCMaps\Contracts\Hookable;
use SCMaps\Frontend\MapShortcode;

/**
 * "Documentation" — a plain reference for every way this plugin's
 * marker data can be consumed: the PHP function API, the REST
 * endpoint, and the [sc_map] shortcode, plus the marker data field
 * reference. Same shape as SC Events Manager's own DocumentationPage.
 *
 * No settings live here — it's read-only, so there's nothing to
 * register_setting() or admin_post_ handle.
 */
final class DocumentationPage implements Hookable
{
    private const PAGE_SLUG = 'scm-documentation';

    /**
     * Attributes shared by the shortcode, scm_get_markers()/
     * scm_render_map(), and the REST endpoint's query params —
     * described once here rather than three times, since all three
     * accept exactly the same set (the REST endpoint's registered
     * args carry the same text, kept in sync by hand since
     * \WP_REST_Server needs its own array shape).
     *
     * @var array<string, string>
     */
    private const SHARED_ATTRIBUTES = [
        'type' => 'Comma-separated marker type post_type keys, e.g. "shop,poi". Empty (the default) means every configured type.',
        'taxonomy' => 'A custom taxonomy slug to filter by, paired with term. Default: none.',
        'term' => 'The term slug within taxonomy to filter by. Default: none.',
        'ids' => 'Comma-separated marker post IDs — plots exactly these markers, ignoring type/taxonomy/term. Default: none.',
        'limit' => 'Maximum markers to return. Default 0 (no limit).',
    ];

    /**
     * Attributes only the shortcode/scm_render_map() understand —
     * they shape the rendered map itself rather than which markers
     * are included, so the REST endpoint (which returns raw data, no
     * map) has no equivalent.
     *
     * @var array<string, string>
     */
    private const DISPLAY_ATTRIBUTES = [
        'height' => 'Map height in pixels. Default 500.',
        'zoom' => 'Initial zoom level. Default: auto — fits every marker in view.',
        'center_lat' => 'Force the map to open centred on this latitude instead of auto-fitting. Pair with center_lng.',
        'center_lng' => 'Force the map to open centred on this longitude instead of auto-fitting. Pair with center_lat.',
    ];

    /**
     * @var array<string, string>
     */
    private const MARKER_FIELDS = [
        'id' => 'The marker\'s post ID.',
        'type' => 'The marker type\'s post_type key.',
        'type_label' => 'The marker type\'s singular label, e.g. "Shop".',
        'name' => 'The marker\'s title.',
        'excerpt' => 'The marker\'s excerpt (or an auto-generated one).',
        'content' => 'The marker\'s full content, with filters (shortcodes, blocks) already applied.',
        'featured_image_url' => 'Medium-size featured image URL, or an empty string if none is set.',
        'view_url' => 'The marker\'s own front-end permalink.',
        'lat' => 'Latitude, as a float.',
        'lng' => 'Longitude, as a float.',
        'colour' => 'The marker type\'s colour, as a hex string.',
        'icon' => 'The marker type\'s icon URL, or an empty string if none was chosen.',
        'terms' => 'An object keyed by taxonomy slug, each value an array of that marker\'s term names.',
    ];

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        if (($_GET['page'] ?? '') !== self::PAGE_SLUG) {
            return;
        }

        \wp_enqueue_style('scm-admin', SCM_URL.'assets/css/admin.css', [], SCM_VERSION);
    }

    public function registerMenu(): void
    {
        \add_submenu_page(
            'scm-settings',
            'Documentation',
            'Documentation',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (! \current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap scm-docs">
            <h1>SC Maps — Documentation</h1>

            <p>
                Three ways to pull marker data into a theme or an external tool, all backed by the same query
                (<code>MarkerListing::query()</code>) — filtering behaves identically whichever one you use. Markers
                with no location set are skipped by all three, since there's nothing to plot.
            </p>

            <?php $this->renderFunctionSection(); ?>
            <?php $this->renderRestSection(); ?>
            <?php $this->renderShortcodeSection(); ?>
            <?php $this->renderFieldReferenceSection(); ?>
        </div>
        <?php
    }

    private function renderFunctionSection(): void
    {
        ?>
        <h2>PHP: <code>scm_get_markers()</code> / <code>scm_render_map()</code></h2>
        <p>
            For theme code on this same site — no HTTP round trip. <code>scm_get_markers()</code> returns raw data
            for you to render your own markup with; <code>scm_render_map()</code> (or its echoing sibling
            <code>scm_the_map()</code>) returns the plugin's own Leaflet map as an HTML string, same as the
            shortcode. These are the plugin's stable PHP contract: the classes underneath them
            (<code>MarkerListing</code>, <code>MapShortcode</code>) can change between versions, but these
            functions' parameters and return shapes won't, within a major version.
        </p>
        <pre class="scm-docs-code">$markers = scm_get_markers([
    'type'  =&gt; 'shop',
    'limit' =&gt; 5,
]);

foreach ($markers as $marker) {
    echo esc_html($marker['name']) . ' — ' . esc_html($marker['type_label']);
}

// Or let the plugin draw its own map:
scm_the_map(['type' =&gt; 'shop', 'height' =&gt; 400]);</pre>

        <?php $this->renderAttributesTable(true); ?>
        <?php
    }

    private function renderRestSection(): void
    {
        ?>
        <h2>REST: <code>GET /wp-json/scm/v1/markers</code></h2>
        <p>
            For anything outside this site's own PHP — a decoupled front end, another site, a build step. Public
            and read-only; no authentication needed, since every field it returns is already visible on the
            marker's own front-end page.
        </p>
        <p>
            <code><?php echo \esc_html(\rest_url('scm/v1/markers')); ?>?type=shop&amp;limit=5</code>
        </p>
        <p>
            <strong>v1 is a promise:</strong> existing fields won't be renamed or removed within it. A field can be
            added; a genuinely breaking change gets its own <code>scm/v2</code> route instead, so anything already
            built against v1 keeps working.
        </p>

        <?php $this->renderAttributesTable(false); ?>

        <p>Response shape:</p>
        <pre class="scm-docs-code">{
  "markers": [ { "id": 12, "name": "…", "lat": 50.97, "lng": 0.10, … } ],
  "total": 1
}</pre>
        <p class="description">Identical to <code>scm_get_markers()</code>'s field set. See the data reference below for what each field contains.</p>
        <?php
    }

    private function renderShortcodeSection(): void
    {
        ?>
        <h2>Shortcode: <code>[<?php echo \esc_html(MapShortcode::SHORTCODE_TAG); ?>]</code></h2>
        <p>For post/page content — renders the plugin's own interactive Leaflet map directly, e.g. <code>[sc_map type="shop" height="400"]</code>.</p>

        <?php $this->renderAttributesTable(true); ?>
        <?php
    }

    private function renderAttributesTable(bool $includeDisplayAttributes): void
    {
        $attributes = self::SHARED_ATTRIBUTES;

        if ($includeDisplayAttributes) {
            $attributes += self::DISPLAY_ATTRIBUTES;
        }
        ?>
        <table class="widefat striped" style="max-width: 900px;">
            <thead>
                <tr>
                    <th>Attribute</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attributes as $attribute => $description) : ?>
                    <tr>
                        <td><code><?php echo \esc_html($attribute); ?></code></td>
                        <td><?php echo \esc_html($description); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderFieldReferenceSection(): void
    {
        ?>
        <h2>Data reference — marker fields</h2>
        <p>
            Every field <code>scm_get_markers()</code> and the REST endpoint return for each marker.
        </p>

        <table class="widefat striped" style="max-width: 900px;">
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (self::MARKER_FIELDS as $field => $description) : ?>
                    <tr>
                        <td><code><?php echo \esc_html($field); ?></code></td>
                        <td><?php echo \esc_html($description); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
