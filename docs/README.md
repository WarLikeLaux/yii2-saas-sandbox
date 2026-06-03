# API-документация

Документация API генерируется через [Doctum](https://doctum.long-term.support/) из `phpDoc`-блоков в коде.

## Как собрать

```bash
make docs
```

Если `tools/doctum.phar` ещё не скачан, `make docs` автоматически вызовет:

```bash
make docs-install
```

Готовый результат будет в `docs/api/`.

> Сборка идёт внутри контейнера (PHP 7.4), как и `make dev/test/analyze`. Поэтому
> используется ветка Doctum 5.5 — последняя с поддержкой PHP 7.4. Флаг
> `--ignore-parse-errors` обязателен: Doctum не понимает расширенные типы PHPStan
> (`list<array{...}>`, `class-string`) и без него завершается с ошибкой.

## Что учитывает генератор

- описания классов и интерфейсов;
- `@param`, `@return`, `@throws`;
- типы свойств и массивов;
- структуру namespace'ов.

Состав документируемого кода и каталог вывода настраиваются в `docs/doctum.php`.
