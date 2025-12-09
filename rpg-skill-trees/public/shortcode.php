<?php
$settings = (new RPG_Skill_Trees())->get_settings();
?>
<div class="rpg-skill-trees-builder" data-nonce="<?php echo esc_attr(wp_create_nonce('rpg_skill_trees_nonce')); ?>">
    <div class="rpg-builder-header">
        <div class="rpg-header-top">
            <h3 class="rpg-section-title"><?php esc_html_e('Vyber si strom povolání', 'rpg-skill-trees'); ?></h3>
            <div class="rpg-header-actions">
                <div class="rpg-reset-container">
                    <button class="rpg-reset-build button" type="button"><?php esc_html_e('Reset', 'rpg-skill-trees'); ?></button>
                    <button class="button rpg-export-png" type="button"><?php esc_html_e('Exportovat jako PNG', 'rpg-skill-trees'); ?></button>
                </div>
                <div class="rpg-builder-messages" id="rpg-builder-messages"></div>
            </div>
        </div>
        <div class="rpg-header-content">
            <div class="rpg-tree-selector">
                <div class="rpg-tree-list" id="rpg-tree-list"></div>
            </div>
            <div class="rpg-point-summary" id="rpg-point-summary"></div>
            <div class="rpg-builder-options">
                <label class="rpg-rules-toggle">
                    <input type="checkbox" id="rpg-toggle-rules" /> <?php esc_html_e('zobrazuj pravidla schopností', 'rpg-skill-trees'); ?>
                </label>
            </div>
        </div>
    </div>
    <div class="rpg-builder-body" id="rpg-builder-body"></div>
    <svg class="rpg-prereq-lines" id="rpg-prereq-lines"></svg>
</div>
