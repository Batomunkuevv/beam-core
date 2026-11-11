<?php

namespace Beam\Core;

defined('ABSPATH') || exit;

/**
 * @phpstan-type ModuleDefinition array{
 *     label: string,
 *     tab: string,
 *     class: string,
 *     description: string,
 *     has_settings: bool
 * }
 * @phpstan-type ModuleDefinitions array<string, ModuleDefinition>
 */
final class ModuleRegistry
{
    public const OPTION_DISABLED = 'beam_core_disabled_modules';

    /**
     * @return ModuleDefinitions
     */
    public static function definitions(): array
    {
        $definitions = [
            'smtp'            => [
                'label'       => 'SMTP',
                'tab'         => 'smtp',
                'class'       => \Beam\Core\Modules\Smtp\Module::class,
                'description' => 'Почта WordPress через SMTP-сервер.',
                'has_settings' => true,
            ],
            'adminBranding'   => [
                'label'       => 'Брендинг админки',
                'tab'         => 'admin-branding',
                'class'       => \Beam\Core\Modules\AdminBranding\Module::class,
                'description' => 'Цвета админки, страница входа и логотип.',
                'has_settings' => true,
            ],
            'disableEmoji'    => [
                'label'       => 'Отключение emoji',
                'tab'         => 'disable-emoji',
                'class'       => \Beam\Core\Modules\DisableEmoji\Module::class,
                'description' => 'Убирает скрипты и стили emoji на сайте и в админке.',
                'has_settings' => false,
            ],
            'disableComments' => [
                'label'       => 'Отключение комментариев',
                'tab'         => 'disable-comments',
                'class'       => \Beam\Core\Modules\DisableComments\Module::class,
                'description' => 'Отключает комментарии на сайте, в REST и XML-RPC.',
                'has_settings' => false,
            ],
            'acfJsonSync'     => [
                'label'       => 'ACF JSON Sync',
                'tab'         => 'acf-json-sync',
                'class'       => \Beam\Core\Modules\AcfJsonSync\Module::class,
                'description' => 'Синхронизация групп полей ACF через JSON в теме или по указанному пути.',
                'has_settings' => false,
            ],
            'acfOffline'      => [
                'label'       => 'ACF Offline',
                'tab'         => 'acf-offline',
                'class'       => \Beam\Core\Modules\AcfOffline\Module::class,
                'description' => 'Отключает обращения ACF Pro к серверу лицензий. Обновление — заменой папки плагина.',
                'has_settings' => false,
            ],
            'breadcrumbs'     => [
                'label'       => 'Breadcrumbs',
                'tab'         => 'breadcrumbs',
                'class'       => \Beam\Core\Modules\Breadcrumbs\Module::class,
                'description' => 'Хлебные крошки для страниц, архивов, типов записей и таксономий.',
                'has_settings' => false,
            ],
            'svgUpload'       => [
                'label'       => 'SVG',
                'tab'         => 'svg-upload',
                'class'       => \Beam\Core\Modules\SvgUpload\Module::class,
                'description' => 'Загрузка SVG в медиабиблиотеку: файл очищается при загрузке, в библиотеке есть превью.',
                'has_settings' => false,
            ],
            'favicon'         => [
                'label'       => 'Favicon',
                'tab'         => 'favicon',
                'class'       => \Beam\Core\Modules\Favicon\Module::class,
                'description' => 'Фавиконка: загрузка SVG или PNG, генерация размеров, манифест и theme-color.',
                'has_settings' => true,
            ],
            'security'        => [
                'label'       => 'Безопасность',
                'tab'         => 'security',
                'class'       => \Beam\Core\Modules\Security\Module::class,
                'description' => 'Отключение редактора файлов, скрытие логинов и проверка XML-RPC.',
                'has_settings' => true,
            ],
        ];

        return self::validDefinitions(
            apply_filters('beam_core_module_definitions', $definitions),
            $definitions,
        );
    }

    /**
     * @return array<string, string>
     */
    public static function tabs(): array
    {
        $tabs = [
            'modules' => 'Модули',
        ];

        foreach (self::definitions() as $definition) {
            $tabs[ $definition['tab'] ] = $definition['label'];
        }

        return $tabs;
    }

    public static function module_key_by_tab(string $tab): ?string
    {
        foreach (self::definitions() as $key => $definition) {
            if ($definition['tab'] === $tab) {
                return $key;
            }
        }

        return null;
    }

    public static function is_enabled(string $key): bool
    {
        if (! isset(self::definitions()[ $key ])) {
            return false;
        }

        $modules = self::filtered_modules();

        return isset($modules[ $key ]);
    }

    public static function is_disabled_by_option(string $key): bool
    {
        $disabled = get_option(self::OPTION_DISABLED, []);

        return is_array($disabled) && in_array($key, $disabled, true);
    }

    public static function is_removed_by_filter(string $key): bool
    {
        $all = [];

        foreach (self::definitions() as $module_key => $definition) {
            $all[ $module_key ] = $definition['class'];
        }

        $filtered = apply_filters('beam_core_modules', $all);

        if (! is_array($filtered)) {
            $filtered = $all;
        }

        return ! isset($filtered[ $key ]);
    }

    /**
     * @return array<string, string>
     */
    public static function filtered_modules(): array
    {
        $all = [];

        foreach (self::definitions() as $key => $definition) {
            $all[ $key ] = $definition['class'];
        }

        $filtered = apply_filters('beam_core_modules', $all);

        if (! is_array($filtered)) {
            $filtered = $all;
        }

        $disabled = get_option(self::OPTION_DISABLED, []);

        if (! is_array($disabled)) {
            $disabled = [];
        }

        foreach ($disabled as $key) {
            unset($filtered[ $key ]);
        }

        return $filtered;
    }

    /**
     * @param list<string> $disabled
     */
    public static function update_disabled_modules(array $disabled): void
    {
        $allowed = array_keys(self::definitions());
        $disabled = array_values(
            array_unique(
                array_filter(
                    $disabled,
                    static fn($key): bool => is_string($key) && in_array($key, $allowed, true),
                ),
            ),
        );

        update_option(self::OPTION_DISABLED, $disabled, false);
    }

    /**
     * @param ModuleDefinitions $fallback
     * @return ModuleDefinitions
     */
    private static function validDefinitions(mixed $definitions, array $fallback): array
    {
        if (! is_array($definitions)) {
            return $fallback;
        }

        $valid = [];

        foreach ($definitions as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            $label = $definition['label'] ?? null;
            $tab = $definition['tab'] ?? null;
            $class = $definition['class'] ?? null;
            $description = $definition['description'] ?? null;
            $hasSettings = $definition['has_settings'] ?? null;

            if (
                ! is_string($label)
                || ! is_string($tab)
                || ! is_string($class)
                || ! is_string($description)
                || ! is_bool($hasSettings)
            ) {
                continue;
            }

            $valid[$key] = [
                'label' => $label,
                'tab' => $tab,
                'class' => $class,
                'description' => $description,
                'has_settings' => $hasSettings,
            ];
        }

        return $valid;
    }
}
