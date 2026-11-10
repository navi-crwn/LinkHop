<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <b>Français</b> · <a href="README.ru.md">Русский</a></p>

# LinkHop — LinkHop · Liens courts, codes QR et statistiques de clics

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/LinkHop/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/LinkHop/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="LinkHop · Réducteur de liens HOP" width="100%"></picture></p>

<p align="center"><b>Raccourcissez vos liens, comptez chaque clic, analysez vos visiteurs par pays et appareil, et protégez-les grâce à une détection automatisée des menaces.</b></p>

<p align="center">
  <a href="https://link.hel.ink">link.hel.ink</a> ·
  <a href="#run">Auto-hébergement</a> ·
  <a href="#features">Fonctionnalités</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="#family">Famille HOP</a>
</p>

---

Installez en une commande (il vérifie d’abord votre ordinateur, pose quelques questions et peut aussi installer le hub HOP ID pour vous) :

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
```

Depuis un clone : `./install.sh` · mise à jour : `./update.sh` · suppression : `./uninstall.sh`. `--yes` prend toutes les valeurs par défaut ; `--help` liste les options.

## Le réducteur de liens HOP

Réducteur de liens rapide et respectueux de la vie privée avec slugs personnalisés, codes QR, analyses détaillées et sécurité proactive.

<a id="features"></a>

## Fonctionnalités

- **Raccourcissement ultra-rapide**: Slugs sur mesure ou identifiants générés servis instantanément sur hel.ink/l/{slug} avec une latence minimale.
- **Codes QR dynamiques**: Générez des codes QR en haute résolution SVG/PNG adaptés à l'impression comme au partage web.
- **Statistiques détaillées des clics**: Suivez en temps réel les pays d'origine (GeoIP), appareils, navigateurs, systèmes et référents.
- **Sécurité proactive intégrée**: Analyse continue via hop/safety et listes de blocage partagées pour protéger votre audience.
- **Contrôles d'accès précis**: Définissez une date d'expiration, un nombre maximum de clics et un mot de passe pour vos liens sensibles.
- **Dossiers et étiquettes**: Classez vos liens dans des dossiers et associez des tags à vos campagnes pour des rapports clairs.
- **Authentification unique HOP ID**: Connectez-vous avec votre HOP ID central sans multiplier les comptes.
- **API REST pour développeurs**: API complète avec jetons d'accès pour créer et suivre vos liens par programmation.

<a id="run"></a>

## Hébergez votre propre LinkHop

LinkHop s'exécute sur une infrastructure standard moderne PHP et Laravel.

```bash
git clone https://github.com/navi-crwn/LinkHop.git
cd LinkHop
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

<a id="faq"></a>

## Foire aux questions

**Où sont passées les pages link-in-bio ?**
Les pages de profil bio ont été séparées pour former BioHop (bio.hel.ink), une application dédiée et enrichie. Les anciens liens redirigent automatiquement.

**Puis-je personnaliser l'adresse de mon lien ?**
Oui, les utilisateurs connectés peuvent choisir le slug personnalisé de leur choix lorsqu'il est disponible.

**Comment les statistiques protègent-elles la vie privée ?**
Les données sont agrégées sans stocker d'informations personnelles brutes ni profiler les internautes, et les journaux sont purgés régulièrement.

**LinkHop est-il open source ?**
Oui, LinkHop est distribué sous la licence permissive MIT.

**Puis-je raccourcir le lien d'une note NoteHop chiffrée ?**
Non. La clé d'une note chiffrée est la partie de son lien située après le `#`, et un lien court la conserverait sur le serveur ; LinkHop refuse donc ces liens : partagez le lien NoteHop tel quel (son bouton de partage donne déjà une adresse courte).

<a id="family"></a>

## Auto-hébergement

<!-- self-hosting:start -->
Tout ce dont LinkHop a besoin se trouve dans des dépôts publics : aucun dépôt privé, aucun jeton et aucun compte hel.ink. Les détails sont dans le guide de la famille : [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md) (en anglais).

| Élément | Pour LinkHop | Rôle |
| --- | --- | --- |
| [HEL.ink](https://github.com/navi-crwn/HEL.ink) (hub, HOP ID) | **Obligatoire** : les comptes y sont, dans la même base de données | Comptes et connexion pour toutes les apps |
| [hop/auth](https://github.com/navi-crwn/hop-auth), [hop/ui](https://github.com/navi-crwn/hop-ui), [hop/safety](https://github.com/navi-crwn/hop-safety) | Installés par `composer install` depuis GitHub public | Code PHP partagé : connexion, interface, sécurité des liens |
| [Imperium](https://github.com/navi-crwn/Imperium) | Facultatif, mais les pages d'administration de LinkHop s'y trouvent | Panneau d'administration de toute la famille ; nécessite le hub |
| Polices et tuiles de la famille | Rien à installer : chargés depuis `hel.ink/hop-assets` | Sans hel.ink : polices système, pas de tuiles flottantes |

- **Ordre d'installation** : d'abord le [hub](https://github.com/navi-crwn/HEL.ink), puis LinkHop. `./install.sh --hub new` installe les deux.
- **Réglages minimaux** : `APP_URL`, `PUBLIC_URL`, `HOP_APP=linkhop`, `HOP_HUB_URL`, `HOP_SSO_SECRET` (celui du hub), `DB_*` (la base de données du hub), `HOP_TRUSTED_HOSTS` (vos noms d'hôte); avec Imperium: `HOP_CONTROL_URL`.
- **Seul ou en famille** : LinkHop ne fonctionne pas sans hub. Dans la famille, il partage la base de données et le `HOP_SSO_SECRET` du hub.
- **Sans Imperium** : `/admin` affiche un avis. Gérez les comptes dans les pages d'administration du hub et utilisez l'API signée `/api/hop/*` et les commandes artisan.
<!-- self-hosting:end -->

## La famille HOP

LinkHop fait partie de la famille d'applications web ouvertes HOP.

| Application | Adresse en ligne | Code | Ce qu'elle fait |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | Portail de comptes, connexion HOP ID |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | ce dépôt | Raccourcisseur de liens, codes QR et statistiques |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Pages de liens bio et thèmes |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Hébergement d'images et médias |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Notes privées chiffrées côté client |
| **FileHop** | `file.hel.ink` | [navi-crwn/FileHop](https://github.com/navi-crwn/FileHop) | Conversion de fichiers dans le navigateur ; rien n'est envoyé |

<a id="security"></a>

## Aide et support

Vous avez trouvé un bogue ou avez besoin d'aide ? Ouvrez un ticket sur <a href="https://github.com/navi-crwn/LinkHop/issues">GitHub Issues</a>ou soumettez une pull request.

---

<p align="center"><sub>LinkHop · membre de la famille HOP · <a href="https://link.hel.ink">link.hel.ink</a></sub></p>
