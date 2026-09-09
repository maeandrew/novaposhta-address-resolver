# Синтетичні приклади

[English](README.md) · [Українська](README.uk.md)

Ці fixtures вигадані та не містять customer, order або production data. Вони
призначені для offline tests і terminal demo.

- `fixtures/address-cases.json` — input strings і очікувані statuses.
- `fixtures/settlements.json` — нормалізовані settlement records.
- `fixtures/warehouses.json` — нормалізовані warehouse records.
- `fixtures/ai-responses.json` — structured AI responses, зокрема invalid та
  hallucinated-reference cases, які мають бути відхилені.
- `offline-demo.php` — runnable resolver example без мережевих запитів.
- `custom-provider.php` — runnable мінімальна реалізація `LocationProvider`, яку
  можна замінити адаптером SDK, HTTP-клієнтом або локальним read model.

`fixture-*` references не є реальними references Нової пошти.

Запуск:

```bash
ddev exec php examples/offline-demo.php "Київ 133"
ddev exec php examples/custom-provider.php "Прикладне, відділення 1"
```
