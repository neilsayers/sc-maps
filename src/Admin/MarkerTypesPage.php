<?php

namespace SCMaps\Admin;

use SCMaps\Contracts\Hookable;
use SCMaps\PostTypes\MarkerPostTypes;
use SCMaps\Settings\Settings;
use SCMaps\Setup\Activator;

/**
 * The plugin's one settings screen: lists every configured marker type
 * (rename labels/colour/icon inline, all in one form) and, while under
 * the limit, a form to add another. First activation redirects here
 * since the list starts empty — see maybeRedirectToSetup().
 *
 * Same shape as SC Room Bookings' RoomTypesPage / SC Events Manager's
 * EventTypesPage, plus the colour + icon fields those two don't need.
 */
final class MarkerTypesPage implements Hookable
{
    private const PAGE_SLUG = 'scm-settings';
    private const ADD_ACTION = 'scm_add_marker_type';
    private const RELABEL_ACTION = 'scm_relabel_marker_types';
    private const DELETE_ACTION = 'scm_delete_marker_type';

    /**
     * Post statuses counted as "existing" for the pre-delete warning
     * and actually moved to Trash — excludes 'trash' (already there)
     * and 'auto-draft' (never a real marker an editor created).
     */
    private const DELETABLE_STATUSES = ['publish', 'future', 'draft', 'pending', 'private'];

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
        \add_action('admin_menu', [$this, 'reorderMenu'], 999);
        \add_action('admin_init', [$this, 'maybeRedirectToSetup']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        \add_action('admin_post_'.self::ADD_ACTION, [$this, 'handleAdd']);
        \add_action('admin_post_'.self::RELABEL_ACTION, [$this, 'handleRelabel']);
        \add_action('admin_post_'.self::DELETE_ACTION, [$this, 'handleDelete']);
        \add_action('admin_notices', [$this, 'maybeShowCreatedNotice']);
    }

    public function enqueueAssets(): void
    {
        if (($_GET['page'] ?? '') !== self::PAGE_SLUG) {
            return;
        }

        \wp_enqueue_media();
        \wp_enqueue_style('scm-admin', SCM_URL.'assets/css/admin.css', [], SCM_VERSION);
        \wp_enqueue_script('scm-marker-types-delete', SCM_URL.'assets/js/marker-types-delete.js', [], SCM_VERSION, true);
        \wp_enqueue_script('scm-marker-types-icon-picker', SCM_URL.'assets/js/marker-types-icon-picker.js', ['jquery'], SCM_VERSION, true);
    }

    public function registerMenu(): void
    {
        \add_menu_page(
            'SC Maps',
            'SC Maps',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage'],
            'dashicons-location-alt',
            92
        );

        /*
         * Without an explicit submenu entry whose own slug matches the
         * parent, this settings page would have no menu link pointing
         * to it at all — see reorderMenu() for why, and for why this
         * alone isn't quite enough either.
         */
        \add_submenu_page(
            self::PAGE_SLUG,
            'SC Maps',
            'Marker Types',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * The top-level menu link's own href is whichever submenu item
     * was registered *first*, not whichever one shares its slug. Run
     * last (priority 999) and move this page's entry to the front so
     * the top-level link — and the visible submenu order — both point
     * here first. See SC Events Manager's identical EventTypesPage
     * ::reorderMenu() for the fuller version of this story.
     */
    public function reorderMenu(): void
    {
        global $submenu;

        if (empty($submenu[self::PAGE_SLUG])) {
            return;
        }

        $items = $submenu[self::PAGE_SLUG];

        foreach ($items as $index => $item) {
            if ($item[2] === self::PAGE_SLUG) {
                unset($items[$index]);
                \array_unshift($items, $item);
                $submenu[self::PAGE_SLUG] = \array_values($items);

                return;
            }
        }
    }

    /**
     * Sends a freshly-activated admin here once, the same way the
     * sibling plugins' own setup wizards do. Skips bulk/network
     * activation so it doesn't hijack that summary screen.
     */
    public function maybeRedirectToSetup(): void
    {
        if (! \get_transient(Activator::REDIRECT_TRANSIENT)) {
            return;
        }

        \delete_transient(Activator::REDIRECT_TRANSIENT);

        if (
            $this->settings->hasMarkerTypes()
            || \wp_doing_ajax()
            || isset($_GET['activate-multi'])
            || ! \current_user_can('manage_options')
        ) {
            return;
        }

        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG));
        exit;
    }

    public function renderPage(): void
    {
        if (! \current_user_can('manage_options')) {
            return;
        }

        $markerTypes = $this->settings->allMarkerTypes();
        ?>
        <div class="wrap">
            <h1>SC Maps</h1>

            <?php \settings_errors('scm_settings'); ?>

            <?php if ($markerTypes !== []) : ?>
                <h2>Marker types</h2>
                <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
                    <?php \wp_nonce_field(self::RELABEL_ACTION); ?>
                    <input type="hidden" name="action" value="<?php echo \esc_attr(self::RELABEL_ACTION); ?>">

                    <table class="widefat striped" style="max-width: 1300px;">
                        <thead>
                            <tr>
                                <th>Singular</th>
                                <th>Plural</th>
                                <th>URL slug</th>
                                <th>Colour</th>
                                <th>Icon</th>
                                <th></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($markerTypes as $markerType) : ?>
                                <tr>
                                    <td>
                                        <input
                                            type="text"
                                            name="labels[<?php echo \esc_attr($markerType['post_type']); ?>][singular]"
                                            value="<?php echo \esc_attr($markerType['label_singular']); ?>"
                                            class="regular-text"
                                            required
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="labels[<?php echo \esc_attr($markerType['post_type']); ?>][plural]"
                                            value="<?php echo \esc_attr($markerType['label_plural']); ?>"
                                            class="regular-text"
                                            required
                                        >
                                    </td>
                                    <td><code>/<?php echo \esc_html($markerType['slug']); ?>/</code></td>
                                    <td>
                                        <input
                                            type="color"
                                            name="labels[<?php echo \esc_attr($markerType['post_type']); ?>][colour]"
                                            value="<?php echo \esc_attr($markerType['colour']); ?>"
                                        >
                                    </td>
                                    <td>
                                        <?php $this->renderIconPicker($markerType); ?>
                                    </td>
                                    <td>
                                        <a
                                            href="<?php echo \esc_url(\admin_url('edit.php?post_type='.$markerType['post_type'])); ?>"
                                            class="button"
                                        >View <?php echo \esc_html($markerType['label_plural']); ?></a>
                                    </td>
                                    <td><?php $this->renderDeleteButton($markerType); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="description">
                        Renaming only changes the labels shown in wp-admin — the URL slug stays fixed so existing
                        links never break.
                    </p>

                    <?php \submit_button('Save changes'); ?>
                </form>

                <?php foreach ($markerTypes as $markerType) : ?>
                    <?php $this->renderDeleteDialog($markerType); ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <h2><?php echo $markerTypes === [] ? 'Create your first marker type' : 'Add another marker type'; ?></h2>

            <?php if ($markerTypes === []) : ?>
                <p>
                    Before SC Maps can plot anything, tell it what kind of place you're marking — for example
                    <strong>Shop</strong>, <strong>Point of interest</strong>, or <strong>Theme park</strong>. Each
                    marker type gets its own colour and icon on the map.
                </p>
            <?php endif; ?>

            <?php if ($this->settings->canAddMarkerType()) : ?>
                <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
                    <?php \wp_nonce_field(self::ADD_ACTION); ?>
                    <input type="hidden" name="action" value="<?php echo \esc_attr(self::ADD_ACTION); ?>">

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="scm_label_singular">Singular name</label></th>
                            <td>
                                <input name="label_singular" type="text" id="scm_label_singular" class="regular-text" placeholder="e.g. Shop" required>
                                <p class="description">Used for "Add New Shop", "Edit Shop", and so on.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="scm_label_plural">Plural name</label></th>
                            <td>
                                <input name="label_plural" type="text" id="scm_label_plural" class="regular-text" placeholder="e.g. Shops" required>
                                <p class="description">Used for the admin menu and archive page, and to build its URL.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="scm_colour">Colour</label></th>
                            <td>
                                <input name="colour" type="color" id="scm_colour" value="<?php echo \esc_attr($this->settings->nextDefaultColour()); ?>">
                                <p class="description">This type's markers are tinted this colour on the map.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Icon</th>
                            <td>
                                <?php $this->renderIconPicker(null); ?>
                                <p class="description">Optional — falls back to a plain pin if none is chosen.</p>
                            </td>
                        </tr>
                    </table>

                    <?php \submit_button('Create marker type'); ?>
                </form>
            <?php else : ?>
                <p>
                    <?php echo \esc_html(\sprintf(
                        'You\'ve reached the current limit of %d marker types.',
                        $this->settings->maxMarkerTypes()
                    )); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * A WP media-library picker button + hidden URL field + live
     * preview, shared between the "add" form (no existing type — the
     * field name is unprefixed) and each row of the relabel table
     * (prefixed with the type's post_type so handleRelabel() can tell
     * rows apart). See assets/js/marker-types-icon-picker.js.
     *
     * @param array{post_type: string, icon: string}|null $markerType
     */
    private function renderIconPicker(?array $markerType): void
    {
        $fieldName = $markerType === null ? 'icon' : 'labels['.$markerType['post_type'].'][icon]';
        $icon = $markerType['icon'] ?? '';
        $fieldId = $markerType === null ? 'scm_icon' : 'scm_icon_'.$markerType['post_type'];
        ?>
        <div class="scm-icon-picker">
            <input type="hidden" name="<?php echo \esc_attr($fieldName); ?>" id="<?php echo \esc_attr($fieldId); ?>" value="<?php echo \esc_url($icon); ?>">
            <div class="scm-icon-preview" <?php echo $icon === '' ? 'style="display:none;"' : ''; ?>>
                <img src="<?php echo \esc_url($icon); ?>" alt="">
                <button type="button" class="button-link scm-icon-remove">Remove</button>
            </div>
            <button type="button" class="button scm-icon-choose" data-target="<?php echo \esc_attr($fieldId); ?>">Choose icon&hellip;</button>
        </div>
        <?php
    }

    /**
     * @param array{post_type: string, slug: string, label_singular: string, label_plural: string} $markerType
     */
    private function renderDeleteButton(array $markerType): void
    {
        ?>
        <button
            type="button"
            class="button button-link-delete"
            onclick="document.getElementById('scm-delete-dialog-<?php echo \esc_attr($markerType['post_type']); ?>').showModal()"
        >Delete&hellip;</button>
        <?php
    }

    /**
     * The confirmation dialog for one marker type's delete button —
     * typing the plural label (GitHub delete-repo style) is required
     * to enable the submit button; see assets/js/marker-types-delete.js.
     * check_admin_referer() in handleDelete() is the real guard
     * against a forged request — the typed-label check only guards
     * against a misclick.
     *
     * @param array{post_type: string, slug: string, label_singular: string, label_plural: string} $markerType
     */
    private function renderDeleteDialog(array $markerType): void
    {
        $postType = $markerType['post_type'];
        $markerCount = $this->markerCountForType($postType);
        $taxonomyCount = \count($this->settings->taxonomiesForMarkerType($postType));
        $dialogId = 'scm-delete-dialog-'.$postType;
        $inputId = 'scm-delete-confirm-'.$postType;
        ?>
        <dialog id="<?php echo \esc_attr($dialogId); ?>" class="scm-delete-dialog">
            <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
                <?php \wp_nonce_field(self::DELETE_ACTION); ?>
                <input type="hidden" name="action" value="<?php echo \esc_attr(self::DELETE_ACTION); ?>">
                <input type="hidden" name="post_type" value="<?php echo \esc_attr($postType); ?>">

                <h2>Delete <?php echo \esc_html($markerType['label_plural']); ?>?</h2>

                <p>
                    This will delete
                    <strong><?php echo \esc_html(\sprintf('%d %s', $markerCount, $markerType['label_plural'])); ?></strong>.
                    They'll be moved to Trash, where they can be restored (or permanently deleted) for 30 days.
                </p>

                <?php if ($taxonomyCount > 0) : ?>
                    <p>
                        It will also remove
                        <?php echo \esc_html(\sprintf(
                            $taxonomyCount === 1 ? '%d custom taxonomy' : '%d custom taxonomies',
                            $taxonomyCount
                        )); ?>
                        attached to this marker type — see <a href="<?php echo \esc_url(\admin_url('admin.php?page=scm-taxonomies')); ?>">Taxonomies</a>.
                    </p>
                <?php endif; ?>

                <p>
                    <label for="<?php echo \esc_attr($inputId); ?>">
                        Type <strong><?php echo \esc_html($markerType['label_plural']); ?></strong> to confirm:
                    </label>
                    <br>
                    <input
                        type="text"
                        id="<?php echo \esc_attr($inputId); ?>"
                        name="confirm_label"
                        class="regular-text scm-delete-confirm-input"
                        data-confirm="<?php echo \esc_attr($markerType['label_plural']); ?>"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                    >
                </p>

                <p>
                    <button type="submit" class="button button-primary scm-delete-confirm-submit" disabled>
                        Delete <?php echo \esc_html($markerType['label_plural']); ?>
                    </button>
                    <button type="button" class="button" onclick="document.getElementById('<?php echo \esc_attr($dialogId); ?>').close()">Cancel</button>
                </p>
            </form>
        </dialog>
        <?php
    }

    /**
     * Markers that actually exist for this type right now — the same
     * statuses get_posts(['post_status' => 'any']) returns in
     * handleDelete(), so this count matches what's about to be
     * trashed.
     */
    private function markerCountForType(string $postType): int
    {
        $counts = \wp_count_posts($postType);
        $total = 0;

        foreach (self::DELETABLE_STATUSES as $status) {
            $total += (int) ($counts->$status ?? 0);
        }

        return $total;
    }

    public function handleAdd(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\esc_html__('You are not allowed to do this.', 'sc-maps'));
        }

        \check_admin_referer(self::ADD_ACTION);

        $singular = \sanitize_text_field(\wp_unslash($_POST['label_singular'] ?? ''));
        $plural = \sanitize_text_field(\wp_unslash($_POST['label_plural'] ?? ''));
        $colour = \sanitize_text_field(\wp_unslash($_POST['colour'] ?? ''));
        $icon = \esc_url_raw(\wp_unslash($_POST['icon'] ?? ''));

        $error = $this->settings->validateNewMarkerType($singular, $plural);

        if ($error !== null) {
            \add_settings_error('scm_settings', 'scm_invalid', $error);
            \set_transient('settings_errors', \get_settings_errors(), 30);
            \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
            exit;
        }

        $postType = $this->settings->addMarkerType($singular, $plural, $colour, $icon);

        // Deferred to the next request's init — see MarkerPostTypes::FLUSH_TRANSIENT.
        \set_transient(MarkerPostTypes::FLUSH_TRANSIENT, true, 30);

        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&scm-created='.$postType));
        exit;
    }

    public function handleRelabel(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\esc_html__('You are not allowed to do this.', 'sc-maps'));
        }

        \check_admin_referer(self::RELABEL_ACTION);

        $labels = \wp_unslash($_POST['labels'] ?? []);
        $hadError = false;

        foreach ($labels as $postType => $row) {
            $singular = \sanitize_text_field($row['singular'] ?? '');
            $plural = \sanitize_text_field($row['plural'] ?? '');
            $colour = \sanitize_text_field($row['colour'] ?? '');
            $icon = \esc_url_raw($row['icon'] ?? '');

            if ($singular === '' || $plural === '') {
                $hadError = true;
                continue;
            }

            $this->settings->relabelMarkerType(\sanitize_key($postType), $singular, $plural, $colour, $icon);
        }

        if ($hadError) {
            \add_settings_error('scm_settings', 'scm_missing_labels', \__('Every marker type needs both a singular and plural name — any left blank were skipped.', 'sc-maps'));
        } else {
            \add_settings_error('scm_settings', 'scm_saved', \__('Settings saved.', 'sc-maps'), 'success');
        }

        \set_transient('settings_errors', \get_settings_errors(), 30);
        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
        exit;
    }

    /**
     * Trashes the type's markers, deletes its taxonomies, then removes
     * the type itself — in that order, since both of the first two
     * steps need Settings::getMarkerType()/taxonomiesForMarkerType()
     * to still find it. The rewrite flush is deferred to the next
     * request for the same reason as handleAdd() — see
     * MarkerPostTypes::FLUSH_TRANSIENT.
     */
    public function handleDelete(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\esc_html__('You are not allowed to do this.', 'sc-maps'));
        }

        \check_admin_referer(self::DELETE_ACTION);

        $postType = \sanitize_key(\wp_unslash($_POST['post_type'] ?? ''));
        $confirmLabel = \sanitize_text_field(\wp_unslash($_POST['confirm_label'] ?? ''));

        $markerType = $this->settings->getMarkerType($postType);

        if ($markerType === null) {
            \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG));
            exit;
        }

        // Belt-and-braces alongside the JS-disabled submit button —
        // that only stops an accidental click, not a resubmitted or
        // hand-crafted request.
        if (\trim($confirmLabel) !== $markerType['label_plural']) {
            \add_settings_error(
                'scm_settings',
                'scm_delete_mismatch',
                \sprintf(
                    'Typed confirmation didn\'t match "%s" — nothing was deleted.',
                    $markerType['label_plural']
                )
            );
            \set_transient('settings_errors', \get_settings_errors(), 30);
            \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
            exit;
        }

        $postIds = \get_posts([
            'post_type' => $postType,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        foreach ($postIds as $postId) {
            \wp_trash_post($postId);
        }

        foreach ($this->settings->taxonomiesForMarkerType($postType) as $taxonomy) {
            $this->settings->deleteTaxonomy($taxonomy['taxonomy']);
        }

        $this->settings->deleteMarkerType($postType);

        // Deferred to the next request's init — see MarkerPostTypes::FLUSH_TRANSIENT.
        \set_transient(MarkerPostTypes::FLUSH_TRANSIENT, true, 30);

        \add_settings_error(
            'scm_settings',
            'scm_deleted',
            \sprintf(
                '"%s" deleted — %d moved to Trash.',
                $markerType['label_plural'],
                \count($postIds)
            ),
            'success'
        );
        \set_transient('settings_errors', \get_settings_errors(), 30);

        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
        exit;
    }

    public function maybeShowCreatedNotice(): void
    {
        $postType = \sanitize_key(\wp_unslash($_GET['scm-created'] ?? ''));

        if ($postType === '') {
            return;
        }

        $markerType = $this->settings->getMarkerType($postType);

        if ($markerType === null) {
            return;
        }

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            \esc_html(\sprintf('"%s" is ready — start adding your first one below.', $markerType['label_plural']))
        );
    }
}
