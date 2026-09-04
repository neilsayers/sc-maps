<?php

namespace SCMaps\MetaBoxes;

use SCMaps\Contracts\Hookable;
use SCMaps\Settings\Settings;
use SCMaps\Support\MarkerMeta;
use SCMaps\Support\MetaField;

/**
 * Hand-written "Location" box rendered above the content editor via
 * edit_form_after_title — add_meta_box() can only place things below
 * it. Same technique SC Events Manager's own EventDetailsMetaBox and
 * SC Room Bookings' RoomDetailsMetaBox use, independently
 * implemented here rather than shared — see sc-maps.php's own
 * docblock for why.
 *
 * Everything else about a marker (name, photo, description shown in
 * its map popup) is just the post's own title/thumbnail/content —
 * this box only owns the one thing that's genuinely marker-specific:
 * where it is.
 */
final class MarkerDetailsMetaBox implements Hookable
{
    private const NONCE_ACTION = 'scm_save_marker_details';
    private const NONCE_NAME = 'scm_marker_details_nonce';

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('edit_form_after_title', [$this, 'renderMetaBox']);
        \add_action('save_post', [$this, 'saveMetaBox']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    private function isMarkerPostType(string $postType): bool
    {
        return $this->settings->getMarkerType($postType) !== null;
    }

    public function enqueueAssets(string $hook): void
    {
        if (! \in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $postType = \get_current_screen()->post_type ?? '';

        if (! $this->isMarkerPostType($postType)) {
            return;
        }

        MarkerMeta::enqueueMapAssets();
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        if (! $this->isMarkerPostType($post->post_type)) {
            return;
        }

        $meta = MarkerMeta::read($post->ID);

        \wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <div class="postbox scm-marker-details">
            <h2 class="hndle"><span>Location</span></h2>
            <div class="inside">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Map location</th>
                        <td>
                            <div id="scm-map" class="scm-map" data-lat="<?php echo \esc_attr($meta['lat']); ?>" data-lng="<?php echo \esc_attr($meta['lng']); ?>"></div>
                            <p class="description">Search for the address above the map, or click the map to drop/move the pin.</p>
                            <p>
                                <label>Lat <input type="text" inputmode="decimal" id="scm_lat" name="scm[lat]" value="<?php echo \esc_attr($meta['lat']); ?>" class="small-text"></label>
                                <label>Lng <input type="text" inputmode="decimal" id="scm_lng" name="scm[lng]" value="<?php echo \esc_attr($meta['lng']); ?>" class="small-text"></label>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="description">The title, featured image and content above are what show in this marker's map popup and on its own page.</p>
            </div>
        </div>
        <?php
    }

    public function saveMetaBox(int $postId): void
    {
        if (
            ! isset($_POST[self::NONCE_NAME])
            || ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
        ) {
            return;
        }

        if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $post = \get_post($postId);

        if (! $post || ! $this->isMarkerPostType($post->post_type) || ! \current_user_can('edit_post', $postId)) {
            return;
        }

        $data = \wp_unslash($_POST['scm'] ?? []);

        MarkerMeta::save($postId, $data);
    }
}
