<?php

/**
 * Plugin Name: BE.AM Core
 * Description: Инфраструктурные модули BE.AM для WordPress: SMTP, безопасность, фавиконка, поля, SVG и другие.
 * Version: 2.0.0
 * Author: BE.AM
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 8.1
 * Requires at least: 6.4
 */

defined('ABSPATH') || exit;

if (defined('BEAM_CORE_LOADED')) {
    return;
}

define('BEAM_CORE_LOADED', true);
define('BEAM_CORE_VERSION', '2.0.0');
define('BEAM_CORE_FILE', __FILE__);
define('BEAM_CORE_DIR', __DIR__);
define('BEAM_CORE_MU_DIR', dirname(__DIR__));

require_once BEAM_CORE_DIR . '/Core/ModuleRegistry.php';
require_once BEAM_CORE_DIR . '/Core/Fields.php';
require_once BEAM_CORE_DIR . '/Core/Plugin.php';
require_once BEAM_CORE_DIR . '/Core/Settings/SettingsPage.php';
require_once BEAM_CORE_DIR . '/Core/DuplicateNotice.php';
require_once BEAM_CORE_DIR . '/Modules/Smtp/Module.php';
require_once BEAM_CORE_DIR . '/Modules/AdminBranding/Module.php';
require_once BEAM_CORE_DIR . '/Modules/DisableEmoji/Module.php';
require_once BEAM_CORE_DIR . '/Modules/DisableComments/Module.php';
require_once BEAM_CORE_DIR . '/Modules/AcfJsonSync/Module.php';
require_once BEAM_CORE_DIR . '/Modules/Breadcrumbs/Module.php';
require_once BEAM_CORE_DIR . '/Modules/SvgUpload/Module.php';
require_once BEAM_CORE_DIR . '/Modules/Favicon/Module.php';
require_once BEAM_CORE_DIR . '/Modules/Security/Module.php';

Beam\Core\Modules\Security\Module::bootstrap();
Beam\Core\Fields::register();

add_action('after_setup_theme', [Beam\Core\Plugin::class, 'register'], 0);
