# BE.AM Core

Инфраструктурные модули для WordPress: SMTP, безопасность, фавиконка, поля, SVG и другие. Каждый модуль можно включить отдельно: **Настройки → BE.AM Core**.

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
    "batomunkuevv/beam-core": "^2.0"
  },
  "extra": {
    "installer-paths": {
      "web/app/mu-plugins/{$name}/": ["type:wordpress-muplugin"]
    }
  }
}
```

Для обычного `wp-content` путь будет `wp-content/mu-plugins/{$name}/`. Репозиторий публичный: достаточно HTTPS, ключи и `github-oauth` не нужны.

После установки пакет окажется в `mu-plugins/beam-core/`. На Bedrock его подключает `bedrock-autoloader.php`. Если раньше стоял ручной загрузчик `mu-plugins/beam-core.php`, его нужно удалить: WordPress сначала загружает PHP-файлы из корня `mu-plugins/`, и из-за константы `BEAM_CORE_LOADED` новая копия из каталога пакета может не подключиться. Для классического WordPress без автозагрузчика mu-plugin загрузчик по-прежнему нужен, как в разделе выше.

## Модули

- **SMTP** — почта WordPress через SMTP-сервер. Настройки или константы `BEAM_SMTP_*`. На production пароль лучше задавать через `BEAM_SMTP_PASS`, а не хранить в базе.
- **Брендинг админки** — цвета админки, страница входа и логотип.
- **Отключение emoji** — убирает скрипты и стили emoji на сайте и в админке.
- **Отключение комментариев** — отключает комментарии на сайте, в REST и XML-RPC. Отзывы WooCommerce (`product`) по умолчанию не затрагиваются; список исключений задаётся фильтром `beam_disable_comments_excluded_post_types`.
- **JSON групп полей** — синхронизация групп полей через JSON. По умолчанию каталог — `acf-json/` активной темы, путь можно сменить фильтром. Команда `wp acf sync` импортирует группы в базу. Ключ модуля `acfJsonSync` не менялся.
- **Breadcrumbs** — хлебные крошки для страниц, архивов, типов записей и таксономий.
- **SVG** — загрузка SVG в медиабиблиотеку: файл очищается при загрузке, в библиотеке есть превью.
- **Favicon** — загрузка SVG или PNG, генерация размеров, манифест и theme-color.
- **Безопасность** — отключение редактора файлов, скрытие логинов и адреса входа, проверка XML-RPC и лимита попыток входа.

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
- `beam_acf_json_directory` — каталог Local JSON групп полей. Имя фильтра и каталог `acf-json` не переименовываются.
- `beam_favicon_attachment_id` — ID вложения фавиконки.
- `beam_favicon_defaults` — иконки по умолчанию (`svg`, `icon_32`, `apple_touch`, `icon_192`, `icon_512`, `ico`), если файл в настройках не выбран.
- `beam_favicon_theme_color`, `beam_favicon_background_color` — цвета манифеста и `theme-color`.
- `beam_admin_branding_config` — настройки брендинга админки.
- `beam_admin_branding_login_logo_url` — URL логотипа на странице входа.
- `beam_security_block_author_archives` — `false`, если публичные архивы авторов нужны.
- `beam_security_users_capability` — право для архивов авторов и REST `/wp/v2/users`. По умолчанию `list_users`, а не любой вошедший пользователь.
- `beam_security_login_slug` — слаг скрытого адреса входа.
- `beam_security_xmlrpc_sslverify` — проверка TLS при запросе статуса XML-RPC. По умолчанию `true`, для локальных доменов (`*.ddev.site`, `*.local`, `localhost` и аналогичных) — `false`.
- `beam_security_login_limit_sslverify` — то же для проверки лимита попыток входа.
- `beam_security_log_login_failures` — `false`, чтобы не писать неудачные входы в syslog.
- `beam_security_client_ip` — IP клиента для журнала неудачных входов. По умолчанию `REMOTE_ADDR`; заголовкам вроде `X-Forwarded-For` модуль не доверяет.
- `beam_disable_comments_excluded_post_types` — типы записей, у которых комментарии остаются включёнными. По умолчанию `product`, если активен WooCommerce или зарегистрирован этот тип.

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

## Отключение комментариев

Модуль отключает комментарии и обсуждения на сайте. Отзывы WooCommerce не отключаются: тип `product` по умолчанию входит в исключения, если активен WooCommerce или этот тип записей зарегистрирован. Чтобы отключать и их:

```php
add_filter('beam_disable_comments_excluded_post_types', static fn (): array => []);
```

## Безопасность

По умолчанию включены: отключение редактора файлов (`DISALLOW_FILE_EDIT`, только если константа ещё не задана), скрытие логинов (для гостей закрыты `?author=`, `/author/`, REST `/wp/v2/users`, авторы в oEmbed и карте сайта) и общее сообщение об ошибке входа. Архивы авторов и REST `/wp/v2/users` открыты только пользователям с правом `list_users` (обычно администраторам), а не любому вошедшему. Право задаётся фильтром `beam_security_users_capability`.

Скрытие адреса входа выключено по умолчанию. Если его включить, вход будет только по своему адресу: при первом включении, если слаг ещё не задан, создаётся случайный вроде `login-k7f3q9` и сохраняется в настройках сайта. Для гостей `wp-login.php` и `/wp-admin` отдают 404 темы; `admin-ajax.php`, `admin-post.php`, REST и форма входа WooCommerce не затрагиваются. Если вход станет недоступен, в `wp-config.php` можно задать `define('BEAM_DISABLE_HIDE_LOGIN', true);` — модуль сразу перестанет скрывать адрес.

XML-RPC модуль не отключает: на вкладке проверяется доступность (`system.listMethods`, кеш около часа). Если эндпоинт открыт, его лучше закрыть в nginx. Сниппет выводит `location = /xmlrpc.php` и, если WordPress в подкаталоге (например Bedrock `/wp/`), также `location = /wp/xmlrpc.php`.

Лимит попыток входа модуль сам не включает. На вкладке есть готовые сниппеты nginx (`limit_req`) и fail2ban. Если скрытие входа включено, лимит в nginx нужно ставить на этот адрес, а не на `wp-login.php`. При неверном пароле WordPress отвечает HTTP 200, поэтому fail2ban смотрит не access-лог nginx, а запись в syslog: facility `auth`, ident `beam-wp`, строка вида `Authentication failure for <user> from <ip>`. Обычно это `/var/log/auth.log` (на RHEL — `/var/log/secure`). IP берётся из `REMOTE_ADDR`, подменить его можно фильтром `beam_security_client_ip`. Отключить запись: `define('BEAM_DISABLE_LOGIN_FAIL_LOG', true);` или фильтр `beam_security_log_login_failures`. Сниппет с баном по 429 от nginx — отдельно и по желанию. Проверка по кнопке делает несколько быстрых POST с неверными данными и может задеть fail2ban, если в `ignoreip` нет адреса сервера; сама по загрузке страницы она не запускается.

## Поля

В пакет встроены поля на основе Advanced Custom Fields PRO **6.7.0.1** (GPLv2 или новее): каталог `vendor-acf/`. Это собственная наработка BE.AM, из апстрима она не обновляется. В интерфейсе это пункт меню «Поля» и название «BE.AM Fields».

Код подключается из `beam-core.php` на `plugins_loaded`, только если класс `ACF` ещё не объявлен. К этому моменту обычные плагины уже загружены, поэтому активный оригинальный ACF или ACF Pro занимает класс первым: встроенная копия не грузится, в админке показывается уведомление «Уже установлен ACF, используется он.»

Проверка лицензии, страница «Updates» и апдейтер удалены. Поля, страницы опций, блоки и WP-CLI доступны без сервера лицензий. Опция `acf_pro_license` в базе не читается и не меняется: при откате на оригинальный плагин сохранённый ключ остаётся на месте.

Функции `acf_pro_is_license_active`, `acf_pro_get_license` и соседние с ними оставлены пустыми и отвечают «лицензия есть». Их вызывают темы и плагины. Внутри пакета доступность полей от этих функций не зависит.

Нельзя переименовывать, иначе существующие сайты, темы и сохранённые данные потребуют миграции:

- функции `get_field`, `the_field`, `update_field` и остальные функции API;
- классы `ACF`, `acf_*` и пространство имён `ACF\`;
- хуки `acf/*`;
- ключи `field_*` и `group_*`;
- каталог и формат `acf-json`;
- имена опций и метаполей в базе, включая `acf_pro_license` и уже сохранённые значения полей.

Ключ модуля синхронизации `acfJsonSync`, фильтр `beam_acf_json_directory` и команда `wp acf sync` тоже остаются прежними.

## Changelog

См. [CHANGELOG.md](CHANGELOG.md).
