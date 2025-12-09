<?php
if (!defined('ABSPATH')) {
    exit;
}

class Rpg_Skill_Trees_Public {
    private $plugin;

    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    public function init() {
        add_shortcode('rpg_skill_trees', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_ajax_rst_save_build', [$this, 'handle_save_build']);
        add_action('wp_ajax_nopriv_rst_save_build', [$this, 'handle_save_build']);
        add_action('wp_ajax_rst_load_build', [$this, 'handle_load_build']);
        add_action('wp_ajax_nopriv_rst_load_build', [$this, 'handle_load_build']);
    }

    public function register_assets() {
        wp_register_style('rpg-skill-trees', plugins_url('../assets/css/public.css', __FILE__), [], RPG_SKILL_TREES_VERSION);
        wp_register_script('rpg-skill-trees', plugins_url('../assets/js/public.js', __FILE__), ['jquery'], RPG_SKILL_TREES_VERSION, true);
    }

    private function get_trees_with_skills() {
        $trees = get_posts([
            'post_type' => 'rpg_tree',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $skills = get_posts([
            'post_type' => 'rpg_skill',
            'numberposts' => -1,
            'meta_key' => 'rst_sort_order',
            'orderby' => ['meta_value_num' => 'ASC', 'title' => 'ASC'],
        ]);
        $tree_skills = [];
        foreach ($skills as $skill) {
            $tree_id = intval(get_post_meta($skill->ID, 'rst_tree_id', true));
            $tree_skills[$tree_id][] = $skill;
        }
        $payload = [];
        foreach ($trees as $tree) {
            if (isset($tree_skills[$tree->ID])) {
                usort($tree_skills[$tree->ID], function($a, $b) {
                    $sort_a = intval(get_post_meta($a->ID, 'rst_sort_order', true));
                    $sort_b = intval(get_post_meta($b->ID, 'rst_sort_order', true));
                    if ($sort_a === $sort_b) {
                        return strcmp($a->post_title, $b->post_title);
                    }
                    return $sort_a <=> $sort_b;
                });
            }
            $payload[] = [
                'id' => $tree->ID,
                'name' => $tree->post_title,
                'description' => wp_kses_post($tree->post_content),
                'icon' => esc_url(get_post_meta($tree->ID, 'rst_icon', true)),
                'color' => sanitize_hex_color(get_post_meta($tree->ID, 'rst_color', true)),
                'tier_rules' => (array) get_post_meta($tree->ID, 'rst_tier_requirements', true),
                'skills' => array_map(function($skill) {
                    return $this->format_skill($skill);
                }, $tree_skills[$tree->ID] ?? []),
            ];
        }
        return $payload;
    }

    private function format_skill($skill) {
        $meta = get_post_meta($skill->ID);
        return [
            'id' => $skill->ID,
            'name' => $skill->post_title,
            'description' => wp_kses_post($skill->post_content),
            'icon' => esc_url($meta['rst_icon'][0] ?? ''),
            'tier' => intval($meta['rst_tier'][0] ?? 1),
            'cost' => floatval($meta['rst_cost'][0] ?? 0),
            'tree_id' => intval($meta['rst_tree_id'][0] ?? 0),
            'prerequisites' => (array) maybe_unserialize($meta['rst_prereq_skills'][0] ?? []),
            'min_previous' => floatval($meta['rst_min_previous'][0] ?? 0),
            'sort_order' => intval($meta['rst_sort_order'][0] ?? 0),
        ];
    }

    public function render_shortcode() {
        wp_enqueue_style('rpg-skill-trees');
        wp_enqueue_script('rpg-skill-trees');
        $settings = Rpg_Skill_Trees::get_settings();
        $trees = $this->get_trees_with_skills();
        $data = [
            'trees' => $trees,
            'settings' => $settings,
            'nonce' => wp_create_nonce('rst_public_nonce'),
            'ajax' => admin_url('admin-ajax.php'),
            'login_required' => intval($settings['require_login']),
            'user_logged_in' => is_user_logged_in(),
        ];
        wp_localize_script('rpg-skill-trees', 'RST_DATA', $data);
        ob_start();
        ?>
        <div class="rst-builder" aria-live="polite">
            <div class="rst-builder__header">
                <h2><?php esc_html_e('RPG Skill Trees Builder', 'rpg-skill-trees'); ?></h2>
                <div class="rst-builder__controls">
                    <label><?php esc_html_e('Choose trees (multiclassing supported)', 'rpg-skill-trees'); ?></label>
                    <div class="rst-tree-list">
                        <?php foreach ($trees as $tree): ?>
                            <label class="rst-tree-option" style="border-color: <?php echo esc_attr($tree['color'] ?: '#666'); ?>;">
                                <input type="checkbox" class="rst-tree-toggle" value="<?php echo esc_attr($tree['id']); ?>" />
                                <?php if ($tree['icon']): ?><img src="<?php echo esc_url($tree['icon']); ?>" alt="" class="rst-tree-icon" /><?php endif; ?>
                                <span><?php echo esc_html($tree['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="rst-points" role="status">
                    <strong><?php esc_html_e('Body', 'rpg-skill-trees'); ?>:</strong>
                    <span class="rst-points-summary"></span>
                </div>
                <div class="rst-actions">
                    <button class="button button-primary rst-save-build"><?php esc_html_e('Save build', 'rpg-skill-trees'); ?></button>
                    <button class="button rst-load-build"><?php esc_html_e('Load build', 'rpg-skill-trees'); ?></button>
                    <button class="button rst-reset-build"><?php esc_html_e('Reset', 'rpg-skill-trees'); ?></button>
                    <div class="rst-feedback" aria-live="assertive"></div>
                </div>
            </div>
            <div class="rst-builder__content">
                <?php foreach ($trees as $tree): ?>
                    <div class="rst-tree" data-tree-id="<?php echo esc_attr($tree['id']); ?>" style="--rst-tree-color: <?php echo esc_attr($tree['color'] ?: '#666'); ?>">
                        <div class="rst-tree__header">
                            <?php if ($tree['icon']): ?><img src="<?php echo esc_url($tree['icon']); ?>" alt="" class="rst-tree-icon" /><?php endif; ?>
                            <h3><?php echo esc_html($tree['name']); ?></h3>
                            <p><?php echo wp_kses_post($tree['description']); ?></p>
                        </div>
                        <div class="rst-tree__tiers">
                            <?php for ($tier = 1; $tier <= 4; $tier++): ?>
                                <div class="rst-tier" data-tier="<?php echo $tier; ?>">
                                    <h4><?php printf(__('Úroveň %d', 'rpg-skill-trees'), $tier); ?></h4>
                                    <div class="rst-tier__skills">
                                        <?php foreach ($tree['skills'] as $skill): if (intval($skill['tier']) !== $tier) continue; ?>
                                            <div class="rst-skill" data-skill-id="<?php echo esc_attr($skill['id']); ?>" data-tree-id="<?php echo esc_attr($skill['tree_id']); ?>" data-tier="<?php echo esc_attr($skill['tier']); ?>" data-cost="<?php echo esc_attr($skill['cost']); ?>" data-prereqs="<?php echo esc_attr(json_encode($skill['prerequisites'])); ?>" data-min-prev="<?php echo esc_attr($skill['min_previous']); ?>">
                                                <?php if ($skill['icon']): ?><img src="<?php echo esc_url($skill['icon']); ?>" alt="" class="rst-skill__icon" /><?php endif; ?>
                                                <div class="rst-skill__name"><?php echo esc_html($skill['name']); ?></div>
                                                <div class="rst-skill__tooltip"><?php echo wp_kses_post($skill['description']); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                            <svg class="rst-prereq-lines" aria-hidden="true"></svg>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function validate_build($selected_skills) {
        $trees = [];
        $skills = [];
        foreach (get_posts(['post_type' => 'rpg_skill', 'numberposts' => -1]) as $skill) {
            $data = $this->format_skill($skill);
            $skills[$skill->ID] = $data;
            $trees[$data['tree_id']] = $trees[$data['tree_id']] ?? [
                'tier_rules' => (array) get_post_meta($data['tree_id'], 'rst_tier_requirements', true),
            ];
        }
        $settings = Rpg_Skill_Trees::get_settings();
        $remaining = $settings['tier_points'];
        $tree_spent = [];
        foreach ($selected_skills as $id) {
            if (!isset($skills[$id])) {
                return __('Unknown skill selected', 'rpg-skill-trees');
            }
            $skill = $skills[$id];
            // prerequisites
            foreach ($skill['prerequisites'] as $pr) {
                if (!in_array($pr, $selected_skills, false)) {
                    return sprintf(__('Missing prerequisite for %s', 'rpg-skill-trees'), $skill['name']);
                }
            }
            $tier = $skill['tier'];
            $tree_id = $skill['tree_id'];
            $tree_spent[$tree_id][$tier] = ($tree_spent[$tree_id][$tier] ?? 0) + $skill['cost'];
            $requirement = floatval($trees[$tree_id]['tier_rules'][$tier] ?? 0);
            if ($tier > 1 && ($tree_spent[$tree_id][$tier-1] ?? 0) < $requirement) {
                return sprintf(__('Tier requirement not met for %s', 'rpg-skill-trees'), $skill['name']);
            }
            // tier point spend with conversion
            $cost = $skill['cost'];
            $result = $this->spend_with_conversion($remaining, $cost, $tier, $settings['conversions']);
            if (!$result['success']) {
                return sprintf(__('Not enough points for %s', 'rpg-skill-trees'), $skill['name']);
            }
            $remaining = $result['remaining'];
        }
        return true;
    }

    private function spend_with_conversion($remaining, $cost, $tier, $rules) {
        $original = $remaining;
        $remaining[$tier] = $remaining[$tier] ?? 0;
        if ($remaining[$tier] >= $cost) {
            $remaining[$tier] -= $cost;
            return ['success' => true, 'remaining' => $remaining];
        }
        $deficit = $cost - $remaining[$tier];
        $remaining[$tier] = 0;
        for ($i = 1; $i <= 4; $i++) {
            if ($i === $tier) continue;
            $available = $remaining[$i] ?? 0;
            $ratio = $this->lookup_conversion_ratio($rules, $i, $tier);
            if ($available <= 0 || $ratio <= 0) continue;
            $produced = $available * $ratio;
            if ($produced >= $deficit) {
                $needed_from_donor = $deficit / $ratio;
                $remaining[$i] -= $needed_from_donor;
                $deficit = 0;
                break;
            } else {
                $remaining[$i] = 0;
                $deficit -= $produced;
            }
        }
        if ($deficit <= 0) {
            return ['success' => true, 'remaining' => $remaining];
        }
        return ['success' => false, 'remaining' => $original];
    }

    private function lookup_conversion_ratio($rules, $from, $to) {
        foreach ($rules as $rule) {
            if (intval($rule['from']) === intval($from) && intval($rule['to']) === intval($to)) {
                return floatval($rule['ratio']);
            }
        }
        return 0;
    }

    public function handle_save_build() {
        check_ajax_referer('rst_public_nonce', 'nonce');
        $settings = Rpg_Skill_Trees::get_settings();
        $skills = isset($_POST['skills']) ? array_map('intval', (array) $_POST['skills']) : [];
        $validation = $this->validate_build($skills);
        if ($validation !== true) {
            wp_send_json_error(['message' => $validation]);
        }
        if (!is_user_logged_in()) {
            wp_send_json_success(['message' => __('Not logged in: build kept in browser storage.', 'rpg-skill-trees')]);
        }
        $user_id = get_current_user_id();
        $builds = get_user_meta($user_id, 'rst_builds', true);
        if (!is_array($builds)) {
            $builds = [];
        }
        $payload = [
            'skills' => $skills,
            'saved_at' => current_time('mysql'),
        ];
        if (!$settings['allow_multiple_builds']) {
            $builds = [$payload];
        } else {
            $builds[] = $payload;
        }
        update_user_meta($user_id, 'rst_builds', $builds);
        wp_send_json_success(['message' => __('Build saved.', 'rpg-skill-trees')]);
    }

    public function handle_load_build() {
        check_ajax_referer('rst_public_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_success(['build' => null, 'message' => __('Not logged in: use browser storage.', 'rpg-skill-trees')]);
        }
        $builds = get_user_meta(get_current_user_id(), 'rst_builds', true);
        if (empty($builds)) {
            wp_send_json_success(['build' => null, 'message' => __('No saved build.', 'rpg-skill-trees')]);
        }
        $build = is_array($builds) ? end($builds) : null;
        wp_send_json_success(['build' => $build, 'message' => __('Loaded latest build.', 'rpg-skill-trees')]);
    }
}
