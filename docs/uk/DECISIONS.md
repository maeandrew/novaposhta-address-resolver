# Архітектурні рішення

[English](../DECISIONS.md) · [Українська](DECISIONS.md)

Цей файл містить рішення, які мають залишатися стабільними. Зміни публічних
контрактів треба описувати тут разом із причиною та наслідками міграції.

## D001 — Ядро без фреймворків

Доменне ядро є чистим PHP-пакетом. Laravel, Eloquent, Filament, HTTP-клієнти,
SDK Нової пошти та AI SDK є інтеграціями, а не залежностями ядра.

## D002 — Provider є джерелом істини

AI та fuzzy matching можуть запропонувати кандидата, але resolved-результат
може містити лише нормалізований запис, повернутий `LocationProvider`.

## D003 — Неоднозначність є окремим результатом

Коли доказів недостатньо, resolver повертає `ambiguous` із кандидатами для
ручної перевірки. Він не обирає перший запис API автоматично.

## D004 — AI рекомендований і залишається pluggable

AI є рекомендованим шаром якості для production-ввідних даних, особливо коли є
одруки, змішані мови або пропущені назви полів. Детермінований pipeline
залишається обов’язковим fallback для збоїв, privacy-обмежень, offline-роботи й
тестів. Structured AI provider реєструється через interface і може працювати у
fallback chain з явною політикою.

## D005 — Persistence належить host-застосунку

Core повертає DTO і не записує замовлення, адреси чи audit rows. Laravel bridge
може надати mapping callback, jobs і events, але не нав’язує схему host-а.

## D006 — Лише синтетичні fixtures

Тести й demo використовують `fixture-*` references та вигадані адреси. Customer
data, production payloads і credentials не додаються до репозиторію.

## D007 — Monorepo на етапі розробки

Core, Laravel bridge та optional adapters розробляються в одному репозиторії,
але кожен package має окремий Composer manifest і чисті межі залежностей.

## D008 — Публічний namespace пакета

Пакет має назву `maeandrew/novaposhta-address-resolver` і PSR-4 namespace
`MaeAndrew\\NovaPoshtaAddressResolver`.

Це пов’язує package identity з публічним maintainer-ом і залишає доменні
поняття Нової пошти всередині namespace пакета.

## D009 — PSR HTTP для першого AI-адаптера

Перший реальний AI-адаптер — опційний пакет OpenAI Responses API у
`packages/openai`. Він залежить від PSR HTTP interfaces і приймає client та
factory від host-застосунку замість обов’язкового vendor SDK.

Це залишає адаптер малим, придатним до офлайн-тестування та сумісним із різними
HTTP-клієнтами. Пізніше можна додати окремий SDK-адаптер без зміни контрактів
ядра.

## D010 — Laravel-інтеграція є окремим package

Laravel bridge живе в `packages/laravel` і підтримує Laravel 11, 12 і 13 на PHP
8.2+. Він підключає core через container, а cache, events, queue jobs і Artisan
commands залишаються опційними integration services. Persistence та mapping
моделей належать host-застосунку. Опублікований package має назву
`maeandrew/novaposhta-address-resolver-laravel`.

Це ізолює core від оновлень фреймворку та схем конкретних застосунків. Bridge
публікується окремо без додавання Laravel dependencies до core-користувачів.
