# RPG Skill Trees

A WordPress plugin that lets administrators define RPG-style skill trees with tiers, prerequisites, and conversion rules, and lets site visitors build multiclass skill selections via shortcode.

## Installation
1. Copy the `rpg-skill-trees` folder into your WordPress installation under `wp-content/plugins/`.
2. Activate **RPG Skill Trees** from the WordPress Plugins screen.

## Data Model
- **Skill Trees**: Custom post type `rpg_skill_tree`. Uses post title/content plus meta:
  - `_rpg_icon` (string URL)
  - `_rpg_tier_requirements` (array keyed by tier for required previous-tier points)
- **Skills**: Custom post type `rpg_skill`. Meta fields:
  - `_rpg_tree` (int tree ID)
  - `_rpg_tier` (1–4)
  - `_rpg_cost` (float cost in the skill tier)
  - `_rpg_icon` (string URL)
  - `_rpg_tooltip` (HTML-safe description)
  - `_rpg_prereqs` (array of skill IDs as mandatory prerequisites)
- **Global settings** (option `rpg_skill_trees_settings`):
  - `tier_points` per tier (default Tier1=5, Tier2=3, Tier3=2, Tier4=1) used as global caps across all selected trees.
  - `conversions`: array of conversion rules `{from, to, ratio}` enabling `convert_points(X, from, to) = X * ratio`. Fractional results are used directly (no rounding) so higher-tier to lower-tier or vice-versa conversions remain proportional.
  - `require_login`, `allow_multiple_builds` flags.
- **Builds**: Stored in user meta key `rpg_skill_trees_builds` (single or multiple builds depending on the option). Guests use a transient keyed by IP for short-term storage.

## Assumptions
- Global tier point pools are shared across all selected trees (multiclass builds draw from the same tier totals).
- Prerequisites are enforced within the same tree in the UI, but you can create cross-tree dependencies; the frontend will respect any listed prerequisite IDs.
- Conversion ratios are directional. If no rule is defined for a conversion, no effective points are gained from that tier.
- Fractional points are allowed for both costs and conversions.
- Tooltip text is stored as HTML but sanitized via `wp_kses_post` on save.
- Guest builds persist for one hour via transient keyed by the visitor IP.

## Usage
1. In the admin menu **RPG Skill Trees**:
   - **Skill Trees**: create trees, set icon and tier investment requirements.
   - **Skills**: assign each skill to a tree, tier, cost, icon, tooltip, and pick prerequisites.
   - **Global Settings**: set default tier points, conversion ratios, and build saving rules.
2. Create or edit a page and add the shortcode `[rpg_skill_trees]`.
3. On the frontend:
   - Select one or more trees to multiclass.
   - Click skills to toggle them. Skills are disabled until tier requirements, point costs (with conversions), and prerequisites are met.
   - Lines between skills visualize prerequisite links.
   - Logged-in users can save/load builds. Guests can reset or rely on temporary transient storage.

## Rounding Strategy
`convert_points` uses straight multiplication by the admin-defined ratio without rounding; fractional results count fully toward availability.

## Security Notes
- Capability checks rely on `manage_options` for admin screens.
- Nonces are used on admin forms and AJAX build actions.
- Inputs are sanitized/escaped on save and output.

## Assets
- `assets/js/public.js` handles frontend selection logic, validation, saving/loading, and drawing SVG lines between prerequisites.
- `assets/css/public.css` provides basic layout and responsive tier columns.

