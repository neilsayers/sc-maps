<?php

namespace SCMaps\Admin;

use SCMaps\Contracts\Hookable;
use SCMaps\PostTypes\MarkerPostTypes;
use SCMaps\Settings\Settings;

/**
 * Submenu under "Maps": create and delete custom taxonomies for each
 * marker type, capped per type — see
 * Settings::maxTaxonomiesPerMarkerType().
 */
final class TaxonomiesPage implements Hookable
{
    private const PAGE_SLUG = 'scm-taxonomies';
    private const ADD_ACTION = 'scm_add_taxonomy';
    private const DELETE_ACTION = 'scm_delete_taxonomy';

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
        \add_action('admin_post_'.self::ADD_ACTION, [$this, 'handleAdd']);
        \add_action('admin_post_'.self::DELETE_ACTION, [$this, 'handleDelete']);
    }

    public function registerMenu(): void
    {
        \add_submenu_page(
            'scm-settings',
            'Taxonomies',
            'Taxonomies',
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

        $markerTypes = $this->settings->allMarkerTypes();
        $taxonomies = $this->settings->allTaxonomies();
        $availableMarkerTypes = \array_filter(
            $markerTypes,
            fn (array $markerType): bool => $this->settings->canAddTaxonomyForMarkerType($markerType['post_type'])
        );
        ?>
        <div class="wrap">
            <h1>Taxonomies</h1>

            <?php \settings_errors('scm_taxonomies'); ?>

            <?php if ($markerTypes === []) : ?>
                <p>
                    <a href="<?php echo \esc_url(\admin_url('admin.php?page=scm-settings')); ?>">Create a marker type</a>
                    first — taxonomies attach to one.
                </p>
                <?php return; ?>
            <?php endif; ?>

            <?php if ($taxonomies !== []) : ?>
                <table class="widefat striped" style="max-width: 900px;">
                    <thead>
                        <tr>
                            <th>Singular</th>
                            <th>Plural</th>
                            <th>Marker type</th>
                            <th>Type</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($taxonomies as $taxonomy) : ?>
                            <?php $markerType = $this->settings->getMarkerType($taxonomy['post_type']); ?>
                            <tr>
                                <td><?php echo \esc_html($taxonomy['label_singular']); ?></td>
                                <td><?php echo \esc_html($taxonomy['label_plural']); ?></td>
                                <td><?php echo \esc_html($markerType['label_plural'] ?? $taxonomy['post_type']); ?></td>
                                <td><?php echo $taxonomy['hierarchical'] ? 'Hierarchical (categories)' : 'Non-hierarchical (tags)'; ?></td>
                                <td>
                                    <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete this taxonomy? Its terms will no longer classify any posts.');">
                                        <?php \wp_nonce_field(self::DELETE_ACTION); ?>
                                        <input type="hidden" name="action" value="<?php echo \esc_attr(self::DELETE_ACTION); ?>">
                                        <input type="hidden" name="taxonomy" value="<?php echo \esc_attr($taxonomy['taxonomy']); ?>">
                                        <button type="submit" class="button button-link-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>Add a taxonomy</h2>

            <?php if ($availableMarkerTypes === []) : ?>
                <p>Every marker type already has the maximum of <?php echo \esc_html((string) $this->settings->maxTaxonomiesPerMarkerType()); ?> custom taxonomies.</p>
            <?php else : ?>
                <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
                    <?php \wp_nonce_field(self::ADD_ACTION); ?>
                    <input type="hidden" name="action" value="<?php echo \esc_attr(self::ADD_ACTION); ?>">

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="scm_tax_post_type">Marker type</label></th>
                            <td>
                                <select name="post_type" id="scm_tax_post_type">
                                    <?php foreach ($availableMarkerTypes as $markerType) : ?>
                                        <option value="<?php echo \esc_attr($markerType['post_type']); ?>"><?php echo \esc_html($markerType['label_plural']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="scm_tax_label_singular">Singular name</label></th>
                            <td>
                                <input name="label_singular" type="text" id="scm_tax_label_singular" class="regular-text" placeholder="e.g. Category" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="scm_tax_label_plural">Plural name</label></th>
                            <td>
                                <input name="label_plural" type="text" id="scm_tax_label_plural" class="regular-text" placeholder="e.g. Categories" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Type</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="hierarchical" value="1">
                                    Hierarchical (like categories — supports parent/child terms)
                                </label>
                                <p class="description">Leave unchecked for a flat, tag-style taxonomy.</p>
                            </td>
                        </tr>
                    </table>

                    <?php \submit_button('Create taxonomy'); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handleAdd(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\esc_html__('You are not allowed to do this.', 'sc-maps'));
        }

        \check_admin_referer(self::ADD_ACTION);

        $postType = \sanitize_key(\wp_unslash($_POST['post_type'] ?? ''));
        $singular = \sanitize_text_field(\wp_unslash($_POST['label_singular'] ?? ''));
        $plural = \sanitize_text_field(\wp_unslash($_POST['label_plural'] ?? ''));
        $hierarchical = ! empty($_POST['hierarchical']);

        $error = $this->settings->validateNewTaxonomy($postType, $singular, $plural);

        if ($error !== null) {
            \add_settings_error('scm_taxonomies', 'scm_invalid', $error);
            \set_transient('settings_errors', \get_settings_errors(), 30);
            \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
            exit;
        }

        $this->settings->addTaxonomy($postType, $singular, $plural, $hierarchical);

        // Deferred to the next request's init — see MarkerPostTypes::FLUSH_TRANSIENT.
        \set_transient(MarkerPostTypes::FLUSH_TRANSIENT, true, 30);

        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG));
        exit;
    }

    public function handleDelete(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\esc_html__('You are not allowed to do this.', 'sc-maps'));
        }

        \check_admin_referer(self::DELETE_ACTION);

        $taxonomy = \sanitize_key(\wp_unslash($_POST['taxonomy'] ?? ''));

        $this->settings->deleteTaxonomy($taxonomy);

        \set_transient(MarkerPostTypes::FLUSH_TRANSIENT, true, 30);

        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG));
        exit;
    }
}
