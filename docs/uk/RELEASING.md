# Публікація релізів

Пакет використовує Semantic Versioning і позначає релізи Git-тегами з
префіксом `v`, наприклад `v0.1.0`.

## Правила версій

- `PATCH` — виправлення без зміни публічного API.
- `MINOR` — зворотно сумісна нова функціональність.
- `MAJOR` — зміна або видалення публічного контракту.

Milestone-версії: `v0.1.0` для M0/M1, `v0.2.0` для M2 та `v0.3.0` для M3.
Перший стабільний API отримає `v1.0.0` після перевірки сумісності.

## Кроки релізу

1. Оновіть `CHANGELOG.md` і перенесіть готові записи з `Unreleased` у секцію
   датованої версії.
2. Запустіть повну офлайн-перевірку:

   ```bash
   ddev composer quality
   ```

3. Створіть commit і annotated tag:

   ```bash
   git add CHANGELOG.md
   git commit -m "Prepare v0.3.0 release"
   git tag -a v0.3.0 -m "Release v0.3.0"
   git push origin main
   git push origin v0.3.0
   ```

4. GitHub Actions перевірить тег, повторно запустить matrix на PHP 8.2 та 8.3
   і створить GitHub Release після успішного завершення перевірок.

Composer знаходить VCS-пакет за цими тегами. Після публікації в Packagist
користувачі зможуть виконати:

```bash
composer require maeandrew/novaposhta-address-resolver:^0.3
```

Laravel bridge публікується з окремого repository як
`maeandrew/novaposhta-address-resolver-laravel`. Його tag і версія в Packagist
відповідають релізу core, а Composer dependency залишається framework-free
core package.

До публікації в Packagist можна додати GitHub repository як Composer VCS
repository та використовувати ту саму version constraint.
