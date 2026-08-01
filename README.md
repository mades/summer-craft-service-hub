# Summer Craft Service Hub

Interfaces and default implementations for the things an application needs but a
framework core should not carry: a database layer, logging, mail, an HTTP client,
file storage, an HTML parser, CSV, time, process handling. ~8k lines, built on
[summer-craft-core](https://github.com/mades/summer-craft-core) and nothing else.

It is not a framework and not a collection of site features. Everything here is
plumbing behind an interface, so an application can write high-level code against
the interface and swap the implementation with an adapter.

## Boundaries

What belongs here: general infrastructure with **no notion of a site**. If a class
would still make sense in a CLI tool that serves no pages, this is its layer.

What does not:

- routing, DI, the request lifecycle, PSR-7 — that is the core, one layer down;
- anything assuming a logged-in user, a page, an admin screen or an uploaded file
  — that belongs to a hub one layer up.

The direction of dependencies is the rule that matters: this package uses the core
and must never reach for a hub above it. That inversion has happened once here — a
time service that imported a `User` model — and it is the likely reason this code
went untested for months.

## What is in it

| Area | What it gives you |
|---|---|
| `Database` | PDO handler and a small active-record layer (`Record`, `RelationalStorage`) |
| `Logger` | `Logger` and `TaggedLogger` interfaces, file and stdout implementations |
| `Email` | `Mailer`, queued or direct sending |
| `Network` | HTTP client over curl |
| `FileStorage` | file and directory operations behind an interface |
| `HtmlParser` | two implementations: `simplehtmldom` and one over `DOMDocument` |
| `Renderer` | PHP view rendering |
| `Language` | volume-based string lookup |
| `Csv`, `Mime`, `Modifier`, `Time`, `Console`, `Processes`, `Waiter` | narrow helpers, one concern each |

## Installation

```bash
composer require mades/summer-craft-service-hub
```

PHP 8.4+ with the `curl`, `pdo` and `mbstring` extensions. `psr/log` and the core
are the only composer dependencies.

## Wiring

**This package registers nothing by itself** — it ships no config loader. Every
interface is bound by whoever consumes it, which is what keeps the implementations
swappable:

```php
$config->services[Logger::class] = ComponentConfig::forClass(FileLogger::class);
$config->services[HtmlParser::class] = ComponentConfig::forClass(DomHtmlParser::class);
```

An application built on a hub above inherits those bindings from the hub's config
loader; one built straight on the core writes them in its own. Either way the
choice of implementation stays with the application.

## Minimal example

A record and a repository:

```php
class Article extends Record
{
    public ?int $id = null;
    public string $title = '';
    public string $body = '';
}

/** @extends RelationalStorage<Article> */
class ArticleRepository extends RelationalStorage
{
    public function __construct(RequestScope $scope)
    {
        parent::__construct($scope, new RelationalStorageConfig(
            Article::class,
            RelationalDatabaseHandler::class, // which handler service to run on
            'articles',
            'id',
            ['id'],
        ));
    }

    public function newInstance(): Article
    {
        return Article::emptyRecord();
    }
}
```

Reading and writing:

```php
$repository = $scope->get(ArticleRepository::class);

$article = $repository->findOne(['id' => 42]);
$recent = $repository->find(
    ['published' => 1],
    ['order' => ['id' => 'desc'], 'limit' => ['count' => 10]],
);

$article->title = 'Renamed';
$repository->save($article);

$scope->get(Logger::class)->info('Article saved', ['id' => $article->id]);
```

## Neighbouring packages

- [summer-craft-core](https://github.com/mades/summer-craft-core) — DI, routing, PSR-7, events. The
  layer below; this package depends on it.
- [summer-craft-skeleton](https://github.com/mades/summer-craft-skeleton) — `create-project` starting
  point for a new application.

Applications consume them as ordinary composer packages. This repository is the
exception: it keeps a path repository on the core so that a change there is
visible here immediately, which is why the two checkouts have to sit side by side.

## Testing

Everything runs in docker, no local PHP needed:

```bash
make test                 # PHPUnit on the latest PHP (8.5)
make test PHP=8.2         # a specific version
make test-all             # full matrix: PHP 8.4 – 8.5
make stan                 # phpstan static analysis (level 5)
make test ARGS="--filter StringModifierTest"
```

The Makefile mounts the **parent** directory rather than this one — otherwise the
path repository pointing at the core cannot be resolved inside the container.

## License

MIT
