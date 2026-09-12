<?php

namespace htrxuan\hdsc;

if (!defined('ABSPATH')) {
    exit;
}

class HDSC_Admin
{
    const NONCE_ACTION = 'hdsc_save_charts';

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        require_once HDSC_PLUGIN_DIR . 'includes/class-hdsc-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
        add_action('admin_post_hdsc_save_charts', array($this, 'save_charts'));

        // Per-product override: which chart shows on this one product, if any.
        add_action('woocommerce_product_options_general_product_data', array($this, 'render_product_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_field'));
    }

    public function register_hub_tabs($tabs)
    {
        $tabs['size-charts'] = array(
            'label'  => __('Size Charts', 'hdwebmobile-size-charts'),
            'order'  => 50,
            'render' => array($this, 'render_page'),
        );
        return $tabs;
    }

    /**
     * The ONLY caller of HDSC_Repository::save_charts(). Both the capability check AND the
     * nonce check run before a single byte of $_POST is read -- the exact gate the vulnerable
     * competing plugin was missing entirely.
     */
    public function save_charts()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-size-charts'));
        }
        if (!isset($_POST['hdsc_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hdsc_nonce'])), self::NONCE_ACTION)) {
            wp_die(esc_html__('Security check failed. Please try again.', 'hdwebmobile-size-charts'));
        }

        $submitted = isset($_POST['hdsc_chart']) && is_array($_POST['hdsc_chart'])
            ? (array) wp_unslash($_POST['hdsc_chart']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is individually sanitised/validated inside HDSC_Repository::save_charts(), which never trusts caller input regardless of this authorization gate.
            : array();

        $rows = array();
        foreach ($submitted as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = array(
                'name'       => $row['name'] ?? '',
                'columns'    => $row['columns'] ?? '',
                'rows'       => $row['rows'] ?? '',
                'categories' => $row['categories'] ?? array(),
            );
        }

        HDSC_Repository::save_charts($rows);

        wp_safe_redirect(admin_url('admin.php?page=hdwebmobile&tab=size-charts&updated=1'));
        exit;
    }

    /* ---------- product data panel: which chart applies to this one product ---------- */

    public function render_product_field()
    {
        global $post;
        $charts  = HDSC_Repository::get_charts();
        $current = get_post_meta($post->ID, HDSC_Repository::META_PRODUCT_OVERRIDE, true);
        echo '<div class="options_group">';
        echo '<p class="form-field"><label for="hdsc_chart_id">' . esc_html__('Size chart', 'hdwebmobile-size-charts') . '</label>';
        echo '<select id="hdsc_chart_id" name="hdsc_chart_id">';
        echo '<option value="">' . esc_html__('Use category default', 'hdwebmobile-size-charts') . '</option>';
        foreach ($charts as $chart_id => $chart) {
            printf('<option value="%s"%s>%s</option>', esc_attr($chart_id), selected($current, $chart_id, false), esc_html($chart['name']));
        }
        echo '</select></p></div>';
    }

    public function save_product_field($post_id)
    {
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data')) {
            return;
        }
        $chart_id = isset($_POST['hdsc_chart_id']) ? sanitize_text_field(wp_unslash($_POST['hdsc_chart_id'])) : '';
        if ('' !== $chart_id && !HDSC_Repository::get_chart($chart_id)) {
            $chart_id = ''; // Not a real chart -- don't store a dangling reference.
        }
        if ('' === $chart_id) {
            delete_post_meta($post_id, HDSC_Repository::META_PRODUCT_OVERRIDE);
        } else {
            update_post_meta($post_id, HDSC_Repository::META_PRODUCT_OVERRIDE, $chart_id);
        }
    }

    /* ---------- hub tab ---------- */

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-size-charts'));
        }

        $charts = HDSC_Repository::get_charts();
        $terms  = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        $terms  = is_wp_error($terms) ? array() : $terms;
        ?>
        <p><?php esc_html_e('Build size charts and assign them to product categories (or override on a single product\'s own data panel). Every chart is plain text -- there is no image upload anywhere in this plugin.', 'hdwebmobile-size-charts'); ?></p>

        <?php if (!empty($_GET['updated'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success flag. ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Size charts saved.', 'hdwebmobile-size-charts'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="hdsc_save_charts" />
            <?php wp_nonce_field(self::NONCE_ACTION, 'hdsc_nonce'); ?>

            <?php $rows = array_values($charts); $rows[] = array('name' => '', 'columns' => array(), 'rows' => array(), 'categories' => array()); ?>
            <?php foreach ($rows as $i => $chart) : ?>
                <table class="widefat striped" style="max-width:820px;margin-bottom:1.5em;">
                    <tbody>
                        <tr>
                            <td style="width:10em;"><label><?php esc_html_e('Chart name', 'hdwebmobile-size-charts'); ?></label></td>
                            <td><input type="text" name="hdsc_chart[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr($chart['name']); ?>" placeholder="<?php esc_attr_e('e.g. Women\'s Tops', 'hdwebmobile-size-charts'); ?>" style="width:100%;" /></td>
                        </tr>
                        <tr>
                            <td><label><?php esc_html_e('Columns', 'hdwebmobile-size-charts'); ?></label></td>
                            <td>
                                <input type="text" name="hdsc_chart[<?php echo (int) $i; ?>][columns]" value="<?php echo esc_attr(implode(', ', $chart['columns'])); ?>" placeholder="<?php esc_attr_e('Size, Bust, Waist, Hips', 'hdwebmobile-size-charts'); ?>" style="width:100%;" />
                                <p class="description"><?php esc_html_e('Comma-separated column headers.', 'hdwebmobile-size-charts'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <td><label><?php esc_html_e('Rows', 'hdwebmobile-size-charts'); ?></label></td>
                            <td>
                                <?php
                                $row_lines = array();
                                foreach ($chart['rows'] as $r) {
                                    $row_lines[] = implode(', ', $r);
                                }
                                ?>
                                <textarea name="hdsc_chart[<?php echo (int) $i; ?>][rows]" rows="4" style="width:100%;" placeholder="<?php esc_attr_e("S, 32-34, 24-26, 35-37\nM, 35-37, 27-29, 38-40", 'hdwebmobile-size-charts'); ?>"><?php echo esc_textarea(implode("\n", $row_lines)); ?></textarea>
                                <p class="description"><?php esc_html_e('One row per line, comma-separated cells, matching the column order above.', 'hdwebmobile-size-charts'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <td><label><?php esc_html_e('Categories', 'hdwebmobile-size-charts'); ?></label></td>
                            <td>
                                <?php foreach ($terms as $term) : ?>
                                    <label style="margin-right:1em;display:inline-block;">
                                        <input type="checkbox" name="hdsc_chart[<?php echo (int) $i; ?>][categories][]" value="<?php echo (int) $term->term_id; ?>" <?php checked(in_array($term->term_id, $chart['categories'], true)); ?> />
                                        <?php echo esc_html($term->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php endforeach; ?>

            <p class="description"><?php esc_html_e('Leave the last chart\'s name blank to skip it. Assign a chart to one or more categories, or leave categories blank and pick it directly on an individual product\'s data panel.', 'hdwebmobile-size-charts'); ?></p>
            <?php submit_button(__('Save Size Charts', 'hdwebmobile-size-charts')); ?>
        </form>
        <?php
    }
}
