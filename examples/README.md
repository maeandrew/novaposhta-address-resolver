# Synthetic examples

[English](README.md) · [Українська](README.uk.md)

These fixtures are deliberately synthetic and contain no customer, order, or
production information. They are intended to drive offline tests and a small
terminal demo.

- `fixtures/address-cases.json` — input strings and expected statuses.
- `fixtures/settlements.json` — normalized provider settlement records.
- `fixtures/warehouses.json` — normalized provider warehouse records.
- `fixtures/ai-responses.json` — structured AI responses, including invalid and
  hallucinated-reference cases that must be rejected.
- `offline-demo.php` — a runnable no-network resolver example.

The `fixture-*` references are not Nova Poshta references. An adapter test may
replace them with values from a fake SDK response, but core tests should use the
same normalized shape.

Run the demo with:

```bash
ddev exec php examples/offline-demo.php "Київ 133"
```
