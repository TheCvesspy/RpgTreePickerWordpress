<?php
class RPG_Skill_Trees {
    const VERSION = '1.4.10';
    const OPTION_KEY = 'rpg_skill_trees_settings';
    const BUILD_META_KEY = 'rpg_skill_trees_builds';

    public function run() {
        add_action('init', [$this, 'register_post_types']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_boxes']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_filter('manage_rpg_skill_posts_columns', [$this, 'add_skill_columns']);
        add_action('manage_rpg_skill_posts_custom_column', [$this, 'render_skill_columns'], 10, 2);
        add_filter('manage_rpg_skill_tree_posts_columns', [$this, 'add_tree_columns']);
        add_action('manage_rpg_skill_tree_posts_custom_column', [$this, 'render_tree_columns'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'public_assets']);
        add_shortcode('rpg_skill_trees', [$this, 'render_shortcode']);
        add_action('wp_ajax_rpg_skill_trees_save_build', [$this, 'ajax_save_build']);
        add_action('wp_ajax_rpg_skill_trees_load_build', [$this, 'ajax_load_build']);
        add_action('wp_ajax_nopriv_rpg_skill_trees_load_build', [$this, 'ajax_load_build']);
    }

    private function get_active_tree_meta_query() {
        return [
            'relation' => 'OR',
            ['key' => 'rst_active', 'compare' => 'NOT EXISTS'],
            ['key' => 'rst_active', 'value' => '0', 'compare' => '!='],
        ];
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
        $active_meta = get_post_meta($post->ID, 'rst_active', true);
        $active = ($active_meta === '' ? 1 : intval($active_meta));
        echo '<p><label>' . esc_html__('Icon', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="text" class="widefat rpg-icon-input" id="rpg_tree_icon" name="rpg_icon" value="' . esc_attr($icon) . '" />';
        echo '<button type="button" class="button rpg-upload-icon" data-target="rpg_tree_icon">' . esc_html__('Upload Icon', 'rpg-skill-trees') . '</button></p>';
        echo '<p class="rst-toggle-switch">';
        echo '<input type="checkbox" id="rst_tree_active" name="rst_active" value="1" ' . checked($active, 1, false) . ' />';
        echo '<label for="rst_tree_active">' . esc_html__('Active (show on front end)', 'rpg-skill-trees') . '</label>';
        echo '</p>';
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
        $sort_order = get_post_meta($post->ID, 'rst_sort_order', true);

        $trees = get_posts(['post_type' => 'rpg_skill_tree', 'numberposts' => -1]);
        $skills = get_posts(['post_type' => 'rpg_skill', 'numberposts' => -1]);

        echo '<p><label>' . esc_html__('Tree(s)', 'rpg-skill-trees') . '</label></p>';
        echo '<div class="rpg-checkbox-list">';
        foreach ($trees as $t) {
            $input_id = 'rpg-tree-' . $t->ID;
            echo '<label class="rpg-checkbox-item" for="' . esc_attr($input_id) . '">';
            echo '<input type="checkbox" id="' . esc_attr($input_id) . '" name="rpg_trees[]" value="' . esc_attr($t->ID) . '" ' . checked(in_array($t->ID, $tree_meta, true), true, false) . ' /> ' . esc_html($t->post_title);
            echo '</label>';
        }
        echo '</div>';

        echo '<p><label>' . esc_html__('Tier (1-4)', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="number" min="1" max="4" name="rpg_tier" value="' . esc_attr($tier ? $tier : 1) . '" /></p>';

        echo '<p><label>' . esc_html__('Tier Point Cost', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="number" step="0.1" min="0" name="rpg_cost" value="' . esc_attr($cost ? $cost : 1) . '" /></p>';

        echo '<p><label>' . esc_html__('Sort Order', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="number" name="rpg_sort_order" value="' . esc_attr($sort_order ? $sort_order : 0) . '" />';
        echo '<span class="description">' . esc_html__('Controls ordering of skill cards in user view.', 'rpg-skill-trees') . '</span></p>';

        echo '<p><label>' . esc_html__('Icon', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="text" class="widefat rpg-icon-input" id="rpg_skill_icon" name="rpg_icon" value="' . esc_attr($icon) . '" />';
        echo '<button type="button" class="button rpg-upload-icon" data-target="rpg_skill_icon">' . esc_html__('Upload Icon', 'rpg-skill-trees') . '</button></p>';

        echo '<p><label>' . esc_html__('Tooltip', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="text" class="widefat" name="rpg_tooltip" value="' . esc_attr($tooltip) . '" /></p>';

        echo '<p><label>' . esc_html__('Effect', 'rpg-skill-trees') . '</label><br />';
        echo '<input type="text" class="widefat" name="rpg_effect" value="' . esc_attr($effect) . '" /></p>';

        echo '<p><label>' . esc_html__('Prerequisite Skills', 'rpg-skill-trees') . '</label></p>';
        echo '<div class="rpg-checkbox-columns">';
        $chunks = array_chunk($skills, 10);
        foreach ($chunks as $chunk_index => $skill_chunk) {
            echo '<div class="rpg-checkbox-column">';
            foreach ($skill_chunk as $s) {
                $input_id = 'rpg-prereq-' . $s->ID . '-' . $chunk_index;
                echo '<label class="rpg-checkbox-item" for="' . esc_attr($input_id) . '">';
                echo '<input type="checkbox" id="' . esc_attr($input_id) . '" name="rpg_prereqs[]" value="' . esc_attr($s->ID) . '" ' . checked(in_array($s->ID, $prereqs), true, false) . ' /> ' . esc_html($s->post_title);
                echo '</label>';
            }
            echo '</div>';
        }
        echo '</div>';
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
            update_post_meta($post_id, 'rst_active', isset($_POST['rst_active']) ? 1 : 0);
        }

        if (isset($_POST['rpg_skill_meta_nonce']) && wp_verify_nonce($_POST['rpg_skill_meta_nonce'], 'rpg_skill_meta')) {
            $trees = isset($_POST['rpg_trees']) ? array_map('intval', (array) $_POST['rpg_trees']) : [];
            $tier = isset($_POST['rpg_tier']) ? intval($_POST['rpg_tier']) : 1;
            $cost = isset($_POST['rpg_cost']) ? floatval($_POST['rpg_cost']) : 1;
            $icon = isset($_POST['rpg_icon']) ? esc_url_raw($_POST['rpg_icon']) : '';
            $tooltip = isset($_POST['rpg_tooltip']) ? wp_kses_post($_POST['rpg_tooltip']) : '';
            $effect = isset($_POST['rpg_effect']) ? wp_kses_post($_POST['rpg_effect']) : '';
            $prereqs = isset($_POST['rpg_prereqs']) ? array_map('intval', (array) $_POST['rpg_prereqs']) : [];
            $sort_order = isset($_POST['rpg_sort_order']) ? intval($_POST['rpg_sort_order']) : 0;

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
            update_post_meta($post_id, 'rst_sort_order', $sort_order);
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
            $settings['font_sizes'] = [
                'title' => isset($_POST['font_sizes']['title']) ? floatval($_POST['font_sizes']['title']) : 14,
                'tooltip' => isset($_POST['font_sizes']['tooltip']) ? floatval($_POST['font_sizes']['tooltip']) : 12,
                'requirements' => isset($_POST['font_sizes']['requirements']) ? floatval($_POST['font_sizes']['requirements']) : 12,
            ];
            $settings['header_font_sizes'] = [
                'title' => isset($_POST['header_font_sizes']['title']) ? floatval($_POST['header_font_sizes']['title']) : 18,
                'label' => isset($_POST['header_font_sizes']['label']) ? floatval($_POST['header_font_sizes']['label']) : 13,
                'message' => isset($_POST['header_font_sizes']['message']) ? floatval($_POST['header_font_sizes']['message']) : 13,
            ];
            $settings['colors'] = [
                'layout_bg' => sanitize_hex_color($_POST['colors']['layout_bg'] ?? '') ?: '#0b1021',
                'layout_border' => sanitize_hex_color($_POST['colors']['layout_border'] ?? '') ?: '#1f2937',
                'layout_text' => sanitize_hex_color($_POST['colors']['layout_text'] ?? '') ?: '#e5e7eb',
                'tier_bg' => sanitize_hex_color($_POST['colors']['tier_bg'] ?? '') ?: '#111827',
                'tier_border' => sanitize_hex_color($_POST['colors']['tier_border'] ?? '') ?: '#1f2937',
                'tier_title' => sanitize_hex_color($_POST['colors']['tier_title'] ?? '') ?: '#93c5fd',
                'button_bg' => sanitize_hex_color($_POST['colors']['button_bg'] ?? '') ?: '#1f2937',
                'button_text' => sanitize_hex_color($_POST['colors']['button_text'] ?? '') ?: '#f9fafb',
                'button_border' => sanitize_hex_color($_POST['colors']['button_border'] ?? '') ?: '#374151',
                'button_hover' => sanitize_hex_color($_POST['colors']['button_hover'] ?? '') ?: '#2563eb',
                'skill_bg' => sanitize_hex_color($_POST['colors']['skill_bg'] ?? '') ?: '#1f2937',
                'skill_border' => sanitize_hex_color($_POST['colors']['skill_border'] ?? '') ?: '#374151',
                'skill_text' => sanitize_hex_color($_POST['colors']['skill_text'] ?? '') ?: '#f9fafb',
                'skill_tooltip_bg' => sanitize_hex_color($_POST['colors']['skill_tooltip_bg'] ?? '') ?: '#111827',
                'skill_tooltip' => sanitize_hex_color($_POST['colors']['skill_tooltip'] ?? '') ?: '#cbd5e1',
                'skill_prereq' => sanitize_hex_color($_POST['colors']['skill_prereq'] ?? '') ?: '#fbbf24',
                'skill_selected_bg' => sanitize_hex_color($_POST['colors']['skill_selected_bg'] ?? '') ?: '#0f172a',
                'skill_selected_border' => sanitize_hex_color($_POST['colors']['skill_selected_border'] ?? '') ?: '#60a5fa',
                'skill_hover_border' => sanitize_hex_color($_POST['colors']['skill_hover_border'] ?? '') ?: '#60a5fa',
                'points_bg' => sanitize_hex_color($_POST['colors']['points_bg'] ?? '') ?: '#111827',
                'points_border' => sanitize_hex_color($_POST['colors']['points_border'] ?? '') ?: '#1f2937',
                'points_text' => sanitize_hex_color($_POST['colors']['points_text'] ?? '') ?: '#e5e7eb',
                'points_label' => sanitize_hex_color($_POST['colors']['points_label'] ?? '') ?: '#cbd5f5',
                'messages' => sanitize_hex_color($_POST['colors']['messages'] ?? '') ?: '#fca5a5',
            ];
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
            $settings = $this->get_settings();
            $title_size = isset($settings['font_sizes']['title']) ? max(1, floatval($settings['font_sizes']['title'])) : 14;
            $tooltip_size = isset($settings['font_sizes']['tooltip']) ? max(1, floatval($settings['font_sizes']['tooltip'])) : 12;
            $requirements_size = isset($settings['font_sizes']['requirements']) ? max(1, floatval($settings['font_sizes']['requirements'])) : 12;
            $header_title_size = isset($settings['header_font_sizes']['title']) ? max(1, floatval($settings['header_font_sizes']['title'])) : 18;
            $header_label_size = isset($settings['header_font_sizes']['label']) ? max(1, floatval($settings['header_font_sizes']['label'])) : 13;
            $header_message_size = isset($settings['header_font_sizes']['message']) ? max(1, floatval($settings['header_font_sizes']['message'])) : 13;
            $colors = isset($settings['colors']) ? $settings['colors'] : [];
            $inline_styles = '.rpg-skill-trees-builder{background:' . esc_html($colors['layout_bg']) . ';border-color:' . esc_html($colors['layout_border']) . ';color:' . esc_html($colors['layout_text']) . ';}'
                . '.rpg-section-title{font-size:' . $header_title_size . 'px;}'
                . '.rpg-tree-list label,.rpg-rules-toggle{font-size:' . $header_label_size . 'px;}'
                . '.rpg-builder-messages{font-size:' . $header_message_size . 'px;}'
                . '.rpg-tree>h3,.rpg-tier-title{color:' . esc_html($colors['tier_title']) . ';}'
                . '.rpg-tier{background:' . esc_html($colors['tier_bg']) . ';border-color:' . esc_html($colors['tier_border']) . ';}'
                . '.rpg-skill-name{font-size:' . $title_size . 'px;}'
                . '.rpg-skill-tooltip,.rpg-tooltip-effect{font-size:' . $tooltip_size . 'px;}'
                . '.rpg-skill-prereqs{font-size:' . $requirements_size . 'px;}'
                . '.rpg-skill{background:' . esc_html($colors['skill_bg']) . ';border-color:' . esc_html($colors['skill_border']) . ';color:' . esc_html($colors['skill_text']) . ';}'
                . '.rpg-skill:hover{border-color:' . esc_html($colors['skill_hover_border']) . ';}'
                . '.rpg-skill.rpg-selected{background:' . esc_html($colors['skill_selected_bg']) . ';border-color:' . esc_html($colors['skill_selected_border']) . ';box-shadow:0 0 0 2px ' . esc_html($colors['skill_selected_border']) . ';}'
                . '.rpg-skill-name{color:' . esc_html($colors['skill_text']) . ';}'
                . '.rpg-skill-tooltip,.rpg-tooltip-effect{color:' . esc_html($colors['skill_tooltip']) . ';}'
                . '.rpg-hover-tooltip{background:' . esc_html($colors['skill_tooltip_bg']) . ';border-color:' . esc_html($colors['skill_border']) . ';}'
                . '.rpg-skill-prereqs{color:' . esc_html($colors['skill_prereq']) . ';}'
                . '.rpg-skill-trees-builder .button,.rpg-skill-trees-builder .button-primary{background:' . esc_html($colors['button_bg']) . ';color:' . esc_html($colors['button_text']) . ';border-color:' . esc_html($colors['button_border']) . ';}'
                . '.rpg-skill-trees-builder .button:hover,.rpg-skill-trees-builder .button-primary:hover{background:' . esc_html($colors['button_hover']) . ';color:' . esc_html($colors['button_text']) . ';border-color:' . esc_html($colors['button_hover']) . ';}'
                . '.rpg-skill-trees-builder .button-primary{background:' . esc_html($colors['button_hover']) . ';border-color:' . esc_html($colors['button_hover']) . ';}'
                . '.rpg-point-row{background:' . esc_html($colors['points_bg']) . ';border-color:' . esc_html($colors['points_border']) . ';color:' . esc_html($colors['points_text']) . ';}'
                . '.rpg-point-label{color:' . esc_html($colors['points_label']) . ';}'
                . '.rpg-point-values{color:' . esc_html($colors['points_text']) . ';}'
                . '.rpg-builder-messages{color:' . esc_html($colors['messages']) . ';}';
            wp_add_inline_style('rpg-skill-trees-public', $inline_styles);
            wp_localize_script('rpg-skill-trees-public', 'RPGSkillTreesData', $this->get_public_data());
        }
    }

    private function get_public_data() {
        $trees = get_posts([
            'post_type' => 'rpg_skill_tree',
            'numberposts' => -1,
            'meta_query' => $this->get_active_tree_meta_query(),
        ]);
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
                    'sort_order' => intval(get_post_meta($skill->ID, 'rst_sort_order', true)),
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
                'insufficientPoints' => __('Nedostatek bodů pro tuto úroveň.', 'rpg-skill-trees'),
                'lockedByTier' => __('Nesplněné požadavky úrovně.', 'rpg-skill-trees'),
                'requiresSkills' => __('Vyžaduje: ', 'rpg-skill-trees'),
                'saved' => __('Build uložen', 'rpg-skill-trees'),
                'loginRequired' => __('Pro uložení buildu se musíte přihlásit.', 'rpg-skill-trees'),
                'treeRequired' => __('Nejprve vyberte příslušný strom.', 'rpg-skill-trees'),
                'tierRemovalBlocked' => __('Odebrání této schopnosti by narušilo požadavky vyšších úrovní.', 'rpg-skill-trees'),
                'exportError' => __('Export se nezdařil.', 'rpg-skill-trees'),
                'conversionNotAllowed' => __('Konverze pro tyto úrovně není povolena.', 'rpg-skill-trees'),
                'conversionInsufficient' => __('Nedostatek bodů pro konverzi z úrovně ', 'rpg-skill-trees'),
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
            wp_send_json_error(__('Přihlášení je vyžadováno.', 'rpg-skill-trees'));
        }
        $build = isset($_POST['build']) ? json_decode(stripslashes($_POST['build']), true) : [];
        if (!is_array($build)) {
            wp_send_json_error(__('Neplatný build.', 'rpg-skill-trees'));
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
            wp_send_json_success(__('Build uložen', 'rpg-skill-trees'));
        } else {
            set_transient('rpg_skill_trees_guest_' . md5($_SERVER['REMOTE_ADDR']), $build, HOUR_IN_SECONDS);
            wp_send_json_success(__('Build uložen lokálně', 'rpg-skill-trees'));
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
                return ['valid' => false, 'message' => __('Neznámá schopnost v buildu.', 'rpg-skill-trees')];
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
                return ['valid' => false, 'message' => __('Neznámá schopnost v buildu.', 'rpg-skill-trees')];
            }
            $skill = $skill_map[$skill_instance];
            if (!in_array($skill['tree'], $selected_trees, true)) {
                return ['valid' => false, 'message' => __('Strom schopností není vybrán.', 'rpg-skill-trees')];
            }
            if (!$this->prerequisites_met_server($skill['prereqs'], $selected_skills, $skill['tree'])) {
                return ['valid' => false, 'message' => __('Chybí požadované schopnosti.', 'rpg-skill-trees')];
            }
            if (!$this->tier_requirement_met_server($skill, $tree_spent)) {
                return ['valid' => false, 'message' => __('Nesplněný požadavek úrovně.', 'rpg-skill-trees')];
            }
            if (!$this->has_points_for_skill_server($skill, $totals, $spent, $settings)) {
                return ['valid' => false, 'message' => __('Nedostatek bodů.', 'rpg-skill-trees')];
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
            'font_sizes' => [
                'title' => 14,
                'tooltip' => 12,
                'requirements' => 12,
            ],
            'header_font_sizes' => [
                'title' => 18,
                'label' => 13,
                'message' => 13,
            ],
            'colors' => [
                'layout_bg' => '#0b1021',
                'layout_border' => '#1f2937',
                'layout_text' => '#e5e7eb',
                'tier_bg' => '#111827',
                'tier_border' => '#1f2937',
                'tier_title' => '#93c5fd',
                'button_bg' => '#1f2937',
                'button_text' => '#f9fafb',
                'button_border' => '#374151',
                'button_hover' => '#2563eb',
                'skill_bg' => '#1f2937',
                'skill_border' => '#374151',
                'skill_text' => '#f9fafb',
                'skill_tooltip_bg' => '#111827',
                'skill_tooltip' => '#cbd5e1',
                'skill_prereq' => '#fbbf24',
                'skill_selected_bg' => '#0f172a',
                'skill_selected_border' => '#60a5fa',
                'skill_hover_border' => '#60a5fa',
                'points_bg' => '#111827',
                'points_border' => '#1f2937',
                'points_text' => '#e5e7eb',
                'points_label' => '#cbd5f5',
                'messages' => '#fca5a5',
            ],
        ];
        $settings = get_option(self::OPTION_KEY, []);
        $settings = wp_parse_args($settings, $defaults);
        $settings['font_sizes'] = wp_parse_args($settings['font_sizes'], $defaults['font_sizes']);
        $settings['header_font_sizes'] = wp_parse_args($settings['header_font_sizes'], $defaults['header_font_sizes']);
        $settings['colors'] = wp_parse_args($settings['colors'], $defaults['colors']);
        return $settings;
    }

    public function add_skill_columns($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ('title' === $key) {
                $new['rpg_used_trees'] = __('Used in tree(s)', 'rpg-skill-trees');
            }
        }
        if (!isset($new['rpg_used_trees'])) {
            $new['rpg_used_trees'] = __('Used in tree(s)', 'rpg-skill-trees');
        }
        return $new;
    }

    public function render_skill_columns($column, $post_id) {
        if ('rpg_used_trees' !== $column) {
            return;
        }
        $trees = get_post_meta($post_id, '_rpg_trees', true);
        if (empty($trees)) {
            $legacy = get_post_meta($post_id, '_rpg_tree', true);
            $trees = $legacy ? [$legacy] : [];
        }
        if (empty($trees)) {
            echo '<em>' . esc_html__('Not assigned', 'rpg-skill-trees') . '</em>';
            return;
        }
        $names = [];
        foreach ((array) $trees as $tree_id) {
            $tree = get_post($tree_id);
            if ($tree && 'rpg_skill_tree' === $tree->post_type) {
                $names[] = $tree->post_title;
            }
        }
        if (empty($names)) {
            echo '<em>' . esc_html__('Not assigned', 'rpg-skill-trees') . '</em>';
            return;
        }
        echo esc_html(implode(', ', $names));
    }

    public function add_tree_columns($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ('title' === $key) {
                $new['rst_active'] = __('Active', 'rpg-skill-trees');
            }
        }
        if (!isset($new['rst_active'])) {
            $new['rst_active'] = __('Active', 'rpg-skill-trees');
        }
        return $new;
    }

    public function render_tree_columns($column, $post_id) {
        if ('rst_active' !== $column) {
            return;
        }
        $active = intval(get_post_meta($post_id, 'rst_active', true));
        echo $active === 0
            ? '<span class="rst-status rst-status--inactive">' . esc_html__('Inactive', 'rpg-skill-trees') . '</span>'
            : '<span class="rst-status rst-status--active">' . esc_html__('Active', 'rpg-skill-trees') . '</span>';
    }
}
