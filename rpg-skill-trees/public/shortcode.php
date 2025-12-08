<?php
$settings = (new RPG_Skill_Trees())->get_settings();
?>
<div class="rpg-skill-trees-builder" data-nonce="<?php echo esc_attr(wp_create_nonce('rpg_skill_trees_nonce')); ?>">
    <div class="rpg-reset-container">
        <button class="rpg-reset-build button"><?php esc_html_e('Reset', 'rpg-skill-trees'); ?></button>
    </div>
    <div class="rpg-builder-header">
        <div class="rpg-tree-selector">
            <h3><?php esc_html_e('Vyber si strom povolání', 'rpg-skill-trees'); ?></h3>
            <div class="rpg-tree-list" id="rpg-tree-list"></div>
        </div>
        <div class="rpg-point-summary" id="rpg-point-summary"></div>
        <div class="rpg-builder-messages" id="rpg-builder-messages"></div>
    </div>
    <div class="rpg-builder-body" id="rpg-builder-body"></div>
    <svg class="rpg-prereq-lines" id="rpg-prereq-lines"></svg>
</div>
