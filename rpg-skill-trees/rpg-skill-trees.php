<?php
/**
 * Plugin Name: RPG Skill Trees
 * Description: Manage RPG-style skill trees with tiers, prerequisites, conversions, and a frontend builder.
 * Version: 1.0.0
 * Author: OpenAI Codex
 * Text Domain: rpg-skill-trees
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-rpg-skill-trees.php';

function rpg_skill_trees_run() {
    $plugin = new Rpg_Skill_Trees();
    $plugin->run();
}

rpg_skill_trees_run();
