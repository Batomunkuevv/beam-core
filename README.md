# BE.AM Core

Инфраструктурные модули для WordPress: SMTP, безопасность, фавиконка, ACF, SVG и другие. Каждый модуль можно включить отдельно: **Настройки → BE.AM Core**.

Требуется PHP 8.1 и WordPress 6.4+.

## Установка

### Как обычный плагин

Скопируйте репозиторий в `wp-content/plugins/beam-core/` и активируйте «BE.AM Core».

### Как must-use плагин

Положите содержимое репозитория в `wp-content/mu-plugins/beam-core/` и добавьте загрузчик `wp-content/mu-plugins/beam-core.php`:

```php
<?php

require_once __DIR__ . '/beam-core/beam-core.php';
```

WordPress подключает только PHP-файлы из корня `mu-plugins/`, поэтому без загрузчика пакет не запустится.

### Composer (VCS)

В `composer.json` сайта:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/Batomunkuevv/beam-core.git"
    }
  ],
  "require": {
    "batomunkuevv/beam-core": "^1.4"
  },
  "extra": {
    "installer-paths": {
      "web/app/mu-plugins/{$name}/": ["type:wordpress-muplugin"]
    }
  }
}
```

Для обычного `wp-content` путь будет `wp-content/mu-plugins/{$name}/`. Репозиторий частный: в Composer нужна авторизация GitHub (SSH-агент или `github-oauth` в локальном `auth.json`, не в репозитории сайта).

После установки пакет окажется в `mu-plugins/beam-core/`. Загрузчик `mu-plugins/beam-core.php` Composer не создаёт — его нужно добавить отдельно, как в разделе выше.

## Модули

- **SMTP** — почта WordPress через SMTP-сервер. Настройки или константы `BEAM_SMTP_*`. На production пароль лучше задавать через `BEAM_SMTP_PASS`, а не хранить в базе.
- **Брендинг админки** — цвета админки, страница входа и логотип.
- **Отключение emoji** — убирает скрипты и стили emoji на сайте и в админке.
- **Отключение комментариев** — отключает комментарии на сайте, в REST и XML-RPC.
- **ACF JSON Sync** — синхронизация групп полей ACF через JSON. По умолчанию каталог — `acf-json/` активной темы, путь можно сменить фильтром. Команда `wp acf sync` импортирует группы в базу.
- **ACF Offline** — отключает обращения ACF Pro к серверу лицензий. Обновление — заменой папки плагина. Лицензию модуль не подменяет; использовать только с копией, на которую есть право, security-обновления ставить вручную.
- **Breadcrumbs** — хлебные крошки для страниц, архивов, типов записей и таксономий.
- **SVG** — загрузка SVG в медиабиблиотеку: файл очищается при загрузке, в библиотеке есть превью.
- **Favicon** — загрузка SVG или PNG, генерация размеров, манифест и theme-color.
- **Безопасность** — отключение редактора файлов, скрытие логинов и проверка XML-RPC.

Отключённые модули не подключают свои хуки. Жёстко убрать модуль из списка (в админке его не будет):

```php
add_filter(
    'beam_core_modules',
    static function (array $modules): array {
        unset($modules['disableComments']);

        return $modules;
    }
);
```

Модули регистрируются на `after_setup_theme`, поэтому фильтр можно повесить в mu-plugin или в `functions.php` темы.

## Фильтры

- `beam_core_modules` — список модулей (`key => class`).
- `beam_core_module_definitions` — полные описания модулей для UI.
- `beam_acf_json_directory` — каталог Local JSON ACF.
- `beam_favicon_attachment_id` — ID вложения фавиконки.
- `beam_favicon_defaults` — иконки по умолчанию (`svg`, `icon_32`, `apple_touch`, `icon_192`, `icon_512`, `ico`), если файл в настройках не выбран.
- `beam_favicon_theme_color`, `beam_favicon_background_color` — цвета манифеста и `theme-color`.
- `beam_admin_branding_config` — настройки брендинга админки.
- `beam_admin_branding_login_logo_url` — URL логотипа на странице входа.
- `beam_security_block_author_archives` — `false`, если публичные архивы авторов нужны.
- `beam_security_xmlrpc_sslverify` — проверка TLS при запросе статуса XML-RPC.

Тема или mu-plugin сайта передают свои значения через эти фильтры. В самом пакете нет привязки к конкретному проекту.

## SMTP

Поддерживаются опция `beam_smtp_settings` и константы `BEAM_SMTP_*`. Адрес для заявок с форм — поле в настройках или `BEAM_SMTP_LEAD_EMAIL`, иначе email администратора сайта.

## Breadcrumbs

```php
breadcrumbs();
$items = breadcrumbs_items();
```

## SVG

Санитайзер доступен и при выключенном модуле — для инлайн-вывода в теме:

```php
$markup = \Beam\Core\Modules\SvgUpload\Sanitizer::sanitize((string) file_get_contents($path));
```

## Favicon

Загрузка — **Настройки → BE.AM Core → Favicon**. SVG или PNG, лучше квадрат от 512×512; производные размеры пишутся в `uploads/beam-favicon/`. Неквадратные изображения обрезаются по центру. Если задана фавиконка или иконки по умолчанию, штатные теги иконки сайта WordPress не дублируются.

Пример для темы:

```php
add_filter('beam_favicon_defaults', static function (array $defaults): array {
    $defaults['svg'] = get_theme_file_uri('assets/brand/favicon.svg');
    $defaults['icon_32'] = get_theme_file_uri('assets/brand/favicon-32.png');
    $defaults['apple_touch'] = get_theme_file_uri('assets/brand/apple-touch-icon.png');
    $defaults['ico'] = home_url('/favicon.ico');

    return $defaults;
});

add_filter('beam_favicon_theme_color', static fn (): string => '#111111');
add_filter('beam_favicon_background_color', static fn (): string => '#111111');
```

Растеризация SVG идёт через Imagick, если расширение доступно. Иначе для PNG-размеров используются пути из `beam_favicon_defaults`, а ссылка на загруженный SVG сохраняется.

## Безопасность

По умолчанию включены: отключение редактора файлов (`DISALLOW_FILE_EDIT`, только если константа ещё не задана), скрытие логинов (для гостей закрыты `?author=`, `/author/`, REST `/wp/v2/users`, авторы в oEmbed и карте сайта) и общее сообщение об ошибке входа.

XML-RPC модуль не отключает: на вкладке проверяется доступность (`system.listMethods`, кеш около часа). Если эндпоинт открыт, его лучше закрыть в nginx. Путь в сниппете считается из `site_url('xmlrpc.php')`.

## Changelog

См. [CHANGELOG.md](CHANGELOG.md).
