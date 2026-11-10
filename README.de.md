<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <b>Deutsch</b> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="LinkHop: short links, QR codes and click analytics" width="100%"></picture></p>

<p align="center"><b>Links kürzen, jeden Klick zählen, das Publikum verstehen. Kostenlos, Open Source und selbst hostbar.</b></p>

---

LinkHop ist ein Link-Kürzer mit QR-Codes und Klick-Statistiken. Konten liegen im HOP-ID-Hub (HEL.ink): Du meldest dich einmal an, und alle Apps der Familie kennen dich.

## Installation

Mit einem Befehl installieren (es prüft zuerst deinen Rechner, stellt ein paar Fragen und kann auch den HOP-ID-Hub für dich einrichten):

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
```

Aus einem Klon: `./install.sh` · aktualisieren: `./update.sh` · entfernen: `./uninstall.sh`. Mit `--yes` gelten alle Standardwerte; `--help` zeigt die Optionen.

## Selbst hosten

<!-- self-hosting:start -->
Alles, was LinkHop braucht, liegt in öffentlichen Repositorys: kein privates Repository, kein Token und kein hel.ink-Konto. Details stehen in der Familienanleitung: [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md) (Englisch).

| Baustein | Für LinkHop | Wozu |
| --- | --- | --- |
| [HEL.ink](https://github.com/navi-crwn/HEL.ink) (Hub, HOP ID) | **Erforderlich**: Die Konten liegen dort, in derselben Datenbank | Konten und Anmeldung für alle Apps |
| [hop/auth](https://github.com/navi-crwn/hop-auth), [hop/ui](https://github.com/navi-crwn/hop-ui), [hop/safety](https://github.com/navi-crwn/hop-safety) | `composer install` holt sie aus dem öffentlichen GitHub | Gemeinsamer PHP-Code: Anmeldung, Oberfläche, Link-Sicherheit |
| [Imperium](https://github.com/navi-crwn/Imperium) | Optional, aber die Admin-Seiten von LinkHop liegen dort | Admin-Panel für die ganze Familie; braucht den Hub |
| Familien-Schriften und -Kacheln | Nichts zu installieren: kommen von `hel.ink/hop-assets` | Ohne hel.ink: Systemschriften, keine schwebenden Kacheln |

- **Reihenfolge**: zuerst der [Hub](https://github.com/navi-crwn/HEL.ink), dann LinkHop. `./install.sh --hub new` installiert beides.
- **Mindest-Einstellungen**: `APP_URL`, `PUBLIC_URL`, `HOP_APP=linkhop`, `HOP_HUB_URL`, `HOP_SSO_SECRET` (wie im Hub), `DB_*` (die Datenbank des Hubs), `HOP_TRUSTED_HOSTS` (deine Hostnamen); mit Imperium: `HOP_CONTROL_URL`.
- **Allein oder in der Familie**: LinkHop läuft nicht ohne Hub. In der Familie teilt es Datenbank und `HOP_SSO_SECRET` mit dem Hub.
- **Ohne Imperium**: `/admin` zeigt einen Hinweis. Konten verwaltest du in den Admin-Seiten des Hubs, dazu gibt es die signierte `/api/hop/*`-API und die artisan-Befehle.
- **Weitere Apps der Familie**: BioHop (Link-in-Bio), PixelHop (Bilder), NoteHop (verschlüsselte Notizen) und FileHop (Dateien im Browser umwandeln) laufen am selben Konto; siehe die englische [README](README.md).
<!-- self-hosting:end -->

## Mehr erfahren

Die vollständige Dokumentation (Funktionen, Einstellungen, Sicherheit) steht auf Englisch in der [README](README.md).

Lizenz: MIT.
