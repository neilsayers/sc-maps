<?php

namespace SCMaps\Frontend;

use SCMaps\Contracts\Hookable;
use SCMaps\Settings\Settings;

/**
 * [sc_map] — a Leaflet map plotting every configured marker (narrow
 * with type="shop,poi" or taxonomy="cuisine"&term="italian"), also
 * callable directly from a theme template via the static render()
 * method or the scm_render_map()/scm_the_map() functions in
 * template-functions.php.
 */
final class MapShortcode implements Hookable
{
    public const SHORTCODE_TAG = 'sc_map';

    private const DEFAULT_ATTS = [
        'type' => '',
        'taxonomy' => '',
        'term' => '',
        'ids' => '',
        'limit' => '0',
        'height' => '500',
        'zoom' => '',
        'center_lat' => '',
        'center_lng' => '',
        // 'popup' (default) matches every existing embed of this
        // plugin — clicking a marker opens the name/excerpt/image/link
        // popup it always has. 'none' turns that off for a site that's
        // building its own click behaviour instead (e.g. a page with
        // its own list of markers alongside the map).
        'on_click' => 'popup',
        // 'none' (default) matches every existing embed. 'tooltip'
        // shows the marker's name in a small bubble above it on
        // hover, and turns the pin white for the duration — see
        // assets/js/map.js.
        'on_hover' => 'none',
    ];

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_shortcode(self::SHORTCODE_TAG, [$this, 'renderShortcode']);
        \add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Styles are enqueued unconditionally on every front end request —
     * not deferred until a shortcode/scm_render_map() call is known to
     * have happened, the way the script half below is. wp_head() (and
     * so wp_print_styles()) fires before the main content/template
     * partials render, so a style only enqueued once rendering
     * actually reaches the map would already have missed its one
     * chance to print. Leaflet's CSS is small (~15KB), so the cost of
     * always loading it — matching SC Events Manager's own
     * EventListingShortcode::enqueueAssets(), which does the same for
     * its frontend.css — is negligible.
     *
     * The script half doesn't have this problem: both are registered
     * with $in_footer = true, so they print at wp_footer(), which
     * runs after all content — so renderWith() enqueues the ~150KB of
     * Leaflet JS only on a page that actually renders a map.
     */
    public function enqueueAssets(): void
    {
        \wp_enqueue_style('scm-leaflet', SCM_URL.'assets/vendor/leaflet/leaflet.css', [], '1.9.4');
        \wp_enqueue_style('scm-map', SCM_URL.'assets/css/map.css', ['scm-leaflet'], SCM_VERSION);

        \wp_register_script('scm-leaflet', SCM_URL.'assets/vendor/leaflet/leaflet.js', [], '1.9.4', true);
        \wp_register_script('scm-map', SCM_URL.'assets/js/map.js', ['scm-leaflet'], SCM_VERSION, true);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderShortcode($atts): string
    {
        $atts = \shortcode_atts(self::DEFAULT_ATTS, (array) $atts, self::SHORTCODE_TAG);

        return self::renderWith($atts, $this->settings);
    }

    /**
     * Callable directly from a theme template, e.g.:
     *   echo MapShortcode::render(['type' => 'shop', 'height' => 400]);
     *
     * @param array<string, mixed> $atts
     */
    public static function render(array $atts): string
    {
        return self::renderWith($atts + self::DEFAULT_ATTS, new Settings());
    }

    /**
     * Raw marker data for the same attributes render()/the [sc_map]
     * shortcode accept, without the HTML step — the shared query
     * behind scm_get_markers() and the REST endpoint
     * (Frontend\MapRestController), so both stay in lockstep with the
     * shortcode's own filtering instead of re-implementing it.
     *
     * @param array<string, mixed> $atts
     * @return array<int, array<string, mixed>>
     */
    public static function query(array $atts, ?Settings $settings = null): array
    {
        $merged = $atts + self::DEFAULT_ATTS;

        $ids = $merged['ids'];
        $ids = \is_array($ids)
            ? \array_map('intval', $ids)
            : \array_filter(\array_map('intval', \explode(',', (string) $ids)));

        return MarkerListing::query([
            'type' => (string) $merged['type'],
            'taxonomy' => (string) $merged['taxonomy'],
            'term' => (string) $merged['term'],
            'ids' => $ids,
            'limit' => (int) $merged['limit'],
        ], $settings);
    }

    /**
     * @param array<string, mixed> $atts
     */
    private static function renderWith(array $atts, Settings $settings): string
    {
        // Styles are already enqueued unconditionally — see
        // enqueueAssets(). Only the script is lazy, deferred until a
        // map is actually being rendered.
        \wp_enqueue_script('scm-map');

        $markers = self::query($atts, $settings);

        $height = \max(200, (int) $atts['height']);
        $id = \uniqid('scm-map-');

        $config = [
            'markers' => $markers,
            'zoom' => $atts['zoom'] !== '' ? (int) $atts['zoom'] : null,
            'center' => ($atts['center_lat'] !== '' && $atts['center_lng'] !== '')
                ? ['lat' => (float) $atts['center_lat'], 'lng' => (float) $atts['center_lng']]
                : null,
            'onClick' => $atts['on_click'] === 'none' ? 'none' : 'popup',
            'onHover' => $atts['on_hover'] === 'tooltip' ? 'tooltip' : 'none',
        ];

        \ob_start();
        ?>
        <div
            id="<?php echo \esc_attr($id); ?>"
            class="scm-map-display"
            style="height: <?php echo \esc_attr((string) $height); ?>px;"
            data-config="<?php echo \esc_attr((string) \wp_json_encode($config)); ?>"
        ></div>
        <?php if ($markers === []) : ?>
            <p class="scm-map-empty">No locations to show yet.</p>
        <?php endif; ?>
        <?php

        return (string) \ob_get_clean();
    }
}
