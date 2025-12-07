# RPG Skill Trees

A WordPress plugin that lets administrators define RPG-style skill trees with tiers, prerequisites, tier conversion rules, and tier investment gates. Site visitors can select multiple trees (multiclass), spend points, view prerequisite links, and save or reload builds.

## Installation
1. Copy the `rpg-skill-trees` folder into your WordPress `wp-content/plugins/` directory.
2. Activate **RPG Skill Trees** in the Plugins screen.

## Data model & assumptions
- **Storage** uses two hidden custom post types: `rpg_tree` (skill trees/classes) and `rpg_skill` (skills). Post meta stores icons, colors, tier requirements per tree, tier costs, prerequisites, and per-skill minimum previous-tier points.
- **Global settings** (`rpg_skill_trees_settings` option) hold tier point defaults, tier conversion rules, and build saving preferences.
- **Builds** are saved as user meta `rst_builds` (latest only unless multiple builds are allowed). Guests receive a message instructing them that builds stay in browser storage.
- **Tier limits** are **global per tier** across all selected trees. Conversions can move value across tiers using the provided ratios.
- **Prerequisites** are enforced within the same tree; cross-tree prerequisites are allowed but not validated visually beyond the ID reference.
- **Conversion rounding**: conversions multiply by the configured ratio and are rounded to **2 decimal places** in PHP. JS uses raw floats; spending converts donor tiers and deducts the donor share required to cover the deficit.
- **Tier gates**: unlocking higher tiers requires the configured points spent in the previous tier **for that tree**. Per-skill `Minimum points in previous tier` is also enforced.

## Creating trees and skills
1. In the dashboard, open **RPG Skill Trees → Skill Trees** to add a tree with description, icon URL, color, and per-tier unlock requirements.
2. Go to **RPG Skill Trees → Skills** to add skills. Choose the tree, tier (1–4), cost, icon, description, optional minimum points in previous tier, and skill prerequisites (multi-select).
3. Set **Global Settings** for tier point defaults, conversion rules (from tier, to tier, ratio), and build-saving options.

## Embedding on a page
Use the shortcode:

```
[rpg_skill_trees]
```

Place it in any page/post or shortcode block. The plugin only enqueues its frontend assets when the shortcode is rendered.

## Frontend behavior
- Users choose one or more trees (multiclass) and see skills grouped by tier (columns). Skill prerequisites are drawn as connector lines on an SVG overlay.
- Selecting a skill checks: tree selected, prerequisites chosen, tree tier gates, per-skill previous-tier requirement, and available tier points (with conversions). Failure shows a feedback message.
- Points panel shows spent/available per tier.
- Buttons: **Save build**, **Load build**, **Reset**. Saving/loading uses AJAX with user meta for logged-in users; guests get guidance to rely on the browser.

## Shortcode scripts & styles
- `assets/js/public.js` handles selection, validation, conversions, SVG prerequisite lines, and AJAX saves/loads.
- `assets/css/public.css` styles the builder layout, tiers, and prerequisite lines.
