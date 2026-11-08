<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <b>Русский</b></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="LinkHop: short links, QR codes and click analytics" width="100%"></picture></p>

<p align="center"><b>Сокращайте ссылки, считайте каждый клик и узнавайте свою аудиторию. Бесплатно, с открытым кодом, можно развернуть у себя.</b></p>

---

LinkHop — сервис коротких ссылок с QR-кодами и статистикой кликов. Аккаунты хранятся в хабе HOP ID (HEL.ink): вы входите один раз, и все приложения семейства вас знают.

## Установка

Установка одной командой (сначала проверяет ваш компьютер, задаёт несколько вопросов и может сам установить хаб HOP ID):

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
```

Из клона: `./install.sh` · обновление: `./update.sh` · удаление: `./uninstall.sh`. С `--yes` берутся все значения по умолчанию; `--help` показывает параметры.

## Собственный хостинг

<!-- self-hosting:start -->
Всё, что нужно LinkHop, лежит в публичных репозиториях: не нужны ни приватный репозиторий, ни токен, ни учётная запись на hel.ink. Подробности — в руководстве по семейству: [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md) (на английском).

| Компонент | Для LinkHop | Зачем |
| --- | --- | --- |
| [HEL.ink](https://github.com/navi-crwn/HEL.ink) (хаб, HOP ID) | **Обязательно**: учётные записи хранятся там, в той же базе данных | Учётные записи и вход для всех приложений |
| [hop/auth](https://github.com/navi-crwn/hop-auth), [hop/ui](https://github.com/navi-crwn/hop-ui), [hop/safety](https://github.com/navi-crwn/hop-safety) | Устанавливаются `composer install` из публичного GitHub | Общий PHP-код: вход, интерфейс, безопасность ссылок |
| [Imperium](https://github.com/navi-crwn/Imperium) | Необязательно, но страницы администрирования LinkHop находятся там | Панель администратора для всего семейства; нужен хаб |
| Шрифты и плитки семейства | Ничего устанавливать не нужно: загружаются с `hel.ink/hop-assets` | Без hel.ink: системные шрифты, без плавающих плиток |

- **Порядок установки**: сначала [хаб](https://github.com/navi-crwn/HEL.ink), затем LinkHop. `./install.sh --hub new` устанавливает оба.
- **Минимальные настройки**: `APP_URL`, `PUBLIC_URL`, `HOP_APP=linkhop`, `HOP_HUB_URL`, `HOP_SSO_SECRET` (как у хаба), `DB_*` (база данных хаба), `HOP_TRUSTED_HOSTS` (ваши имена хостов); с Imperium: `HOP_CONTROL_URL`.
- **Отдельно или в семействе**: LinkHop не работает без хаба. В семействе оно использует общую с хабом базу данных и `HOP_SSO_SECRET`.
- **Без Imperium**: `/admin` показывает уведомление. Учётными записями управляйте на страницах администрирования хаба, а также используйте подписанный API `/api/hop/*` и команды artisan.
<!-- self-hosting:end -->

## Подробнее

Полная документация (возможности, настройки, безопасность) — на английском в [README](README.md).

Лицензия: MIT.
