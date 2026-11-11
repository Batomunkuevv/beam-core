<?php

namespace Beam\Core\Settings;

use Beam\Core\ModuleRegistry;

defined('ABSPATH') || exit;

final class SettingsPage
{
    public const PAGE_SLUG = 'beam-core';

    public static function register(): void
    {
        add_action('admin_menu', [ self::class, 'add_menu' ]);
        add_action('admin_init', [ self::class, 'handle_modules_save' ]);
        add_action('admin_enqueue_scripts', [ self::class, 'enqueue_assets' ]);

        self::load_settings_classes();
        self::register_module_handlers();
    }

    public static function add_menu(): void
    {
        add_options_page(
            'BE.AM Core',
            'BE.AM Core',
            'manage_options',
            self::PAGE_SLUG,
            [ self::class, 'render' ],
        );
    }

    public static function enqueue_assets(string $hook): void
    {
        if ('settings_page_' . self::PAGE_SLUG !== $hook) {
            return;
        }

        wp_enqueue_style(
            'beam-core-settings',
            self::asset_url('css/settings.css'),
            [],
            self::asset_version('css/settings.css'),
        );
    }

    public static function handle_modules_save(): void
    {
        if (! isset($_POST['beam_core_modules_save'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('beam_core_modules');

        $enabled  = isset($_POST['module_enabled']) && is_array($_POST['module_enabled'])
            // Module keys are camelCase (adminBranding…); sanitize_key() lowercased them, so they never matched and
            // every camelCase module was saved as disabled. Keep the exact text and accept only known keys.
            ? array_values(
                array_intersect(
                    array_map('sanitize_text_field', wp_unslash($_POST['module_enabled'])),
                    array_keys(ModuleRegistry::definitions()),
                ),
            )
            : [];
        $disabled = [];

        foreach (array_keys(ModuleRegistry::definitions()) as $key) {
            if (ModuleRegistry::is_removed_by_filter($key)) {
                if (ModuleRegistry::is_disabled_by_option($key)) {
                    $disabled[] = $key;
                }

                continue;
            }

            if (! in_array($key, $enabled, true)) {
                $disabled[] = $key;
            }
        }

        ModuleRegistry::update_disabled_modules($disabled);

        add_settings_error(
            'beam_core',
            'modules_saved',
            'Состояние модулей сохранено.',
            'updated',
        );
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $tabs       = ModuleRegistry::tabs();
        $active_tab = self::active_tab();
        ?>
		<div class="wrap beam-core-settings">
			<h1><?= esc_html(get_admin_page_title()) ?></h1>

			<nav class="nav-tab-wrapper beam-core-settings__tabs" aria-label="Разделы BE.AM Core">
				<?php foreach ($tabs as $slug => $label) : ?>
					<a
						href="<?= esc_url(self::tab_url($slug)) ?>"
						class="nav-tab<?= $active_tab === $slug ? ' nav-tab-active' : '' ?>"
					><?= esc_html($label) ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="beam-core-settings__panel">
				<?php self::render_tab($active_tab); ?>
			</div>
		</div>
		<?php
    }

    private static function render_tab(string $tab): void
    {
        if ('modules' === $tab) {
            self::render_modules_tab();

            return;
        }

        $module_key = ModuleRegistry::module_key_by_tab($tab);

        if (null === $module_key) {
            self::render_modules_tab();

            return;
        }

        $definition = ModuleRegistry::definitions()[ $module_key ];

        if (! ModuleRegistry::is_enabled($module_key) || ! $definition['has_settings']) {
            self::render_module_info_tab($module_key);

            return;
        }

        if ('smtp' === $tab && class_exists('\BeamSmtpSettingsPage')) {
            \BeamSmtpSettingsPage::render();

            return;
        }

        if ('admin-branding' === $tab && class_exists('\BeamAdminBrandingSettingsPage')) {
            \BeamAdminBrandingSettingsPage::render();

            return;
        }

        if ('favicon' === $tab && class_exists(\Beam\Core\Modules\Favicon\SettingsPage::class)) {
            \Beam\Core\Modules\Favicon\SettingsPage::render();

            return;
        }

        if ('security' === $tab && class_exists(\Beam\Core\Modules\Security\SettingsPage::class)) {
            \Beam\Core\Modules\Security\SettingsPage::render();

            return;
        }
    }

    private static function render_modules_tab(): void
    {
        ?>
		<form method="post" action="<?= esc_url(self::tab_url('modules')) ?>">
			<?php wp_nonce_field('beam_core_modules'); ?>

			<p class="description beam-core-settings__lead">
				Включите только нужные модули. Отключённые не подключают свои хуки.
			</p>

			<table class="widefat striped beam-core-settings__modules">
				<thead>
					<tr>
						<th scope="col" class="beam-core-settings__col-status">Статус</th>
						<th scope="col">Модуль</th>
						<th scope="col">Описание</th>
						<th scope="col" class="beam-core-settings__col-tab">Настройки</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach (ModuleRegistry::definitions() as $key => $definition) : ?>
						<?php
                        $enabled_by_option = ! ModuleRegistry::is_disabled_by_option($key);
					    $removed_by_filter = ModuleRegistry::is_removed_by_filter($key);
					    $is_active         = ModuleRegistry::is_enabled($key);
					    ?>
						<tr>
							<td>
								<label>
									<input
										type="checkbox"
										name="module_enabled[]"
										value="<?= esc_attr($key) ?>"
										<?= checked($enabled_by_option, true, false) ?>
										<?= disabled($removed_by_filter) ?>
									/>
									<span class="screen-reader-text"><?= esc_html($definition['label']) ?></span>
								</label>
							</td>
							<td>
								<strong><?= esc_html($definition['label']) ?></strong>
								<?php if ($removed_by_filter) : ?>
									<p class="description">Отключён фильтром <code>beam_core_modules</code>.</p>
								<?php elseif ($is_active) : ?>
									<p class="description beam-core-settings__status is-active">Активен</p>
								<?php else : ?>
									<p class="description beam-core-settings__status">Выключен</p>
								<?php endif; ?>
							</td>
							<td><?= esc_html($definition['description']) ?></td>
							<td>
								<a href="<?= esc_url(self::tab_url($definition['tab'])) ?>">
									<?= esc_html($definition['has_settings'] ? 'Открыть' : 'Подробнее') ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button('Сохранить модули', 'primary', 'beam_core_modules_save'); ?>
		</form>
		<?php
    }

    private static function render_module_info_tab(string $module_key): void
    {
        $definition = ModuleRegistry::definitions()[ $module_key ];
        $is_active  = ModuleRegistry::is_enabled($module_key);
        ?>
		<div class="beam-core-module-info">
			<p><?= esc_html($definition['description']) ?></p>
			<p>
				Статус:
				<strong><?= esc_html($is_active ? 'Включён' : 'Отключён') ?></strong>
			</p>
			<?php if (! $is_active) : ?>
				<p class="description">Включите модуль на вкладке «Модули».</p>
			<?php endif; ?>
			<p>
				<a href="<?= esc_url(self::tab_url('modules')) ?>">← К списку модулей</a>
			</p>
		</div>
		<?php
    }

    public static function active_tab(): string
    {
        $tab  = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'modules';
        $tabs = ModuleRegistry::tabs();

        return isset($tabs[ $tab ]) ? $tab : 'modules';
    }

    public static function tab_url(string $tab): string
    {
        return add_query_arg(
            [
                'page' => self::PAGE_SLUG,
                'tab'  => $tab,
            ],
            admin_url('options-general.php'),
        );
    }

    private static function load_settings_classes(): void
    {
        require_once BEAM_CORE_DIR . '/Modules/Smtp/Config.php';
        require_once BEAM_CORE_DIR . '/Modules/Smtp/SettingsPage.php';
        require_once BEAM_CORE_DIR . '/Modules/AdminBranding/AssetPath.php';
        require_once BEAM_CORE_DIR . '/Modules/AdminBranding/Config.php';
        require_once BEAM_CORE_DIR . '/Modules/AdminBranding/SettingsPage.php';
        require_once BEAM_CORE_DIR . '/Modules/Favicon/Config.php';
        require_once BEAM_CORE_DIR . '/Modules/Favicon/Generator.php';
        require_once BEAM_CORE_DIR . '/Modules/Favicon/SettingsPage.php';
        require_once BEAM_CORE_DIR . '/Modules/Security/Config.php';
        require_once BEAM_CORE_DIR . '/Modules/Security/Security.php';
        require_once BEAM_CORE_DIR . '/Modules/Security/HideLogin.php';
        require_once BEAM_CORE_DIR . '/Modules/Security/XmlrpcStatus.php';
        require_once BEAM_CORE_DIR . '/Modules/Security/LoginLimitStatus.php';
        require_once BEAM_CORE_DIR . '/Modules/Security/SettingsPage.php';
    }

    private static function register_module_handlers(): void
    {
        if (ModuleRegistry::is_enabled('smtp') && class_exists('\BeamSmtpSettingsPage')) {
            \BeamSmtpSettingsPage::register_handlers();
        }

        if (ModuleRegistry::is_enabled('adminBranding') && class_exists('\BeamAdminBrandingSettingsPage')) {
            \BeamAdminBrandingSettingsPage::register_handlers();
        }

        if (ModuleRegistry::is_enabled('favicon') && class_exists(\Beam\Core\Modules\Favicon\SettingsPage::class)) {
            \Beam\Core\Modules\Favicon\SettingsPage::register_handlers();
        }

        if (ModuleRegistry::is_enabled('security') && class_exists(\Beam\Core\Modules\Security\SettingsPage::class)) {
            \Beam\Core\Modules\Security\SettingsPage::register_handlers();
        }
    }

    private static function asset_url(string $relative): string
    {
        return plugins_url(ltrim($relative, '/'), BEAM_CORE_DIR . '/Core/Settings/assets/settings.css');
    }

    private static function asset_version(string $relative): string
    {
        $path = BEAM_CORE_DIR . '/Core/Settings/assets/' . ltrim($relative, '/');

        return file_exists($path) ? (string) filemtime($path) : BEAM_CORE_VERSION;
    }
}
