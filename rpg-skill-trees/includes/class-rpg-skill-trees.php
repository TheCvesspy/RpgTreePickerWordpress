<?php
if (!defined('ABSPATH')) {
    exit;
}

class Rpg_Skill_Trees {
    const OPTION_KEY = 'rpg_skill_trees_settings';

    /** @var Rpg_Skill_Trees_Admin */
    public $admin;

    /** @var Rpg_Skill_Trees_Public */
    public $public;

    public function __construct() {
        $this->define_constants();
        require_once plugin_dir_path(__FILE__) . '../admin/class-rpg-skill-trees-admin.php';
        require_once plugin_dir_path(__FILE__) . '../public/class-rpg-skill-trees-public.php';
    }

    private function define_constants() {
        if (!defined('RPG_SKILL_TREES_VERSION')) {
            define('RPG_SKILL_TREES_VERSION', '1.0.0');
        }
        if (!defined('RPG_SKILL_TREES_PATH')) {
            define('RPG_SKILL_TREES_PATH', plugin_dir_path(__DIR__));
        }
        if (!defined('RPG_SKILL_TREES_URL')) {
            define('RPG_SKILL_TREES_URL', plugins_url('/', dirname(__FILE__)) . '');
        }
    }

    public function run() {
        add_action('init', [$this, 'register_post_types']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'maybe_boot_components'], 11);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_rst_convert_points', [$this, 'ajax_convert_points']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('rpg-skill-trees', false, dirname(plugin_basename(__DIR__)) . '/languages');
    }

    public function register_post_types() {
        register_post_type('rpg_tree', [
            'labels' => [
                'name' => __('Skill Trees', 'rpg-skill-trees'),
                'singular_name' => __('Skill Tree', 'rpg-skill-trees'),
            ],
            'public' => false,
            'show_ui' => false,
            'supports' => ['title', 'editor'],
        ]);

        register_post_type('rpg_skill', [
            'labels' => [
                'name' => __('Skills', 'rpg-skill-trees'),
                'singular_name' => __('Skill', 'rpg-skill-trees'),
            ],
            'public' => false,
            'show_ui' => false,
            'supports' => ['title', 'editor'],
        ]);
    }

    public function register_settings() {
        register_setting('rpg_skill_trees_settings', self::OPTION_KEY, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($input) {
        $sanitized = [
            'tier_points' => [],
            'conversions' => [],
            'require_login' => !empty($input['require_login']) ? 1 : 0,
            'allow_multiple_builds' => !empty($input['allow_multiple_builds']) ? 1 : 0,
        ];

        for ($i = 1; $i <= 4; $i++) {
            $sanitized['tier_points'][$i] = isset($input['tier_points'][$i]) ? floatval($input['tier_points'][$i]) : 0;
        }

        if (!empty($input['conversions']) && is_array($input['conversions'])) {
            foreach ($input['conversions'] as $conversion) {
                $sanitized['conversions'][] = [
                    'from' => intval($conversion['from']),
                    'to' => intval($conversion['to']),
                    'ratio' => floatval($conversion['ratio']),
                ];
            }
        }

        return $sanitized;
    }

    public static function get_settings() {
        $defaults = [
            'tier_points' => [1 => 5, 2 => 3, 3 => 2, 4 => 1],
            'conversions' => [
                ['from' => 3, 'to' => 2, 'ratio' => 2],
                ['from' => 2, 'to' => 3, 'ratio' => 0.5],
            ],
            'require_login' => 0,
            'allow_multiple_builds' => 0,
        ];
        $options = get_option(self::OPTION_KEY, []);
        return wp_parse_args($options, $defaults);
    }

    public static function convert_points($amount, $from, $to) {
        if ($from === $to) {
            return $amount;
        }
        $settings = self::get_settings();
        if (empty($settings['conversions'])) {
            return 0;
        }
        foreach ($settings['conversions'] as $rule) {
            if (intval($rule['from']) === intval($from) && intval($rule['to']) === intval($to)) {
                return round($amount * floatval($rule['ratio']), 2);
            }
        }
        return 0;
    }

    public function ajax_convert_points() {
        check_ajax_referer('rst_public_nonce', 'nonce');
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $from = isset($_POST['from']) ? intval($_POST['from']) : 1;
        $to = isset($_POST['to']) ? intval($_POST['to']) : 1;
        wp_send_json_success(['result' => self::convert_points($amount, $from, $to)]);
    }

    public function maybe_boot_components() {
        if (is_admin()) {
            $this->admin = new Rpg_Skill_Trees_Admin($this);
            $this->admin->init();
        }
        $this->public = new Rpg_Skill_Trees_Public($this);
        $this->public->init();
    }
}
