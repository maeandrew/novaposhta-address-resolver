# Внесок у проєкт

[English](CONTRIBUTING.md) · [Українська](CONTRIBUTING.uk.md)

Проєкт приймає сфокусовані pull request-и, які зберігають framework-free core
і правила безпечного resolution.

Перед pull request:

1. Прочитайте `AGENTS.md` та документи реалізації.
2. Додайте або оновіть offline tests для зміни поведінки.
3. Залишайте framework/vendor інтеграції в окремих adapter packages.
4. Запустіть formatter, static analysis і повний test suite.
5. Не додавайте credentials, customer data, live API responses або private
   infrastructure details.

Якщо змінюється API або domain decision, оновіть `docs/DECISIONS.md` та README.
