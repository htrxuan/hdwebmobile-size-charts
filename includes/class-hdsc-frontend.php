<?php

namespace htrxuan\hdsc;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the size chart on a product page, if one applies. Uses a plain <details>/<summary>
 * disclosure -- no JavaScript, no modal library, nothing to enqueue. Every cell was already
 * sanitize_text_field()'d on save (see HDSC_Repository::split_cells()); it is still escaped
 * again here at the point of output.
 */
final class HDSC_Frontend
{

    private static $instance = null;
    private static $rendered = false;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'assets'));
        add_action('woocommerce_single_product_summary', array($this, 'render'), 25);
        add_filter('render_block', array($this, 'block_fallback'), 10, 2);
    }

    public function assets()
    {
        if (is_product()) {
            wp_enqueue_style('hdsc', HDSC_PLUGIN_URL . 'assets/css/hdsc.css', array(), HDSC_VERSION);
        }
    }

    public function render()
    {
        if (self::$rendered) {
            return;
        }
        global $product;
        if (!$product instanceof \WC_Product) {
            return;
        }
        $html = $this->get_html($product->get_id());
        if ('' === $html) {
            return;
        }
        self::$rendered = true;
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_html() escapes every dynamic value at output.
    }

    public function block_fallback($block_content, $block)
    {
        if (self::$rendered) {
            return $block_content;
        }
        $name = is_array($block) && isset($block['blockName']) ? $block['blockName'] : '';
        if ('woocommerce/add-to-cart-form' !== $name || !is_singular('product')) {
            return $block_content;
        }
        $html = $this->get_html(get_queried_object_id());
        if ('' === $html) {
            return $block_content;
        }
        self::$rendered = true;
        return $block_content . $html;
    }

    private function get_html($product_id)
    {
        $chart = HDSC_Repository::get_chart_for_product($product_id);
        if (!$chart) {
            return '';
        }

        $html  = '<details class="hdsc-chart"><summary>' . esc_html__('Size chart', 'hdwebmobile-size-charts') . '</summary>';
        $html .= '<table class="hdsc-chart__table"><caption>' . esc_html($chart['name']) . '</caption><thead><tr>';
        foreach ($chart['columns'] as $col) {
            $html .= '<th>' . esc_html($col) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($chart['rows'] as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . esc_html($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></details>';

        return $html;
    }
}
