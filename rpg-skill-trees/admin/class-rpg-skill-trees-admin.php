<?php
if (!defined('ABSPATH')) {
    exit;
}

class Rpg_Skill_Trees_Admin {
    private $plugin;

    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    public function init() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_rst_save_tree', [$this, 'handle_save_tree']);
        add_action('admin_post_rst_delete_tree', [$this, 'handle_delete_tree']);
        add_action('admin_post_rst_save_skill', [$this, 'handle_save_skill']);
        add_action('admin_post_rst_delete_skill', [$this, 'handle_delete_skill']);
        add_action('admin_post_rst_save_settings', [$this, 'handle_save_settings']);
    }

    public function register_menu() {
        add_menu_page(
            __('RPG Skill Trees', 'rpg-skill-trees'),
            __('RPG Skill Trees', 'rpg-skill-trees'),
            'manage_options',
            'rpg-skill-trees',
            [$this, 'render_trees_page'],
            'dashicons-networking'
        );

        add_submenu_page('rpg-skill-trees', __('Skill Trees', 'rpg-skill-trees'), __('Skill Trees', 'rpg-skill-trees'), 'manage_options', 'rpg-skill-trees', [$this, 'render_trees_page']);
        add_submenu_page('rpg-skill-trees', __('Skills', 'rpg-skill-trees'), __('Skills', 'rpg-skill-trees'), 'manage_options', 'rpg-skill-trees-skills', [$this, 'render_skills_page']);
        add_submenu_page('rpg-skill-trees', __('Global Settings', 'rpg-skill-trees'), __('Global Settings', 'rpg-skill-trees'), 'manage_options', 'rpg-skill-trees-settings', [$this, 'render_settings_page']);
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'rpg-skill-trees') === false) {
            return;
        }
        wp_enqueue_style('rpg-skill-trees-admin', plugins_url('../assets/css/admin.css', __FILE__), [], RPG_SKILL_TREES_VERSION);
        wp_enqueue_script('rpg-skill-trees-admin', plugins_url('../assets/js/admin.js', __FILE__), ['jquery'], RPG_SKILL_TREES_VERSION, true);
    }

    private function get_trees() {
        return get_posts([
            'post_type' => 'rpg_tree',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
    }

    private function get_skills() {
        return get_posts([
            'post_type' => 'rpg_skill',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
    }

    public function render_trees_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $editing = isset($_GET['tree_id']) ? intval($_GET['tree_id']) : 0;
        $tree = $editing ? get_post($editing) : null;
        $tier_rules = $editing ? get_post_meta($editing, 'rst_tier_requirements', true) : [];
        $icon = $editing ? get_post_meta($editing, 'rst_icon', true) : '';
        $color = $editing ? get_post_meta($editing, 'rst_color', true) : '';
        $trees = $this->get_trees();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Skill Trees', 'rpg-skill-trees'); ?></h1>
            <div class="rst-columns">
                <div class="rst-column">
                    <h2><?php echo $editing ? esc_html__('Edit Tree', 'rpg-skill-trees') : esc_html__('Add New Tree', 'rpg-skill-trees'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('rst_save_tree'); ?>
                        <input type="hidden" name="action" value="rst_save_tree" />
                        <input type="hidden" name="tree_id" value="<?php echo esc_attr($editing); ?>" />
                        <table class="form-table">
                            <tr>
                                <th><label for="rst_tree_name"><?php esc_html_e('Name', 'rpg-skill-trees'); ?></label></th>
                                <td><input type="text" class="regular-text" name="name" id="rst_tree_name" value="<?php echo $tree ? esc_attr($tree->post_title) : ''; ?>" required /></td>
                            </tr>
                            <tr>
                                <th><label for="rst_tree_description"><?php esc_html_e('Description', 'rpg-skill-trees'); ?></label></th>
                                <td><textarea name="description" id="rst_tree_description" rows="4" class="large-text"><?php echo $tree ? esc_textarea($tree->post_content) : ''; ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="rst_tree_icon"><?php esc_html_e('Icon URL', 'rpg-skill-trees'); ?></label></th>
                                <td><input type="url" name="icon" id="rst_tree_icon" class="regular-text" value="<?php echo esc_attr($icon); ?>" /></td>
                            </tr>
                            <tr>
                                <th><label for="rst_tree_color"><?php esc_html_e('Color/Theme', 'rpg-skill-trees'); ?></label></th>
                                <td><input type="text" name="color" id="rst_tree_color" class="regular-text" value="<?php echo esc_attr($color); ?>" placeholder="#4287f5" /></td>
                            </tr>
                            <?php for ($i = 2; $i <= 4; $i++): ?>
                            <tr>
                                <th><label for="rst_rule_<?php echo $i; ?>"><?php printf(esc_html__('Minimum Tier %1$d points before Tier %2$d unlocks', 'rpg-skill-trees'), $i-1, $i); ?></label></th>
                                <td><input type="number" step="0.1" name="tier_rules[<?php echo $i; ?>]" id="rst_rule_<?php echo $i; ?>" value="<?php echo isset($tier_rules[$i]) ? esc_attr($tier_rules[$i]) : 0; ?>" /></td>
                            </tr>
                            <?php endfor; ?>
                        </table>
                        <?php submit_button($editing ? __('Update Tree', 'rpg-skill-trees') : __('Create Tree', 'rpg-skill-trees')); ?>
                    </form>
                </div>
                <div class="rst-column">
                    <h2><?php esc_html_e('Existing Trees', 'rpg-skill-trees'); ?></h2>
                    <table class="widefat">
                        <thead><tr><th><?php esc_html_e('Name', 'rpg-skill-trees'); ?></th><th><?php esc_html_e('Skills', 'rpg-skill-trees'); ?></th><th><?php esc_html_e('Actions', 'rpg-skill-trees'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($trees as $t): ?>
                            <?php $skill_count = count(get_posts(['post_type' => 'rpg_skill', 'numberposts' => -1, 'meta_key' => 'rst_tree_id', 'meta_value' => $t->ID])); ?>
                            <tr>
                                <td><?php echo esc_html($t->post_title); ?></td>
                                <td><?php echo intval($skill_count); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'rpg-skill-trees', 'tree_id' => $t->ID], admin_url('admin.php'))); ?>" class="button">Edit</a>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                        <?php wp_nonce_field('rst_delete_tree'); ?>
                                        <input type="hidden" name="action" value="rst_delete_tree" />
                                        <input type="hidden" name="tree_id" value="<?php echo esc_attr($t->ID); ?>" />
                                        <button type="submit" class="button button-link-delete" onclick="return confirm('Delete this tree?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_skills_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $editing = isset($_GET['skill_id']) ? intval($_GET['skill_id']) : 0;
        $skill = $editing ? get_post($editing) : null;
        $trees = $this->get_trees();
        $skills = $this->get_skills();
        $meta = $editing ? get_post_meta($editing) : [];
        $tree_id = $editing ? intval($meta['rst_tree_id'][0] ?? 0) : 0;
        $tier = $editing ? intval($meta['rst_tier'][0] ?? 1) : 1;
        $icon = $editing ? esc_url($meta['rst_icon'][0] ?? '') : '';
        $cost = $editing ? floatval($meta['rst_cost'][0] ?? 0) : 0;
        $description = $editing ? ($skill ? $skill->post_content : '') : '';
        $prereqs = $editing ? (array) maybe_unserialize($meta['rst_prereq_skills'][0] ?? []) : [];
        $min_points = $editing ? floatval($meta['rst_min_previous'][0] ?? 0) : 0;
        $sort_order = $editing ? intval($meta['rst_sort_order'][0] ?? 0) : 0;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Skills', 'rpg-skill-trees'); ?></h1>
            <div class="rst-columns">
                <div class="rst-column">
                    <h2><?php echo $editing ? esc_html__('Edit Skill', 'rpg-skill-trees') : esc_html__('Add New Skill', 'rpg-skill-trees'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('rst_save_skill'); ?>
                        <input type="hidden" name="action" value="rst_save_skill" />
                        <input type="hidden" name="skill_id" value="<?php echo esc_attr($editing); ?>" />
                        <table class="form-table">
                            <tr>
                                <th><label for="rst_skill_name"><?php esc_html_e('Name', 'rpg-skill-trees'); ?></label></th>
                                <td><input type="text" class="regular-text" name="name" id="rst_skill_name" value="<?php echo $skill ? esc_attr($skill->post_title) : ''; ?>" required /></td>
                            </tr>
                            <tr>
                                <th><label for="rst_skill_tree"><?php esc_html_e('Tree', 'rpg-skill-trees'); ?></label></th>
                                <td>
                                    <select name="tree_id" id="rst_skill_tree" required>
                                        <option value=""><?php esc_html_e('Select tree', 'rpg-skill-trees'); ?></option>
                                        <?php foreach ($trees as $t): ?>
                                            <option value="<?php echo esc_attr($t->ID); ?>" <?php selected($tree_id, $t->ID); ?>><?php echo esc_html($t->post_title); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="rst_skill_tier"><?php esc_html_e('Tier', 'rpg-skill-trees'); ?></label></th>
                                <td>
                                    <select name="tier" id="rst_skill_tier">
                                        <?php for ($i = 1; $i <= 4; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php selected($tier, $i); ?>><?php printf(__('Tier %d', 'rpg-skill-trees'), $i); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="rst_skill_cost"><?php esc_html_e('Tier point cost', 'rpg-skill-trees'); ?></label></th>
                                <td><input type="number" step="0.1" name="cost" id="rst_skill_cost" value="<?php echo esc_attr($cost); ?>" required /></td>
                            </tr>
                            <tr>
                                <th><label for="rst_skill_sort_order"><?php esc_html_e('Sort Order', 'rpg-skill-trees'); ?></label></th>
                                <td>
                                    <input type="number" name="sort_order" id="rst_skill_sort_order" value="<?php echo esc_attr($sort_order); ?>" />
                                    <p class="description"><?php esc_html_e('Controls the ordering of skill cards in the user view.', 'rpg-skill-trees'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="rst_skill_icon"><?php esc_html_e('Icon URL', 'rpg-skill-trees'); ?></label></th>
                                <td><input type="url" name="icon" id="rst_skill_icon" class="regular-text" value="<?php echo esc_attr($icon); ?>" /></td>
                            </tr>
                            <tr>
                                <th><label for="rst_skill_description"><?php esc_html_e('Tooltip description', 'rpg-skill-trees'); ?></label></th>
                                <td><textarea name="description" id="rst_skill_description" rows="4" class="large-text"><?php echo esc_textarea($description); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="rst_skill_min_prev"><?php esc_html_e('Minimum points in previous tier', 'rpg-skill-trees'); ?></label></th>
                                <td><input type="number" step="0.1" name="min_prev" id="rst_skill_min_prev" value="<?php echo esc_attr($min_points); ?>" /></td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Skill prerequisites', 'rpg-skill-trees'); ?></label></th>
                                <td>
                                    <select name="prereq_skills[]" multiple size="6" aria-label="Prerequisite skills">
                                        <?php foreach ($skills as $s): ?>
                                            <option value="<?php echo esc_attr($s->ID); ?>" <?php selected(in_array($s->ID, $prereqs, false)); ?>><?php echo esc_html($s->post_title); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e('Hold CTRL/CMD to select multiple prerequisites. Typically select from the same tree.', 'rpg-skill-trees'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button($editing ? __('Update Skill', 'rpg-skill-trees') : __('Create Skill', 'rpg-skill-trees')); ?>
                    </form>
                </div>
                <div class="rst-column">
                    <h2><?php esc_html_e('Existing Skills', 'rpg-skill-trees'); ?></h2>
                    <table class="widefat">
                        <thead><tr><th><?php esc_html_e('Name', 'rpg-skill-trees'); ?></th><th><?php esc_html_e('Tree', 'rpg-skill-trees'); ?></th><th><?php esc_html_e('Tier', 'rpg-skill-trees'); ?></th><th><?php esc_html_e('Cost', 'rpg-skill-trees'); ?></th><th><?php esc_html_e('Prerequisites', 'rpg-skill-trees'); ?></th><th><?php esc_html_e('Actions', 'rpg-skill-trees'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($skills as $s): ?>
                            <?php $meta = get_post_meta($s->ID); $tree_id = intval($meta['rst_tree_id'][0] ?? 0); $tree = $tree_id ? get_post($tree_id) : null; $pr = (array) maybe_unserialize($meta['rst_prereq_skills'][0] ?? []); ?>
                            <tr>
                                <td><?php echo esc_html($s->post_title); ?></td>
                                <td><?php echo $tree ? esc_html($tree->post_title) : __('Unknown', 'rpg-skill-trees'); ?></td>
                                <td><?php echo intval($meta['rst_tier'][0] ?? 1); ?></td>
                                <td><?php echo esc_html($meta['rst_cost'][0] ?? ''); ?></td>
                                <td><?php echo $pr ? esc_html(implode(', ', array_map(function($id) { $p = get_post($id); return $p ? $p->post_title : ''; }, $pr))) : __('None', 'rpg-skill-trees'); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'rpg-skill-trees-skills', 'skill_id' => $s->ID], admin_url('admin.php'))); ?>" class="button">Edit</a>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                        <?php wp_nonce_field('rst_delete_skill'); ?>
                                        <input type="hidden" name="action" value="rst_delete_skill" />
                                        <input type="hidden" name="skill_id" value="<?php echo esc_attr($s->ID); ?>" />
                                        <button type="submit" class="button button-link-delete" onclick="return confirm('Delete this skill?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = Rpg_Skill_Trees::get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Global Settings', 'rpg-skill-trees'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('rst_save_settings'); ?>
                <input type="hidden" name="action" value="rst_save_settings" />
                <h2><?php esc_html_e('Tier Points Defaults', 'rpg-skill-trees'); ?></h2>
                <table class="form-table">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                    <tr>
                        <th><label for="rst_tier_points_<?php echo $i; ?>"><?php printf(esc_html__('Tier %d default points', 'rpg-skill-trees'), $i); ?></label></th>
                        <td><input type="number" step="0.1" name="settings[tier_points][<?php echo $i; ?>]" id="rst_tier_points_<?php echo $i; ?>" value="<?php echo esc_attr($settings['tier_points'][$i]); ?>" /></td>
                    </tr>
                    <?php endfor; ?>
                </table>
                <h2><?php esc_html_e('Tier Conversion Rules', 'rpg-skill-trees'); ?></h2>
                <div id="rst-conversions">
                    <?php if (!empty($settings['conversions'])): foreach ($settings['conversions'] as $idx => $rule): ?>
                        <div class="rst-conversion-row">
                            <label><?php esc_html_e('From', 'rpg-skill-trees'); ?> <input type="number" name="settings[conversions][<?php echo $idx; ?>][from]" value="<?php echo esc_attr($rule['from']); ?>" min="1" max="4" /></label>
                            <label><?php esc_html_e('To', 'rpg-skill-trees'); ?> <input type="number" name="settings[conversions][<?php echo $idx; ?>][to]" value="<?php echo esc_attr($rule['to']); ?>" min="1" max="4" /></label>
                            <label><?php esc_html_e('Ratio', 'rpg-skill-trees'); ?> <input type="number" step="0.01" name="settings[conversions][<?php echo $idx; ?>][ratio]" value="<?php echo esc_attr($rule['ratio']); ?>" /></label>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <p><button type="button" class="button" id="rst-add-conversion"><?php esc_html_e('Add Conversion', 'rpg-skill-trees'); ?></button></p>
                <h2><?php esc_html_e('General', 'rpg-skill-trees'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Require login to save builds?', 'rpg-skill-trees'); ?></th>
                        <td><label><input type="checkbox" name="settings[require_login]" value="1" <?php checked($settings['require_login'], 1); ?> /> <?php esc_html_e('Yes', 'rpg-skill-trees'); ?></label></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Allow multiple saved builds per user?', 'rpg-skill-trees'); ?></th>
                        <td><label><input type="checkbox" name="settings[allow_multiple_builds]" value="1" <?php checked($settings['allow_multiple_builds'], 1); ?> /> <?php esc_html_e('Yes', 'rpg-skill-trees'); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(__('Save Settings', 'rpg-skill-trees')); ?>
            </form>
        </div>
        <?php
    }

    public function handle_save_tree() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Not allowed', 'rpg-skill-trees'));
        }
        check_admin_referer('rst_save_tree');
        $tree_id = isset($_POST['tree_id']) ? intval($_POST['tree_id']) : 0;
        $data = [
            'post_title' => sanitize_text_field($_POST['name'] ?? ''),
            'post_content' => wp_kses_post($_POST['description'] ?? ''),
            'post_type' => 'rpg_tree',
            'post_status' => 'publish',
        ];
        if ($tree_id) {
            $data['ID'] = $tree_id;
            wp_update_post($data);
        } else {
            $tree_id = wp_insert_post($data);
        }
        $tier_rules = isset($_POST['tier_rules']) ? array_map('floatval', (array) $_POST['tier_rules']) : [];
        update_post_meta($tree_id, 'rst_tier_requirements', $tier_rules);
        update_post_meta($tree_id, 'rst_icon', esc_url_raw($_POST['icon'] ?? ''));
        update_post_meta($tree_id, 'rst_color', sanitize_text_field($_POST['color'] ?? ''));
        wp_safe_redirect(admin_url('admin.php?page=rpg-skill-trees&updated=1'));
        exit;
    }

    public function handle_delete_tree() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Not allowed', 'rpg-skill-trees'));
        }
        check_admin_referer('rst_delete_tree');
        $tree_id = intval($_POST['tree_id'] ?? 0);
        if ($tree_id) {
            wp_delete_post($tree_id, true);
        }
        wp_safe_redirect(admin_url('admin.php?page=rpg-skill-trees&deleted=1'));
        exit;
    }

    public function handle_save_skill() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Not allowed', 'rpg-skill-trees'));
        }
        check_admin_referer('rst_save_skill');
        $skill_id = isset($_POST['skill_id']) ? intval($_POST['skill_id']) : 0;
        $tree_id = intval($_POST['tree_id'] ?? 0);
        $tier = intval($_POST['tier'] ?? 1);
        $data = [
            'post_title' => sanitize_text_field($_POST['name'] ?? ''),
            'post_content' => wp_kses_post($_POST['description'] ?? ''),
            'post_type' => 'rpg_skill',
            'post_status' => 'publish',
        ];
        if ($skill_id) {
            $data['ID'] = $skill_id;
            wp_update_post($data);
        } else {
            $skill_id = wp_insert_post($data);
        }
        update_post_meta($skill_id, 'rst_tree_id', $tree_id);
        update_post_meta($skill_id, 'rst_tier', $tier);
        update_post_meta($skill_id, 'rst_icon', esc_url_raw($_POST['icon'] ?? ''));
        update_post_meta($skill_id, 'rst_cost', floatval($_POST['cost'] ?? 0));
        update_post_meta($skill_id, 'rst_min_previous', floatval($_POST['min_prev'] ?? 0));
        update_post_meta($skill_id, 'rst_sort_order', intval($_POST['sort_order'] ?? 0));
        $prereqs = isset($_POST['prereq_skills']) ? array_map('intval', (array) $_POST['prereq_skills']) : [];
        update_post_meta($skill_id, 'rst_prereq_skills', $prereqs);
        wp_safe_redirect(admin_url('admin.php?page=rpg-skill-trees-skills&updated=1'));
        exit;
    }

    public function handle_delete_skill() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Not allowed', 'rpg-skill-trees'));
        }
        check_admin_referer('rst_delete_skill');
        $skill_id = intval($_POST['skill_id'] ?? 0);
        if ($skill_id) {
            wp_delete_post($skill_id, true);
        }
        wp_safe_redirect(admin_url('admin.php?page=rpg-skill-trees-skills&deleted=1'));
        exit;
    }

    public function handle_save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Not allowed', 'rpg-skill-trees'));
        }
        check_admin_referer('rst_save_settings');
        $settings = $_POST['settings'] ?? [];
        update_option(Rpg_Skill_Trees::OPTION_KEY, $this->plugin->sanitize_settings($settings));
        wp_safe_redirect(admin_url('admin.php?page=rpg-skill-trees-settings&updated=1'));
        exit;
    }
}
