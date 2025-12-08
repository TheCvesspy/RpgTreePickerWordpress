<?php
class RPG_Skill_Trees {
    const VERSION = '1.0.0';
    const OPTION_KEY = 'rpg_skill_trees_settings';
    const BUILD_META_KEY = 'rpg_skill_trees_builds';

    public function run() {
        add_action('init', [$this, 'register_post_types']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_boxes']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'public_assets']);
        add_shortcode('rpg_skill_trees', [$this, 'render_shortcode']);
        add_action('wp_ajax_rpg_skill_trees_save_build', [$this, 'ajax_save_build']);
        add_action('wp_ajax_rpg_skill_trees_load_build', [$this, 'ajax_load_build']);
        add_action('wp_ajax_nopriv_rpg_skill_trees_load_build', [$this, 'ajax_load_build']);
    }

    public function register_post_types() {
        register_post_type('rpg_skill_tree', [
            'labels' => [
                'name' => __('Skill Trees', 'rpg-skill-trees'),
                'singular_name' => __('Skill Tree', 'rpg-skill-trees'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'thumbnail'],
        ]);

        register_post_type('rpg_skill', [
            'labels' => [
                'name' => __('Skills', 'rpg-skill-trees'),
                'singular_name' => __('Skill', 'rpg-skill-trees'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'thumbnail'],
        ]);
    }

    public function register_meta_boxes() {
        add_meta_box('rpg_skill_tree_meta', __('Tree Settings', 'rpg-skill-trees'), [$this, 'render_tree_meta'], 'rpg_skill_tree', 'normal', 'default');
        add_meta_box('rpg_skill_meta', __('Skill Settings', 'rpg-skill-trees'), [$this, 'render_skill_meta'], 'rpg_skill', 'normal', 'default');
    }

    public function render_tree_meta($post) {
        wp_nonce_field('rpg_skill_tree_meta', 'rpg_skill_tree_meta_nonce');
        $icon = get_post_meta($post->ID, '_rpg_icon', true);
        $tier_requirements = (array) get_post_meta($post->ID, '_rpg_tier_requirements', true);
        echo '<p><label>' . esc_html__('Icon', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="text" class="widefat rpg-icon-input" id="rpg_tree_icon" name="rpg_icon" value="' . esc_attr($icon) . '" />';
        echo '<button type="button" class="button rpg-upload-icon" data-target="rpg_tree_icon">' . esc_html__('Upload Icon', 'rpg-skill-trees') . '</button></p>';
        echo '<h4>' . esc_html__('Tier Investment Requirements', 'rpg-skill-trees') . '</h4>';
        for ($i = 1; $i <= 3; $i++) {
            $val = isset($tier_requirements[$i]) ? intval($tier_requirements[$i]) : 0;
            echo '<p><label>' . sprintf(esc_html__('Minimum Tier %d points before Tier %d unlocks', 'rpg-skill-trees'), $i, $i + 1) . '</label><br />';
            echo '<input type="number" min="0" name="rpg_tier_requirements[' . $i . ']" value="' . esc_attr($val) . '" /></p>';
        }
    }

    public function render_skill_meta($post) {
        wp_nonce_field('rpg_skill_meta', 'rpg_skill_meta_nonce');
        $tree_meta = get_post_meta($post->ID, '_rpg_trees', true);
        if (empty($tree_meta)) {
            $legacy_tree = get_post_meta($post->ID, '_rpg_tree', true);
            $tree_meta = $legacy_tree ? [$legacy_tree] : [];
        }
        $tier = get_post_meta($post->ID, '_rpg_tier', true);
        $cost = get_post_meta($post->ID, '_rpg_cost', true);
        $icon = get_post_meta($post->ID, '_rpg_icon', true);
        $tooltip = get_post_meta($post->ID, '_rpg_tooltip', true);
        $effect = get_post_meta($post->ID, '_rpg_effect', true);
        $prereqs = (array) get_post_meta($post->ID, '_rpg_prereqs', true);

        $trees = get_posts(['post_type' => 'rpg_skill_tree', 'numberposts' => -1]);
        $skills = get_posts(['post_type' => 'rpg_skill', 'numberposts' => -1]);

        echo '<p><label>' . esc_html__('Tree(s)', 'rpg-skill-trees') . '</label><br />';
        echo '<select name="rpg_trees[]" multiple size="5" class="widefat">';
        foreach ($trees as $t) {
            echo '<option value="' . esc_attr($t->ID) . '" ' . selected(in_array($t->ID, $tree_meta, true), true, false) . '>' . esc_html($t->post_title) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label>' . esc_html__('Tier (1-4)', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="number" min="1" max="4" name="rpg_tier" value="' . esc_attr($tier ? $tier : 1) . '" /></p>';

        echo '<p><label>' . esc_html__('Tier Point Cost', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="number" step="0.1" min="0" name="rpg_cost" value="' . esc_attr($cost ? $cost : 1) . '" /></p>';

        echo '<p><label>' . esc_html__('Icon', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="text" class="widefat rpg-icon-input" id="rpg_skill_icon" name="rpg_icon" value="' . esc_attr($icon) . '" />';
        echo '<button type="button" class="button rpg-upload-icon" data-target="rpg_skill_icon">' . esc_html__('Upload Icon', 'rpg-skill-trees') . '</button></p>';

        echo '<p><label>' . esc_html__('Tooltip', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="text" class="widefat" name="rpg_tooltip" value="' . esc_attr($tooltip) . '" /></p>';

        echo '<p><label>' . esc_html__('Effect', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="text" class="widefat" name="rpg_effect" value="' . esc_attr($effect) . '" /></p>';

        echo '<p><label>' . esc_html__('Prerequisite Skills', 'rpg-skill-trees') . '</label><br />';
        echo '<select name="rpg_prereqs[]" multiple size="5" class="widefat">';
        foreach ($skills as $s) {
            echo '<option value="' . esc_attr($s->ID) . '" ' . selected(in_array($s->ID, $prereqs), true, false) . '>' . esc_html($s->post_title) . '</option>';
        }
        echo '</select></p>';
    }

    public function save_meta_boxes($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['rpg_skill_tree_meta_nonce']) && wp_verify_nonce($_POST['rpg_skill_tree_meta_nonce'], 'rpg_skill_tree_meta')) {
            $icon = isset($_POST['rpg_icon']) ? esc_url_raw($_POST['rpg_icon']) : '';
            update_post_meta($post_id, '_rpg_icon', $icon);
            $reqs = isset($_POST['rpg_tier_requirements']) ? array_map('intval', (array) $_POST['rpg_tier_requirements']) : [];
            update_post_meta($post_id, '_rpg_tier_requirements', $reqs);
        }

        if (isset($_POST['rpg_skill_meta_nonce']) && wp_verify_nonce($_POST['rpg_skill_meta_nonce'], 'rpg_skill_meta')) {
            $trees = isset($_POST['rpg_trees']) ? array_map('intval', (array) $_POST['rpg_trees']) : [];
            $tier = isset($_POST['rpg_tier']) ? intval($_POST['rpg_tier']) : 1;
            $cost = isset($_POST['rpg_cost']) ? floatval($_POST['rpg_cost']) : 1;
            $icon = isset($_POST['rpg_icon']) ? esc_url_raw($_POST['rpg_icon']) : '';
            $tooltip = isset($_POST['rpg_tooltip']) ? wp_kses_post($_POST['rpg_tooltip']) : '';
            $effect = isset($_POST['rpg_effect']) ? wp_kses_post($_POST['rpg_effect']) : '';
            $prereqs = isset($_POST['rpg_prereqs']) ? array_map('intval', (array) $_POST['rpg_prereqs']) : [];

            update_post_meta($post_id, '_rpg_trees', $trees);
            if (!empty($trees)) {
                update_post_meta($post_id, '_rpg_tree', $trees[0]);
            }
            update_post_meta($post_id, '_rpg_tier', max(1, min(4, $tier)));
            update_post_meta($post_id, '_rpg_cost', max(0, $cost));
            update_post_meta($post_id, '_rpg_icon', $icon);
            update_post_meta($post_id, '_rpg_tooltip', $tooltip);
            update_post_meta($post_id, '_rpg_effect', $effect);
            update_post_meta($post_id, '_rpg_prereqs', $prereqs);
        }
    }

    public function register_admin_menu() {
        add_menu_page(__('RPG Skill Trees', 'rpg-skill-trees'), __('RPG Skill Trees', 'rpg-skill-trees'), 'manage_options', 'rpg-skill-trees', [$this, 'render_skill_trees_page'], 'dashicons-networking');
        add_submenu_page('rpg-skill-trees', __('Skill Trees', 'rpg-skill-trees'), __('Skill Trees', 'rpg-skill-trees'), 'manage_options', 'edit.php?post_type=rpg_skill_tree');
        add_submenu_page('rpg-skill-trees', __('Skills', 'rpg-skill-trees'), __('Skills', 'rpg-skill-trees'), 'manage_options', 'edit.php?post_type=rpg_skill');
        add_submenu_page('rpg-skill-trees', __('Global Settings', 'rpg-skill-trees'), __('Global Settings', 'rpg-skill-trees'), 'manage_options', 'rpg-skill-trees-settings', [$this, 'render_settings_page']);
    }

    public function render_skill_trees_page() {
        wp_redirect(admin_url('edit.php?post_type=rpg_skill_tree'));
        exit;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = $this->get_settings();
        if (isset($_POST['rpg_skill_trees_settings_nonce']) && wp_verify_nonce($_POST['rpg_skill_trees_settings_nonce'], 'rpg_skill_trees_settings')) {
            $settings['tier_points'] = isset($_POST['tier_points']) ? array_map('floatval', (array) $_POST['tier_points']) : [1=>5,2=>3,3=>2,4=>1];
            $settings['conversions'] = [];
            if (!empty($_POST['conversion_from'])) {
                foreach ($_POST['conversion_from'] as $idx => $from) {
                    $to = intval($_POST['conversion_to'][$idx]);
                    $ratio = floatval($_POST['conversion_ratio'][$idx]);
                    $settings['conversions'][] = [
                        'from' => intval($from),
                        'to' => $to,
                        'ratio' => $ratio,
                    ];
                }
            }
            $settings['require_login'] = isset($_POST['require_login']) ? 1 : 0;
            $settings['allow_multiple_builds'] = isset($_POST['allow_multiple_builds']) ? 1 : 0;
            update_option(self::OPTION_KEY, $settings);
            echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'rpg-skill-trees') . '</p></div>';
        }
        include plugin_dir_path(__FILE__) . '../admin/settings-page.php';
    }

    public function admin_assets($hook) {
        if (false !== strpos($hook, 'rpg_skill_tree') || false !== strpos($hook, 'rpg_skill') || false !== strpos($hook, 'rpg-skill-trees')) {
            wp_enqueue_media();
            wp_enqueue_style('rpg-skill-trees-admin', plugins_url('../assets/css/admin.css', __FILE__), [], self::VERSION);
            wp_enqueue_script('rpg-skill-trees-admin', plugins_url('../assets/js/admin.js', __FILE__), ['jquery'], self::VERSION, true);
            wp_localize_script('rpg-skill-trees-admin', 'rpgSkillTreesAdmin', [
                'upload_title' => __('Select icon', 'rpg-skill-trees'),
                'upload_button' => __('Use this icon', 'rpg-skill-trees'),
            ]);
        }
    }

    public function public_assets() {
        if (!is_singular() && !is_home() && !is_archive()) {
            return;
        }
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }
        if (has_shortcode($post->post_content, 'rpg_skill_trees')) {
            wp_enqueue_style('rpg-skill-trees-public', plugins_url('../assets/css/public.css', __FILE__), [], self::VERSION);
            wp_enqueue_script('rpg-skill-trees-public', plugins_url('../assets/js/public.js', __FILE__), ['jquery'], self::VERSION, true);
            wp_localize_script('rpg-skill-trees-public', 'RPGSkillTreesData', $this->get_public_data());
        }
    }

    private function get_public_data() {
        $trees = get_posts(['post_type' => 'rpg_skill_tree', 'numberposts' => -1]);
        $skills = get_posts(['post_type' => 'rpg_skill', 'numberposts' => -1]);
        $tree_data = [];
        foreach ($trees as $tree) {
            $tree_data[$tree->ID] = [
                'id' => $tree->ID,
                'name' => $tree->post_title,
                'description' => wp_kses_post($tree->post_content),
                'icon' => esc_url(get_post_meta($tree->ID, '_rpg_icon', true)),
                'tier_requirements' => (array) get_post_meta($tree->ID, '_rpg_tier_requirements', true),
            ];
        }
        $skill_data = [];
        foreach ($skills as $skill) {
            $trees_for_skill = get_post_meta($skill->ID, '_rpg_trees', true);
            if (empty($trees_for_skill)) {
                $legacy_tree = get_post_meta($skill->ID, '_rpg_tree', true);
                $trees_for_skill = $legacy_tree ? [$legacy_tree] : [];
            }
            foreach ((array) $trees_for_skill as $tree_id) {
                $tree_id = intval($tree_id);
                if ($tree_id <= 0) {
                    continue;
                }
                $skill_data[] = [
                    'id' => $skill->ID,
                    'instance' => $skill->ID . ':' . $tree_id,
                    'name' => $skill->post_title,
                    'tree' => $tree_id,
                    'tier' => intval(get_post_meta($skill->ID, '_rpg_tier', true)),
                    'cost' => floatval(get_post_meta($skill->ID, '_rpg_cost', true)),
                    'icon' => esc_url(get_post_meta($skill->ID, '_rpg_icon', true)),
                    'tooltip' => wp_kses_post(get_post_meta($skill->ID, '_rpg_tooltip', true)),
                    'effect' => wp_kses_post(get_post_meta($skill->ID, '_rpg_effect', true)),
                    'prereqs' => (array) get_post_meta($skill->ID, '_rpg_prereqs', true),
                ];
            }
        }
        return [
            'trees' => array_values($tree_data),
            'skills' => $skill_data,
            'settings' => $this->get_settings(),
            'currentUser' => is_user_logged_in() ? get_current_user_id() : 0,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rpg_skill_trees_nonce'),
            'i18n' => [
                'insufficientPoints' => __('Not enough points for this tier.', 'rpg-skill-trees'),
                'lockedByTier' => __('Tier requirements not met.', 'rpg-skill-trees'),
                'requiresSkills' => __('Vyžaduje: ', 'rpg-skill-trees'),
                'saved' => __('Build saved', 'rpg-skill-trees'),
                'loginRequired' => __('You must be logged in to save builds.', 'rpg-skill-trees'),
                'treeRequired' => __('Select the related tree first.', 'rpg-skill-trees'),
                'tierRemovalBlocked' => __('Removing this skill would break tier requirements for selected higher tiers.', 'rpg-skill-trees'),
            ],
        ];
    }

    public function render_shortcode($atts) {
        ob_start();
        include plugin_dir_path(__FILE__) . '../public/shortcode.php';
        return ob_get_clean();
    }

    public function ajax_save_build() {
        check_ajax_referer('rpg_skill_trees_nonce', 'nonce');
        $settings = $this->get_settings();
        if ($settings['require_login'] && !is_user_logged_in()) {
            wp_send_json_error(__('Login required', 'rpg-skill-trees'));
        }
        $build = isset($_POST['build']) ? json_decode(stripslashes($_POST['build']), true) : [];
        if (!is_array($build)) {
            wp_send_json_error(__('Invalid build', 'rpg-skill-trees'));
        }
        $validation = $this->validate_build($build, $settings);
        if (!$validation['valid']) {
            wp_send_json_error($validation['message']);
        }
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            if ($settings['allow_multiple_builds']) {
                $existing = get_user_meta($user_id, self::BUILD_META_KEY, true);
                if (!is_array($existing)) {
                    $existing = [];
                }
                $existing[] = $build;
                update_user_meta($user_id, self::BUILD_META_KEY, $existing);
            } else {
                update_user_meta($user_id, self::BUILD_META_KEY, $build);
            }
            wp_send_json_success(__('Build saved', 'rpg-skill-trees'));
        } else {
            set_transient('rpg_skill_trees_guest_' . md5($_SERVER['REMOTE_ADDR']), $build, HOUR_IN_SECONDS);
            wp_send_json_success(__('Build saved locally', 'rpg-skill-trees'));
        }
    }

    public function ajax_load_build() {
        check_ajax_referer('rpg_skill_trees_nonce', 'nonce');
        $settings = $this->get_settings();
        if (is_user_logged_in()) {
            $build = get_user_meta(get_current_user_id(), self::BUILD_META_KEY, true);
            wp_send_json_success($build);
        }
        $build = get_transient('rpg_skill_trees_guest_' . md5($_SERVER['REMOTE_ADDR']));
        if ($build) {
            wp_send_json_success($build);
        }
        wp_send_json_success([]);
    }

    private function validate_build($build, $settings) {
        $selected_trees = isset($build['trees']) ? array_map('intval', (array) $build['trees']) : [];
        $raw_selected_skills = isset($build['skills']) && is_array($build['skills']) ? array_keys(array_filter($build['skills'])) : [];
        $skills = get_posts(['post_type' => 'rpg_skill', 'numberposts' => -1]);
        $skill_map = [];
        $skill_tree_map = [];
        foreach ($skills as $skill) {
            $trees_for_skill = get_post_meta($skill->ID, '_rpg_trees', true);
            if (empty($trees_for_skill)) {
                $legacy_tree = get_post_meta($skill->ID, '_rpg_tree', true);
                $trees_for_skill = $legacy_tree ? [$legacy_tree] : [];
            }
            $skill_tree_map[$skill->ID] = array_map('intval', (array) $trees_for_skill);
            foreach ($skill_tree_map[$skill->ID] as $tree_id) {
                if ($tree_id <= 0) {
                    continue;
                }
                $instance_key = $skill->ID . ':' . $tree_id;
                $skill_map[$instance_key] = [
                    'skill_id' => $skill->ID,
                    'tree' => $tree_id,
                    'tier' => intval(get_post_meta($skill->ID, '_rpg_tier', true)),
                    'cost' => floatval(get_post_meta($skill->ID, '_rpg_cost', true)),
                    'prereqs' => (array) get_post_meta($skill->ID, '_rpg_prereqs', true),
                ];
            }
        }

        $selected_skills = [];
        foreach ($raw_selected_skills as $raw_key) {
            if (false !== strpos($raw_key, ':')) {
                [$skill_id, $tree_id] = array_map('intval', explode(':', $raw_key));
                $instance_key = $skill_id . ':' . $tree_id;
            } else {
                $skill_id = intval($raw_key);
                $tree_id = 0;
                if (isset($skill_tree_map[$skill_id])) {
                    foreach ($skill_tree_map[$skill_id] as $candidate) {
                        if (in_array($candidate, $selected_trees, true)) {
                            $tree_id = $candidate;
                            break;
                        }
                    }
                    if ($tree_id === 0 && !empty($skill_tree_map[$skill_id])) {
                        $tree_id = intval($skill_tree_map[$skill_id][0]);
                    }
                }
                $instance_key = $tree_id > 0 ? ($skill_id . ':' . $tree_id) : (string) $skill_id;
            }

            if (!isset($skill_map[$instance_key])) {
                return ['valid' => false, 'message' => __('Unknown skill in build.', 'rpg-skill-trees')];
            }
            $selected_skills[] = $instance_key;
        }

        usort($selected_skills, function($a, $b) use ($skill_map) {
            $tier_a = isset($skill_map[$a]) ? $skill_map[$a]['tier'] : 0;
            $tier_b = isset($skill_map[$b]) ? $skill_map[$b]['tier'] : 0;
            return $tier_a <=> $tier_b;
        });

        $totals = $settings['tier_points'];
        $spent = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $tree_spent = [];
        foreach ($selected_skills as $skill_instance) {
            if (!isset($skill_map[$skill_instance])) {
                return ['valid' => false, 'message' => __('Unknown skill in build.', 'rpg-skill-trees')];
            }
            $skill = $skill_map[$skill_instance];
            if (!in_array($skill['tree'], $selected_trees, true)) {
                return ['valid' => false, 'message' => __('Skill tree not selected.', 'rpg-skill-trees')];
            }
            if (!$this->prerequisites_met_server($skill['prereqs'], $selected_skills, $skill['tree'])) {
                return ['valid' => false, 'message' => __('Prerequisites missing.', 'rpg-skill-trees')];
            }
            if (!$this->tier_requirement_met_server($skill, $tree_spent)) {
                return ['valid' => false, 'message' => __('Tier requirement not met.', 'rpg-skill-trees')];
            }
            if (!$this->has_points_for_skill_server($skill, $totals, $spent, $settings)) {
                return ['valid' => false, 'message' => __('Not enough points.', 'rpg-skill-trees')];
            }
            $spent[$skill['tier']] += $skill['cost'];
            if (!isset($tree_spent[$skill['tree']])) {
                $tree_spent[$skill['tree']] = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            }
            $tree_spent[$skill['tree']][$skill['tier']] += $skill['cost'];
        }
        return ['valid' => true, 'message' => ''];
    }

    private function has_points_for_skill_server($skill, $totals, $spent, $settings) {
        $tier = $skill['tier'];
        $available = $totals[$tier] - $spent[$tier];
        if ($available >= $skill['cost']) {
            return true;
        }
        $effective = $available;
        for ($t = 1; $t <= 4; $t++) {
            if ($t === $tier) {
                continue;
            }
            $remaining = $totals[$t] - $spent[$t];
            if ($remaining > 0) {
                $effective += $this->convert_points($remaining, $t, $tier, $settings);
            }
        }
        return $effective >= $skill['cost'];
    }

    private function convert_points($amount, $from, $to, $settings) {
        if ($from === $to) {
            return $amount;
        }
        if (empty($settings['conversions'])) {
            return 0;
        }
        foreach ($settings['conversions'] as $rule) {
            if (intval($rule['from']) === intval($from) && intval($rule['to']) === intval($to)) {
                return $amount * floatval($rule['ratio']);
            }
        }
        return 0;
    }

    private function prerequisites_met_server($prereqs, $selected_skills, $tree_id) {
        if (empty($prereqs)) {
            return true;
        }
        foreach ($prereqs as $req) {
            $instance_key = $req . ':' . $tree_id;
            if (!in_array($instance_key, $selected_skills, true)) {
                return false;
            }
        }
        return true;
    }

    private function tier_requirement_met_server($skill, $tree_spent) {
        if ($skill['tier'] <= 1) {
            return true;
        }
        $reqs = (array) get_post_meta($skill['tree'], '_rpg_tier_requirements', true);
        $required = isset($reqs[$skill['tier'] - 1]) ? floatval($reqs[$skill['tier'] - 1]) : 0;
        if ($required <= 0) {
            return true;
        }
        $spent = isset($tree_spent[$skill['tree']][$skill['tier'] - 1]) ? $tree_spent[$skill['tree']][$skill['tier'] - 1] : 0;
        return $spent >= $required;
    }

    public function get_settings() {
        $defaults = [
            'tier_points' => [1 => 5, 2 => 3, 3 => 2, 4 => 1],
            'conversions' => [],
            'require_login' => 0,
            'allow_multiple_builds' => 0,
        ];
        $settings = get_option(self::OPTION_KEY, []);
        return wp_parse_args($settings, $defaults);
    }
}
