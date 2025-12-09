<?php
$settings = isset($settings) ? $settings : [];
?>
<div class="wrap">
    <h1><?php esc_html_e('RPG Skill Trees - Global Settings', 'rpg-skill-trees'); ?></h1>
    <form method="post">
        <?php wp_nonce_field('rpg_skill_trees_settings', 'rpg_skill_trees_settings_nonce'); ?>
        <h2><?php esc_html_e('Tier Points Defaults', 'rpg-skill-trees'); ?></h2>
        <table class="form-table">
            <tbody>
                <?php for ($i = 1; $i <= 4; $i++) : ?>
                    <tr>
                        <th scope="row"><?php printf(esc_html__('Tier %d points', 'rpg-skill-trees'), $i); ?></th>
                        <td><input type="number" step="0.1" name="tier_points[<?php echo esc_attr($i); ?>]" value="<?php echo esc_attr($settings['tier_points'][$i] ?? 0); ?>" /></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <h2><?php esc_html_e('Tier Conversion Table', 'rpg-skill-trees'); ?></h2>
        <table class="form-table" id="rpg-conversion-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('From Tier', 'rpg-skill-trees'); ?></th>
                    <th><?php esc_html_e('To Tier', 'rpg-skill-trees'); ?></th>
                    <th><?php esc_html_e('Ratio', 'rpg-skill-trees'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($settings['conversions'])) : foreach ($settings['conversions'] as $conv) : ?>
                    <tr>
                        <td><input type="number" min="1" max="4" name="conversion_from[]" value="<?php echo esc_attr($conv['from']); ?>" /></td>
                        <td><input type="number" min="1" max="4" name="conversion_to[]" value="<?php echo esc_attr($conv['to']); ?>" /></td>
                        <td><input type="number" step="0.01" name="conversion_ratio[]" value="<?php echo esc_attr($conv['ratio']); ?>" /></td>
                        <td><button class="button rpg-remove-row" type="button">&times;</button></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr>
                        <td><input type="number" min="1" max="4" name="conversion_from[]" value="1" /></td>
                        <td><input type="number" min="1" max="4" name="conversion_to[]" value="2" /></td>
                        <td><input type="number" step="0.01" name="conversion_ratio[]" value="1" /></td>
                        <td><button class="button rpg-remove-row" type="button">&times;</button></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <p><button class="button" id="rpg-add-conversion" type="button"><?php esc_html_e('Add conversion', 'rpg-skill-trees'); ?></button></p>

        <h2><?php esc_html_e('General Options', 'rpg-skill-trees'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Require login to save builds?', 'rpg-skill-trees'); ?></th>
                    <td><label><input type="checkbox" name="require_login" <?php checked($settings['require_login'], 1); ?> /> <?php esc_html_e('Yes', 'rpg-skill-trees'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Allow multiple saved builds per user?', 'rpg-skill-trees'); ?></th>
                    <td><label><input type="checkbox" name="allow_multiple_builds" <?php checked($settings['allow_multiple_builds'], 1); ?> /> <?php esc_html_e('Yes', 'rpg-skill-trees'); ?></label></td>
                </tr>
            </tbody>
        </table>

        <h2><?php esc_html_e('Skill Card Typography', 'rpg-skill-trees'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Skill title font size (px)', 'rpg-skill-trees'); ?></th>
                    <td><input type="number" min="8" step="0.5" name="font_sizes[title]" value="<?php echo esc_attr($settings['font_sizes']['title'] ?? 14); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Tooltip and effect font size (px)', 'rpg-skill-trees'); ?></th>
                    <td><input type="number" min="8" step="0.5" name="font_sizes[tooltip]" value="<?php echo esc_attr($settings['font_sizes']['tooltip'] ?? 12); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Requirements font size (px)', 'rpg-skill-trees'); ?></th>
                    <td><input type="number" min="8" step="0.5" name="font_sizes[requirements]" value="<?php echo esc_attr($settings['font_sizes']['requirements'] ?? 12); ?>" /></td>
                </tr>
            </tbody>
        </table>

        <h2><?php esc_html_e('Color Settings', 'rpg-skill-trees'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th colspan="2"><h3><?php esc_html_e('Layout & Panels', 'rpg-skill-trees'); ?></h3></th>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Plugin background', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[layout_bg]" value="<?php echo esc_attr($settings['colors']['layout_bg'] ?? '#0b1021'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Plugin border', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[layout_border]" value="<?php echo esc_attr($settings['colors']['layout_border'] ?? '#1f2937'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Base text', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[layout_text]" value="<?php echo esc_attr($settings['colors']['layout_text'] ?? '#e5e7eb'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Tier background', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[tier_bg]" value="<?php echo esc_attr($settings['colors']['tier_bg'] ?? '#111827'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Tier border', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[tier_border]" value="<?php echo esc_attr($settings['colors']['tier_border'] ?? '#1f2937'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Tier heading text', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[tier_title]" value="<?php echo esc_attr($settings['colors']['tier_title'] ?? '#93c5fd'); ?>" /></td>
                </tr>

                <tr>
                    <th colspan="2"><h3><?php esc_html_e('Skill Card', 'rpg-skill-trees'); ?></h3></th>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Skill card background', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[skill_bg]" value="<?php echo esc_attr($settings['colors']['skill_bg'] ?? '#1f2937'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Skill card border', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[skill_border]" value="<?php echo esc_attr($settings['colors']['skill_border'] ?? '#374151'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Skill card text', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[skill_text]" value="<?php echo esc_attr($settings['colors']['skill_text'] ?? '#f9fafb'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Tooltip background', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[skill_tooltip_bg]" value="<?php echo esc_attr($settings['colors']['skill_tooltip_bg'] ?? '#111827'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Tooltip text', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[skill_tooltip]" value="<?php echo esc_attr($settings['colors']['skill_tooltip'] ?? '#cbd5e1'); ?>" /></td>
                </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Prerequisite text', 'rpg-skill-trees'); ?></th>
                        <td><input type="color" name="colors[skill_prereq]" value="<?php echo esc_attr($settings['colors']['skill_prereq'] ?? '#fbbf24'); ?>" /></td>
                    </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Selected card background', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[skill_selected_bg]" value="<?php echo esc_attr($settings['colors']['skill_selected_bg'] ?? '#0f172a'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Selected/hover borders', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[skill_selected_border]" value="<?php echo esc_attr($settings['colors']['skill_selected_border'] ?? '#60a5fa'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Hover border', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[skill_hover_border]" value="<?php echo esc_attr($settings['colors']['skill_hover_border'] ?? '#60a5fa'); ?>" /></td>
                </tr>

                <tr>
                    <th colspan="2"><h3><?php esc_html_e('Buttons', 'rpg-skill-trees'); ?></h3></th>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Button background', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[button_bg]" value="<?php echo esc_attr($settings['colors']['button_bg'] ?? '#1f2937'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Button hover/primary background', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[button_hover]" value="<?php echo esc_attr($settings['colors']['button_hover'] ?? '#2563eb'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Button text', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[button_text]" value="<?php echo esc_attr($settings['colors']['button_text'] ?? '#f9fafb'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Button border', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[button_border]" value="<?php echo esc_attr($settings['colors']['button_border'] ?? '#374151'); ?>" /></td>
                </tr>

                <tr>
                    <th colspan="2"><h3><?php esc_html_e('Point Calculator & Messages', 'rpg-skill-trees'); ?></h3></th>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Point calculator background', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[points_bg]" value="<?php echo esc_attr($settings['colors']['points_bg'] ?? '#111827'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Point calculator border', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[points_border]" value="<?php echo esc_attr($settings['colors']['points_border'] ?? '#1f2937'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Point calculator text', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[points_text]" value="<?php echo esc_attr($settings['colors']['points_text'] ?? '#e5e7eb'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Point calculator labels', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[points_label]" value="<?php echo esc_attr($settings['colors']['points_label'] ?? '#cbd5f5'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Validation/message text', 'rpg-skill-trees'); ?></th>
                    <td><input type="color" name="colors[messages]" value="<?php echo esc_attr($settings['colors']['messages'] ?? '#fca5a5'); ?>" /></td>
                </tr>
            </tbody>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
