<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <b>Français</b> · <a href="README.ru.md">Русский</a></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/home-dark.webp"><img src="docs/readme/home-light.webp" alt="La page d’accueil de LinkHop : un champ pour coller un lien long, le bouton Shorten et la scène 3D d’îles" width="100%"></picture></p>

<p align="center">
  <b>Liens courts, codes QR et statistiques de clics.<br>Gratuit, open source et à héberger chez vous.</b>
</p>

<p align="center">
  <a href="https://link.hel.ink"><b>Essayer LinkHop</b></a> ·
  <a href="https://navi-crwn.github.io/LinkHop/">Site web</a> ·
  <a href="#installation">Installation</a> ·
  <a href="#api">API</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="CHANGELOG.md">Journal des modifications</a> ·
  <a href="CREDITS.md">Crédits</a> ·
  <a href="SECURITY.md">Sécurité</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2+"/>
  <img src="https://img.shields.io/badge/Tailwind-4.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4"/>
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License"/>
  <img src="https://img.shields.io/github/stars/navi-crwn/LinkHop?style=flat-square&color=yellow" alt="GitHub stars"/>
</p>

LinkHop raccourcit les liens, compte les clics et crée un code QR pour chaque lien. C’est le raccourcisseur de liens de la famille HOP, et il tourne sur [link.hel.ink](https://link.hel.ink). Les liens courts s’ouvrent sur `hel.ink/l/slug`.

N’importe qui peut raccourcir un lien sans compte. Un HOP ID gratuit conserve vos liens et affiche leurs statistiques. HOP ID est le compte unique de toutes les applications HOP ; il est tenu par le [hub](https://github.com/navi-crwn/HEL.ink), sur `hel.ink` et `hub.hel.ink`. LinkHop n’a pas de pages de connexion, d’inscription ou de profil : le hub vous connecte et vous renvoie avec un ticket de courte durée, et les applications partagent une seule base de données.

## Famille HOP

| Application | Adresse | Dépôt | Ce qu’elle fait |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · comptes sur `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | Un compte pour toutes les applications HOP. |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Liens courts, codes QR et statistiques de clics. |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Une page link-in-bio pour tout ce que vous faites. |
| **PixelHop** | [pixel.hel.ink](https://pixel.hel.ink) · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Hébergement d’images avec albums et liens directs. |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Notes chiffrées que vous partagez avec un lien. |
| **Imperium** | privé (propriétaire seulement) | privé | Le panneau de contrôle HOP. |

Tout est inclus pour chaque compte, sans offre payante. Le code est sous licence MIT : vous pouvez aussi le faire tourner sur votre propre serveur et votre propre domaine.

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/dashboard-dark.webp"><img src="docs/readme/dashboard-light.webp" alt="Le tableau de bord LinkHop : clics de la semaine avec une barre par jour, totaux, une liste pour bien démarrer, liens récents et lien le plus performant" width="100%"></picture></p>

Les captures des pages après connexion montrent des données de démonstration inventées, pas un vrai compte.

## Fonctionnalités

- Des liens courts avec un slug aléatoire ou personnalisé (de 3 à 255 caractères), un mot de passe facultatif, une date d’expiration, et un code QR en PNG, SVG ou JPG.
- Des limites par lien : une heure de début, un nombre maximal de clics, une adresse de repli quand le lien a expiré ou que les clics sont épuisés, et un type de redirection 301, 302, 307 ou 308.
- Du routage : jusqu’à 20 règles qui envoient les visiteurs ailleurs selon le pays, la langue, l’appareil, le système d’exploitation ou le navigateur, et une répartition A/B de 2 à 5 adresses avec des poids. Un générateur d’étiquettes de campagne (UTM) remplit les paramètres `utm_*`.
- Ouvrir dans l’application : sur iOS et Android, un lien peut d’abord essayer l’application installée, avec l’App Store ou Google Play en solution de repli.
- Statistiques : clics, visiteurs uniques, pays et villes, appareils, navigateurs, navigateurs intégrés aux applications, référents, et scans de QR comparés aux clics directs. La période est comparée à la précédente. Les journaux de chaque clic sont conservés 30 jours.
- Organisation : dossiers, étiquettes, recherche, actions groupées, commentaires sur un lien, import CSV jusqu’à 500 lignes, et une palette de commandes Ctrl ou Cmd + K. Les Paramètres proposent un export CSV de vos liens avec leurs statistiques.
- Vérification de lien sur `/check` : collez un lien `hel.ink` pour voir sa destination avant de l’ouvrir. Rien n’est cliqué ni compté.
- Surveillance des destinations : une fois par jour, LinkHop demande à chaque destination active si elle répond encore, et envoie un e-mail au propriétaire après trois vérifications échouées de suite. On peut la désactiver dans les Paramètres.
- Avertissement de contenu : un lien peut être marqué comme sensible ou réservé aux adultes, et les visiteurs voient un avis avant de continuer.
- Protection contre les abus : limites de débit et quotas, liste noire de domaines, blocages d’IP, vérifications de proxy et de VPN chez les visiteurs, formulaire de signalement, et une analyse toutes les heures à la recherche d’hameçonnage, d’arnaques et d’autres liens nuisibles, avec des requêtes facultatives à Google Safe Browsing et URLhaus.
- API et webhooks : une API REST avec des jetons à portée limitée et des clés d’idempotence, des webhooks signés pour cinq événements de lien, et une configuration ShareX.
- Neuf langues : English, 简体中文, Deutsch, Bahasa Indonesia, 日本語, Português (Brasil), Español, Français et Русский. Ajoutez `?lang=fr` à n’importe quelle page pour changer ; le choix est conservé dans un cookie.
- Confidentialité : pas de publicité, pas de traqueur tiers, et aucun script, police ou image provenant d’un CDN. Les bibliothèques sont intégrées ou placées dans `public/vendor`. Les deux hôtes tiers restants sont Cloudflare Turnstile sur le formulaire invité et les tuiles OpenStreetMap sous la carte des statistiques.
- Auto-hébergement : un installateur en une commande, des scripts de mise à jour et de désinstallation, des sauvegardes nocturnes, et une API d’administration signée pour le centre de contrôle HOP.

Les pages bio (`hel.ink/b/vous`) se créent dans [BioHop](https://github.com/navi-crwn/BioHop), pas dans LinkHop. Le seul cas où LinkHop les sert encore est décrit dans [Pages bio](#pages-bio).

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/analytics-dark.webp"><img src="docs/readme/analytics-light.webp" alt="La page Analytics : filtres, clics et visiteurs uniques comparés à la période précédente, pays et appareil principaux, et un graphique des clics dans le temps" width="100%"></picture></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/links-dark.webp"><img src="docs/readme/links-light.webp" alt="La page My links : une liste avec recherche, avec dossiers, étiquettes, nombre de clics et une courbe de sept jours pour chaque lien" width="100%"></picture></p>

## Où se trouve quoi

| Quoi | URL |
| --- | --- |
| Lien court | `hel.ink/l/slug` |
| Ancienne forme du lien court | `hel.ink/slug`, qui redirige vers `hel.ink/l/slug` jusqu’au 27 novembre 2026 (`LEGACY_SLUG_UNTIL`) |
| Page bio (BioHop) | `hel.ink/b/name` |
| Tableau de bord et API | `link.hel.ink`, avec connexion par le hub sur `hub.hel.ink` |
| Votre compte | `hub.hel.ink` : profil, mot de passe, double authentification, jetons d’API, téléchargement des données, suppression du compte |

Les liens courts sont passés de `hel.ink/slug` à `hel.ink/l/slug` le 28 octobre 2026, pour que `hel.ink` puisse accueillir plusieurs applications HOP. Rien n’a été supprimé. Le tableau de bord, les codes QR, l’API et l’export du compte affichent la nouvelle forme. Sur votre propre installation, l’hôte public est `PUBLIC_URL` (par défaut `APP_URL`), et `php artisan links:announce-migration` envoie à chaque propriétaire de liens un e-mail avec ses liens sous la nouvelle forme (`--reminder` envoie le second e-mail).

## Installation

L’installateur vérifie votre ordinateur, pose quelques questions et peut mettre en place le hub HOP ID à côté de LinkHop :

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
```

Depuis un clone, lancez `./install.sh`. Pour mettre à jour, lancez `./update.sh` ; pour retirer LinkHop, `./uninstall.sh`. Ajoutez `--yes` pour accepter toutes les valeurs par défaut, `--dry-run` pour voir les étapes sans les exécuter, et `--help` pour les autres options.

- `install.sh` demande PHP 8.2 ou plus récent (il utilise `php8.3` quand il est installé ; définissez `PHP_BIN` pour en choisir un autre), Composer, Node.js 20 ou plus récent, et une base de données : SQLite ou MySQL/MariaDB. Il écrit un `.env` privé et un `start.sh` qui lance le serveur web, le worker de file d’attente et le planificateur.
- `update.sh` copie d’abord la base de données dans `backups/` (SQLite ou MySQL/MariaDB), met le site en mode maintenance, récupère le nouveau code, recompile, migre, et remet l’ancienne version si une étape échoue avant les migrations. S’il ne peut pas faire de copie de la base, il s’arrête ; `--no-backup` continue sans copie.
- `uninstall.sh` supprime les paquets installés et les fichiers compilés, et conserve votre `.env`, la base de données, les envois et les journaux. `--purge` supprime aussi ces éléments et vous demande d’abord de taper le nom du site.

> [!NOTE]
> LinkHop garde les comptes dans un hub HOP ID ([HEL.ink](https://github.com/navi-crwn/HEL.ink)) ; il n’existe pas de mode sans hub. L’installateur peut mettre en place un hub à côté de LinkHop (le choix par défaut, environ une minute) ou se connecter à un hub que vous faites déjà tourner. Pour installer le hub et plusieurs applications d’un coup, utilisez `install-family.sh` du dépôt HEL.ink.

### Installation manuelle

Il vous faut PHP 8.2 ou plus récent avec les extensions habituelles de Laravel et `gd` ou `imagick` (codes QR PNG), Composer, Node.js 20.19 ou plus récent, et une base de données. Le [hub](https://github.com/navi-crwn/HEL.ink) doit tourner sur la même base : il possède les tables de comptes (`users`, `api_tokens` et d’autres) et connecte les gens.

```bash
# Les paquets partagés hop/auth, hop/ui et hop/safety viennent de GitHub par Composer.
git clone https://github.com/navi-crwn/LinkHop.git
cd LinkHop
composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate          # puis modifiez .env, voir plus bas
php artisan migrate --force       # uniquement les tables de cette application
php artisan storage:link
php artisan serve --port=8001
```

Pour travailler en local, définissez `APP_ENV=local`, `APP_URL=http://localhost:8001`, `PUBLIC_URL=http://localhost:8001`, `HOP_HUB_URL=http://localhost:8000` (le hub avec `php artisan serve`) et `SESSION_SECURE_COOKIE=false`, et dans le hub `LINKHOP_URL=http://localhost:8001`. Un invité qui ouvre LinkHop est envoyé vers la connexion du hub et revient connecté.

En production, gardez deux choses en marche : un worker de file d’attente (`php artisan queue:work --tries=3`) et le planificateur (une ligne cron `* * * * * cd /chemin/vers/linkhop && php artisan schedule:run`). Le planificateur exécute l’analyse des abus, la surveillance des destinations, les nouvelles tentatives de webhooks, le nettoyage lié à la confidentialité, la suppression des liens invités et la sauvegarde.

**Propriété de la base de données.** Chaque application HOP n’exécute que ses propres migrations sur la base partagée. Celles de LinkHop sont dans `database/migrations`. L’historique des tables de comptes est conservé dans `database/migrations-archive` et n’est plus exécuté ; les tests construisent le schéma à partir de lui. N’exécutez pas `migrate:fresh` sur une base que le hub utilise.

<details>
<summary><b>Réglages dans <code>.env</code></b></summary>

Le fichier `.env.example` liste tout. Voici les réglages que vous êtes le plus susceptible de modifier.

| Réglage | Signification |
| --- | --- |
| `APP_URL` | Le tableau de bord et l’API (`https://link.hel.ink` sur le service hébergé). |
| `PUBLIC_URL` | L’adresse où l’on atteint les liens courts, les codes QR et le sitemap (`https://hel.ink`). Pour une installation à hôte unique, la même valeur que `APP_URL`. |
| `HOP_APP`, `HOP_HUB_URL`, `HOP_SSO_SECRET` | La connexion au hub. `HOP_APP` vaut `linkhop` ; le secret est le même, d’au moins 32 caractères, que dans le `.env` du hub. La connexion, l’accès par Google, la double authentification et la réinitialisation du mot de passe relèvent du hub : rien à configurer ici pour cela. |
| `DB_*` | La même base de données que le hub. |
| `MAIL_*` | Le courrier sortant : avis aux propriétaires de liens, surveillance des destinations et alertes de sauvegarde. |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile sur le raccourcisseur invité et le formulaire de signalement. En production, sans clé secrète, le formulaire invité refuse tout envoi. |
| `GUEST_LINK_IDLE_DAYS`, `GUEST_LINK_MAX_DAYS` | Les liens invités sont supprimés après 14 jours sans clic, et après 30 jours au plus. Le serveur ne peut que raccourcir ces délais. |
| `LIMIT_*` | Les quotas de `config/limits.php`, par exemple 10 liens par minute et 200 par jour par adresse, 200 par jour et 1000 liens actifs par compte. |
| `SAFE_BROWSING_KEY`, `URLHAUS_AUTH_KEY` | Requêtes facultatives pour l’analyse des abus. |
| `GEOLITE_LICENSE_KEY` | Facultatif. Sans elle, `geolite:update` télécharge les bases gratuites DB-IP Lite. |
| `BIO_ENABLED`, `BIOHOP_URL`, `PIXELHOP_URL` | Le paquet bio inclus et les adresses des autres applications. |
| `HOP_CONTROL_URL` | Où `/admin` envoie les administrateurs : le centre de contrôle HOP. |
| `LEGACY_SLUG_UNTIL` | La date à laquelle l’ancienne redirection `hel.ink/slug` s’arrête. À laisser vide sur une nouvelle installation. |
| `TRUSTED_PROXIES` | Les adresses du proxy inverse, ou `cloudflare`. |
| `SENTRY_LARAVEL_DSN`, `BACKUP_DISKS` | Le suivi d’erreurs, et les disques où la sauvegarde est écrite (`local` par défaut). |

**Localisation des visiteurs.** Le pays et la ville viennent d’une base locale DB-IP Lite que `geolite:update` actualise chaque semaine. Quand elle n’a pas de réponse pour une adresse publique, LinkHop interroge au plus deux services web de géolocalisation : freeipapi.com, ip-api.com, ipapi.co, ipinfo.io ou abstractapi.com. L’adresse du visiteur n’est conservée que sous forme de hachage à clé.

**Sauvegardes.** Le planificateur exécute `backup:run` tous les jours à 02:45. L’archive contient la base de données, `storage/app/public` et le `.env`, et part vers les disques de `BACKUP_DISKS`. Une sauvegarde MySQL ou MariaDB demande `mysqldump` sur le serveur. Le `.env` contient vos secrets : protégez le disque de sauvegarde ou définissez `BACKUP_ARCHIVE_PASSWORD`.

**Derrière Cloudflare.** LinkHop envoie une Content Security Policy qui ne fait confiance qu’aux scripts portant le nonce de la page. Email Address Obfuscation (Scrape Shield) et Rocket Loader (Speed, Optimization) de Cloudflare injectent leurs propres scripts sans ce nonce, si bien que les navigateurs les bloquent et que les blocages sont écrits dans `storage/logs/csp-*.log`. Désactivez les deux dans le tableau de bord Cloudflare. LinkHop n’en a pas besoin. Les ajouter à la politique ne marcherait pas avec une politique à nonce, car `'strict-dynamic'` ignore les listes d’hôtes autorisés.

**Hôte unique.** Le service hébergé sépare LinkHop en deux : le tableau de bord sur `link.hel.ink` et les liens courts sur `hel.ink/l/`. Les règles nginx de cette séparation sont dans le dépôt du hub, sous `deploy/nginx/`. Pour un hôte unique, donnez la même valeur à `APP_URL` et `PUBLIC_URL`.

</details>

## Utilisation

**Raccourcir un lien.** Collez l’adresse longue, choisissez si vous le souhaitez un slug, un mot de passe ou une date d’expiration, appuyez sur Shorten et copiez le résultat. Ouvrez Advanced sur la page d’accueil pour les options invité ; une fois connecté, New link offre toutes les options ci-dessus.

**Pages bio.** Connectez-vous à [bio.hel.ink](https://bio.hel.ink) avec le même HOP ID, choisissez un nom et partagez `hel.ink/b/votrenom`.

## API

Les jetons d’API se créent dans le hub (HOP ID, Settings, API) et fonctionnent dans toutes les applications HOP. LinkHop les lit dans la table partagée `api_tokens`. Un jeton porte des portées (scopes) : `link:create`, `link:read` et `link:delete`. Un jeton créé avant l’existence des portées n’en a aucune et peut tout faire. L’API répond sur `link.hel.ink/api`, et sur `hel.ink/api`, qui y renvoie jusqu’au 27 novembre 2026. La limite de débit est par jeton, 100 requêtes par heure sauf si le jeton fixe la sienne.

Créer un lien court (`link:create`). BioHop fait cet appel quand un bloc bio veut un lien court.

```bash
curl -X POST https://link.hel.ink/api/shorten \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com", "alias": "my-link"}'
```

| Champ | | |
| --- | --- | --- |
| `url` | obligatoire | Une adresse complète en `http://` ou `https://`, jusqu’à 2048 caractères. Refusée quand le contrôle de sécurité la signale. |
| `alias` | facultatif | Le slug : de 3 à 255 caractères parmi `A-Z a-z 0-9 _ -`. Les mots réservés et les slugs déjà pris sont refusés. Aléatoire si omis. |
| `folder` | facultatif | Un nom de dossier du compte. Il est créé s’il n’existe pas. |
| `password` | facultatif | De 4 à 50 caractères ; les visiteurs doivent le saisir. |
| `expires_at` | facultatif | Une date-heure ISO dans le futur. |
| `starts_at` | facultatif | Une date-heure avant laquelle les visiteurs voient « Not live yet ». |
| `max_clicks` | facultatif | Le lien s’arrête après ce nombre de clics (de 1 à 10 000 000). |
| `fallback_url` | facultatif | Où vont les visiteurs après la date d’expiration ou la limite de clics. Ne peut pas être un autre lien court. |
| `redirect_type` | facultatif | `301`, `302` (par défaut), `307` ou `308`. |
| `content_warning`, `warning_reason` | facultatif | `none`, `sensitive` ou `adult`, et une note de 255 caractères au plus montrée aux visiteurs. |
| `routing_rules`, `ab_variants`, `app_links` | facultatif | Routage selon le visiteur, répartition A/B et liens profonds. Les formats sont sur la page API docs du tableau de bord. |

```json
HTTP 201
{"success": true,
 "data": {"url": "https://hel.ink/l/my-link", "slug": "my-link", "target_url": "https://example.com",
          "status": "active", "content_warning": "none", "warning_reason": null,
          "created_at": "2026-11-02T10:00:00+00:00", "expires_at": null, "starts_at": null,
          "max_clicks": null, "fallback_url": null, "routing_rules": [], "ab_variants": [],
          "app_links": null, "redirect_type": "302"},
 "message": "Link created successfully"}
```

Les erreurs gardent toujours la même forme, `{"success": false, "error": "…", "message": "…"}`. `401` signifie jeton inconnu ou expiré, `403` portée manquante ou compte suspendu ou non vérifié, `422` erreur de validation (`messages` liste les champs) et `429` quota ou limite de débit.

Envoyez un en-tête `Idempotency-Key` avec `POST /api/shorten` pour réessayer sans risque. La même clé avec le même corps renvoie la première réponse (`Idempotent-Replayed: true`) et ne crée rien en double. La même clé avec un autre corps donne `422`. Les clés sont conservées 24 heures.

Lisez un lien (`link:read`) avec `GET /api/links/{slug}`, qui renvoie le même `data` plus `clicks`. Supprimez-en un (`link:delete`) avec `DELETE /api/links/{slug}`. Les deux répondent `404` pour un slug qui n’est pas sur le compte du jeton.

Les webhooks se configurent dans les Paramètres : jusqu’à cinq adresses https, chacune appelée pour `link.created`, `link.updated`, `link.deleted`, `link.limit_reached` et `link.expired`. Chaque message porte `X-LinkHop-Signature`, un HMAC-SHA256 de l’horodatage et du corps. Une livraison échouée est retentée après 1 minute, 5 minutes, 30 minutes et 2 heures, et un webhook qui échoue dix livraisons de suite est désactivé.

Une configuration ShareX peut être téléchargée depuis la page API docs du tableau de bord.

## FAQ

**Est-ce gratuit ?** Oui. Il n’y a pas d’offre payante ni de fonctionnalité verrouillée.

**Ai-je besoin d’un compte ?** Non. N’importe qui peut raccourcir un lien sans compte. Les liens invités sont supprimés après 14 jours sans clic, et après 30 jours au plus. Un HOP ID gratuit conserve vos liens, affiche leurs statistiques et vous connecte aussi aux autres applications HOP.

**Où est ma page bio ?** Dans BioHop, à `hel.ink/b/` suivi du nom que vous choisissez. Modifiez-la sur [bio.hel.ink](https://bio.hel.ink).

**Que garde un clic en mémoire ?** L’heure, le pays et la ville, l’appareil, le navigateur et le référent, plus un hachage à clé de l’adresse et du navigateur qui change chaque jour et sert à compter les visiteurs uniques. L’adresse elle-même n’est pas conservée, et les journaux de clics sont supprimés après 30 jours.

**Comment signaler un mauvais lien ?** Utilisez le formulaire de signalement sur `/report-abuse`. Les signalements vont dans la file des abus du centre de contrôle HOP, à côté des résultats de l’analyse automatique.

**Puis-je utiliser mon propre domaine ?** Oui, en hébergeant votre propre copie à côté du hub. `APP_URL` est l’hôte du tableau de bord, `PUBLIC_URL` l’hôte des liens courts et `HOP_HUB_URL` le hub.

### Pages bio

Sur hel.ink, les pages bio appartiennent à BioHop. Une installation sur un seul serveur sans BioHop peut encore les servir depuis le paquet `hop/bio` inclus (`packages/bio`) en définissant `BIO_ENABLED=true`. Avec BioHop à côté, laissez `false` : le paquet n’enregistre alors aucune route et n’a besoin d’aucune table. `php artisan db:seed --class=ExampleBioSeeder` crée, sur une installation de développement, une page bio d’exemple et quelques liens courts d’exemple.

## Sous le capot

| Couche | Technologies |
| --- | --- |
| Backend | Laravel 12, PHP 8.2 ou plus récent, SQLite ou MySQL/MariaDB |
| Frontend | Tailwind CSS 4, Alpine.js 3, Vite 7, ainsi que GSAP, Lenis et Three.js pour la page d’accueil |
| Graphiques et cartes | Chart.js 4, Leaflet 1.9 avec des tuiles OpenStreetMap |
| Comptes | HOP ID dans le hub, via `hop/auth` (client SSO et middlewares `hop.auth` et `hop.token`) |
| Paquets partagés | `hop/auth`, `hop/ui` (sélecteur d’applications, polices), `hop/safety` (sécurité des URL et du contenu), `hop/bio` (inclus dans `packages/bio`) |
| Services | Cloudflare Turnstile, DB-IP Lite et services web de requête facultatifs pour la localisation des visiteurs, Google Safe Browsing et URLhaus facultatifs, Sentry |

| Dossier | Contenu |
| --- | --- |
| `app/`, `routes/`, `resources/` | L’application Laravel : contrôleurs, modèles, vues Blade, scripts et styles. |
| `config/hop.php`, `config/limits.php`, `config/shortener.php` | La liste de la famille HOP (la même liste `apps` que celle du hub), les quotas de liens et les réglages des liens invités, des slugs et de la surveillance. Les motifs et seuils de sécurité viennent de `hop/safety` (`config('safety')`). |
| `database/migrations/`, `database/migrations-archive/` | Les tables propres à LinkHop, et l’historique des tables de comptes (désormais celles du hub, non exécuté). |
| `lang/` | Les neuf fichiers de langue. |
| `tests/` | Tests Pest et PHPUnit (`php artisan test`) et tests de navigateur avec utilisateurs synthétiques dans `tests/e2e/` ([comment les lancer](tests/e2e/README.md)). |
| `docs/` | Le site web sur [navi-crwn.github.io/LinkHop](https://navi-crwn.github.io/LinkHop/). `docs/readme/` contient les captures du README ; `scripts/readme-images.mjs` les refait. |
| `scripts/` | Utilitaires : `build-changelog.py` (le journal des modifications dans l’application), `check-hop-family.sh` (le tableau de la famille dans le README anglais) et `readme-images.mjs`. |

Toutes les dépendances et leurs licences figurent dans [CREDITS.md](CREDITS.md).

## Contribuer

1. Faites un fork du dépôt et créez une branche.
2. Faites votre modification, puis lancez `vendor/bin/pint` et `php artisan test`.
3. Poussez et ouvrez une pull request vers `main`.

Ajoutez des tests pour tout nouveau comportement. Signalez les problèmes de sécurité en privé, comme décrit dans [SECURITY.md](SECURITY.md), et utilisez les modèles d’issues pour le reste.

## Licence

MIT. Voir [LICENSE](LICENSE).

---

<p align="center">Créé par <a href="https://github.com/navi-crwn">Ivan Novskies (@navi-crwn)</a> · <a href="https://link.hel.ink">LinkHop</a> · <a href="https://navi-crwn.github.io/LinkHop/">Site web</a></p>
