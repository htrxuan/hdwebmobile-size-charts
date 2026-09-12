<?php

namespace htrxuan\hdsc;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The only place size charts are ever written, and the resolver that picks which chart (if
 * any) applies to a given product.
 *
 * CVE-2025-23991 (CWE-862 Missing Authorization) in "Product Size Charts for WooCommerce"
 * (<= 2.4.5): the chart-configuration write path had no capability check at all, letting any
 * authenticated user -- or, depending on how the vulnerable action was wired, an
 * unauthenticated one -- change the store's size charts.
 *
 * This class closes that by construction: save_charts() is called from exactly one place
 * (HDSC_Admin's settings-save handler), which checks current_user_can('manage_woocommerce')
 * AND a verified nonce before a single field is read. Every value is still independently
 * validated here regardless of caller -- a plausible slug shape, real category term ids
 * (checked with term_exists()), and plain sanitised text for every cell. There is no image
 * upload anywhere in this plugin: a chart is only ever a table of admin-typed text, so there
 * is nothing file-shaped for a write-path bug to touch.
 */
class HDSC_Repository
{
    const OPTION_KEY = 'hdsc_charts';
    const META_PRODUCT_OVERRIDE = '_hdsc_chart_id';

    /**
     * @return array<string, array{name:string, columns:string[], rows:string[][], categories:int[]}>
     */
    public static function get_charts()
    {
        $charts = get_option(self::OPTION_KEY, array());
        return is_array($charts) ? $charts : array();
    }

    public static function get_chart($chart_id)
    {
        $charts = self::get_charts();
        return isset($charts[$chart_id]) ? $charts[$chart_id] : null;
    }

    /**
     * The only write path. $rows is the raw grouped-array POST payload; every field is
     * validated/normalised here regardless of the caller's own authorization check.
     *
     * @param array $rows Each: ['name'=>?, 'columns'=>?, 'rows'=>?, 'categories'=>?]
     *                     'columns' and each row of 'rows' are newline- or comma-separated
     *                     free text; 'categories' is an array of term ids.
     * @return array The stored, cleaned chart list.
     */
    public static function save_charts(array $rows)
    {
        $charts = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = isset($row['name']) ? sanitize_text_field($row['name']) : '';
            if ('' === $name) {
                continue; // A chart with no name is skipped, never guessed at.
            }

            $columns = self::split_cells($row['columns'] ?? '');
            if (empty($columns)) {
                continue; // A chart with no columns has nothing to render -- skip it.
            }

            // 'rows' is one free-text block: one row per line, comma-separated cells within
            // a line -- exactly what the admin form's single <textarea> submits. Split on
            // newlines FIRST (one row per line), then split each line into cells.
            $data_rows = array();
            $raw_rows  = isset($row['rows']) ? (string) $row['rows'] : '';
            foreach (preg_split('/[\r\n]+/', $raw_rows, -1, PREG_SPLIT_NO_EMPTY) as $line) {
                $cells = self::split_cells($line);
                if (empty($cells)) {
                    continue;
                }
                // Pad/truncate to the column count so every row renders a well-formed table.
                $cells = array_pad(array_slice($cells, 0, count($columns)), count($columns), '');
                $data_rows[] = $cells;
            }
            if (empty($data_rows)) {
                continue; // No data rows -- nothing to show.
            }

            $categories = array();
            if (isset($row['categories']) && is_array($row['categories'])) {
                foreach ($row['categories'] as $term_id) {
                    $term_id = absint($term_id);
                    if ($term_id > 0 && term_exists($term_id, 'product_cat')) {
                        $categories[] = $term_id;
                    }
                }
            }

            $chart_id = sanitize_title($name);
            if ('' === $chart_id || isset($charts[$chart_id])) {
                $chart_id = $chart_id . '-' . wp_generate_password(6, false, false);
            }

            $charts[$chart_id] = array(
                'name'       => $name,
                'columns'    => $columns,
                'rows'       => $data_rows,
                'categories' => array_values(array_unique($categories)),
            );
        }

        update_option(self::OPTION_KEY, $charts);
        return $charts;
    }

    /**
     * Which chart (if any) applies to a given product: an explicit per-product override
     * always wins; otherwise the first chart whose assigned categories intersect the
     * product's own categories.
     *
     * @param int $product_id
     * @return array|null
     */
    public static function get_chart_for_product($product_id)
    {
        $override = get_post_meta($product_id, self::META_PRODUCT_OVERRIDE, true);
        if ($override) {
            $chart = self::get_chart($override);
            if ($chart) {
                return $chart;
            }
        }

        $product_cats = wc_get_product_cat_ids($product_id);
        if (empty($product_cats)) {
            return null;
        }

        foreach (self::get_charts() as $chart) {
            if (!empty($chart['categories']) && array_intersect($chart['categories'], $product_cats)) {
                return $chart;
            }
        }
        return null;
    }

    /**
     * A free-form "one per line, or comma-separated" cell splitter. Every resulting cell is
     * already sanitize_text_field()'d, so nothing HTML-shaped survives into storage.
     *
     * @return string[]
     */
    private static function split_cells($raw)
    {
        if (!is_string($raw)) {
            return array();
        }
        $parts = preg_split('/[\r\n,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $cells = array();
        foreach ($parts as $part) {
            $clean = sanitize_text_field(trim($part));
            if ('' !== $clean) {
                $cells[] = $clean;
            }
        }
        return $cells;
    }
}
