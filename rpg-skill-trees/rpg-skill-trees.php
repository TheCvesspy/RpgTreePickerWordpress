<?php
/**
 * Plugin Name: RPG Skill Trees
 * Description: Create RPG-style skill trees with tier rules, prerequisites, and build saving.
 * Version: 1.7.8
 * Author: Quill
 * Text Domain: rpg-skill-trees
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-rpg-skill-trees.php';

function rpg_skill_trees_run() {
    $plugin = new RPG_Skill_Trees();
    $plugin->run();
}

rpg_skill_trees_run();
