# summer-craft-service-hub

Сервисный слой summer-craft: PDO/DB handler (свой мини-ORM поверх `Record`), Logger,
Mailer (адаптация email-класса из CodeIgniter, внутри "CodeHuiter"), Csv, HTTP-клиент на
curl, HTML-parser (вендорная `simplehtmldom`), Renderer (`PhpRenderer`, request-scoped,
`ob_*`), FileStorage, Time, Modifier, Registry, Console, Processes, Mime, Language.
~8.3k строк. Framework — прямая зависимость снизу; потребители — сверху.

## Тесты

По образцу framework: `composer.json` (path-репо на `../summer-craft-core` — репо
должны лежать рядом), `Makefile`/`phpunit.xml.dist`/`phpstan.neon.dist` (L5,
`phpstan-baseline.neon` гитится).

```bash
make test        # docker, дефолт PHP 8.5
make test-all    # матрица 8.4-8.5
make stan        # phpstan L5 + baseline
```

Makefile монтирует **родительскую** директорию (`$(CURDIR)/..`), иначе composer не видит
path-репо на framework внутри контейнера. Состояние: **137 тестов, phpstan чист,
`phpstan-baseline.neon` полностью пуст** (`ignoreErrors: []`) — ни одной молчаливо
подавленной находки.

## Бэклог: префикс `SCS` в общем `summer-craft/.tasks/INDEX.md` — закрыт, кроме SCP-0045

Разобрано за два прохода ревью (2026-07-12/13): архитектурная проблема, 3 runtime
critical-бага, XSS/injection-класса дыры, мёртвый код, подтверждённый SSRF, два пакета
гигиены, полная зачистка phpstan-baseline. Детали каждой задачи — в
`summer-craft/.tasks/SCS-00XX-*.md` (бэклоги всех репо слиты в один 2026-07-30).
Особо важное:

- **SCP-0028** — service-hub раньше зависел от слоя выше (`Time\DateTimeService`
  импортировал `User`) — обратный слой, вероятная причина отсутствия тестов все эти
  месяцы. Разорвано узким интерфейсом `Time\HasTimezone`.
- **SCP-0031** — попутно закрыта дыра в `PhpRenderer`: утечка `ob_*`-буфера и лока при
  исключении во вьюхе (критично под worker-моделью); переведён на `RequestScopeComponent`.
  Под Fiber-конкурентность рендеринг всё равно не безопасен (`ob_*` — общее состояние
  процесса) — полное решение (возврат строк вместо `ob_*`) не делалось, не в планах.
- **SCP-0032 п.8** — вендорный `SimpleHtmlDom` в конструкторе трактует строку как URL/путь
  для загрузки, если она похожа (`^http://` или `is_file`). Живой вызывающий
  (`SiteChangedNotificator` в develop) кладёт туда сырое тело ответа внешнего сайта —
  **подтверждённый SSRF**. Фикс в обёртке `SimpleHtmlDomParser::load()` (всегда парсит как
  текст, минуя sniffing-конструктор), вендорный класс не трогался.
- **SCP-0037** — альтернативный `DomHtmlParser` (DOMDocument/DOMXPath) реализован и
  протестирован (живой прогон на реальных страницах, включая Конституцию); DI-переключение
  исключено из скоупа решением пользователя, дефолт остаётся `SimpleHtmlDomParser`.

`Logger/StdOutLogger` (SCP-0036) — пишет в `php://stdout`/`stderr`, Monolog-style split по
severity (настройки в `LoggerConfig`). **Не** дефолтный алиас в конфиг-лоадере слоя
выше (там `FileLogger`) — переключение отдельным решением.

## Оценка

Ядро (Database handler, RelationalStorage/Record, DI-интеграция) спроектировано разумно.
Реально чинили — границы доверия (SQL-идентификаторы, файловые пути, TLS) и copy-paste
баги (Postgres-функция в MySQL-классе, `$$this` вместо `$this`). Паттерн знаком по ревью
framework: ядро крепкое, дыры предсказуемо там, где раньше не смотрели «а что если это
враждебный ввод». Изменения в рабочем дереве не закоммичены.
