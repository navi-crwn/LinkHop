<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <b>Español</b> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/home-dark.webp"><img src="docs/readme/home-light.webp" alt="La página de inicio de LinkHop: un cuadro para pegar un enlace largo, el botón Shorten y la escena 3D de islas" width="100%"></picture></p>

<p align="center">
  <b>Enlaces cortos, códigos QR y analítica de clics.<br>Gratis, de código abierto y tuyo para alojar.</b>
</p>

<p align="center">
  <a href="https://link.hel.ink"><b>Prueba LinkHop</b></a> ·
  <a href="https://navi-crwn.github.io/LinkHop/">Sitio web</a> ·
  <a href="#instalación">Instalación</a> ·
  <a href="#api">API</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="CHANGELOG.md">Registro de cambios</a> ·
  <a href="CREDITS.md">Créditos</a> ·
  <a href="SECURITY.md">Seguridad</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2+"/>
  <img src="https://img.shields.io/badge/Tailwind-4.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4"/>
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License"/>
  <img src="https://img.shields.io/github/stars/navi-crwn/LinkHop?style=flat-square&color=yellow" alt="GitHub stars"/>
</p>

LinkHop acorta enlaces, cuenta los clics y crea un código QR para cada enlace. Es el acortador de enlaces de la familia HOP y funciona en [link.hel.ink](https://link.hel.ink). Los enlaces cortos se abren en `hel.ink/l/slug`.

Cualquiera puede acortar un enlace sin cuenta. Un HOP ID gratuito conserva tus enlaces y muestra sus estadísticas. HOP ID es la cuenta única de todas las apps HOP y vive en el [hub](https://github.com/navi-crwn/HEL.ink), en `hel.ink` y `hub.hel.ink`. LinkHop no tiene páginas propias de inicio de sesión, registro ni perfil: el hub te identifica y te devuelve con un ticket de corta duración, y las apps comparten una sola base de datos.

## Familia HOP

| App | Dirección | Repositorio | Qué hace |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · cuentas en `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | Una cuenta para todas las apps HOP. |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Enlaces cortos, códigos QR y analítica de clics. |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Una página de link en bio para todo lo que haces. |
| **PixelHop** | [pixel.hel.ink](https://pixel.hel.ink) · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Alojamiento de imágenes con álbumes y enlaces directos. |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Notas cifradas que compartes con un enlace. |
| **Imperium** | privado (solo el propietario) | privado | El panel de control de HOP. |

Todo está incluido en cada cuenta, sin plan de pago. El código tiene licencia MIT, así que también puedes ejecutarlo en tu propio servidor y con tu propio dominio.

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/dashboard-dark.webp"><img src="docs/readme/dashboard-light.webp" alt="El panel de LinkHop: clics de esta semana con una barra por día, totales, una lista para empezar, enlaces recientes y el enlace con mejor rendimiento" width="100%"></picture></p>

Las capturas de páginas con la sesión iniciada muestran datos de demostración inventados, no una cuenta real.

## Características

- Enlaces cortos con un slug aleatorio o personalizado (de 3 a 255 caracteres), contraseña opcional, fecha de caducidad y un código QR en PNG, SVG o JPG.
- Límites del enlace: una hora de inicio, un número máximo de clics, una dirección alternativa para cuando el enlace ha caducado o se han agotado los clics, y el tipo de redirección 301, 302, 307 o 308.
- Enrutamiento: hasta 20 reglas que envían a los visitantes a otra dirección según el país, el idioma, el dispositivo, el sistema operativo o el navegador, y una división A/B de 2 a 5 direcciones con pesos. Un generador de etiquetas de campaña (UTM) rellena los parámetros `utm_*`.
- Abrir en la app: en iOS y Android, un enlace puede probar primero la app instalada, con App Store o Google Play como alternativa.
- Analítica: clics, visitantes únicos, países y ciudades, dispositivos, navegadores, navegadores dentro de apps, referentes y escaneos de QR frente a clics directos. El periodo se compara con el anterior. Los registros de cada clic se conservan 30 días.
- Organización: carpetas, etiquetas, búsqueda, acciones en lote, comentarios en un enlace, importación de CSV de hasta 500 filas y una paleta de comandos con Ctrl o Cmd + K. En Ajustes hay una exportación CSV de tus enlaces con sus estadísticas.
- Comprobador de enlaces en `/check`: pega un enlace de `hel.ink` para ver su destino antes de abrirlo. No se hace clic ni se cuenta nada.
- Monitor de destinos: una vez al día LinkHop pregunta a cada destino activo si sigue respondiendo y envía un correo al propietario tras tres comprobaciones fallidas seguidas. Se puede desactivar en Ajustes.
- Aviso de contenido: un enlace puede marcarse como sensible o para adultos, y los visitantes ven un aviso antes de continuar.
- Protección contra abusos: límites de frecuencia y cuotas, una lista negra de dominios, bloqueos de IP, comprobaciones de proxy y VPN en los visitantes, un formulario de denuncia y un análisis cada hora en busca de phishing, estafas y otros enlaces dañinos, con consultas opcionales a Google Safe Browsing y URLhaus.
- API y webhooks: una API REST con tokens con ámbito y claves de idempotencia, webhooks firmados para cinco eventos de enlace y una configuración de ShareX.
- Nueve idiomas: English, 简体中文, Deutsch, Bahasa Indonesia, 日本語, Português (Brasil), Español, Français y Русский. Añade `?lang=es` a cualquier página para cambiar; la elección se guarda en una cookie.
- Privacidad: sin anuncios, sin rastreadores de terceros y sin scripts, fuentes ni imágenes de una CDN. Las bibliotecas van empaquetadas o están en `public/vendor`. Los dos hosts de terceros que quedan son Cloudflare Turnstile en el formulario de invitados y los mosaicos de OpenStreetMap bajo el mapa de analítica.
- Autoalojamiento: instalador de un solo comando, scripts de actualización y desinstalación, copias de seguridad nocturnas y una API de administración firmada para el centro de control de HOP.

Las páginas bio (`hel.ink/b/tu-nombre`) se crean en [BioHop](https://github.com/navi-crwn/BioHop), no en LinkHop. El único caso en que LinkHop todavía las sirve está en [Páginas bio](#páginas-bio).

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/analytics-dark.webp"><img src="docs/readme/analytics-light.webp" alt="La página Analytics: filtros, clics y visitantes únicos frente al periodo anterior, país y dispositivo principales, y un gráfico de clics a lo largo del tiempo" width="100%"></picture></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/links-dark.webp"><img src="docs/readme/links-light.webp" alt="La página My links: una lista con búsqueda, con carpetas, etiquetas, número de clics y una línea de siete días por enlace" width="100%"></picture></p>

## Dónde está cada cosa

| Qué | URL |
| --- | --- |
| Enlace corto | `hel.ink/l/slug` |
| Forma antigua del enlace corto | `hel.ink/slug`, que redirige a `hel.ink/l/slug` hasta el 27 de noviembre de 2026 (`LEGACY_SLUG_UNTIL`) |
| Página bio (BioHop) | `hel.ink/b/name` |
| Panel y API | `link.hel.ink`, con inicio de sesión a través del hub en `hub.hel.ink` |
| Tu cuenta | `hub.hel.ink`: perfil, contraseña, verificación en dos pasos, tokens de API, descarga de datos, eliminación de la cuenta |

Los enlaces cortos pasaron de `hel.ink/slug` a `hel.ink/l/slug` el 28 de octubre de 2026, para que `hel.ink` pueda alojar varias apps HOP. No se eliminó nada. El panel, los códigos QR, la API y la exportación de la cuenta muestran la forma nueva. En tu propia instalación el host público es `PUBLIC_URL` (por defecto `APP_URL`), y `php artisan links:announce-migration` envía por correo a cada propietario de enlaces sus enlaces en la forma nueva (`--reminder` envía el segundo correo).

## Instalación

El instalador revisa tu equipo, hace unas preguntas y puede configurar el hub HOP ID junto a LinkHop:

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
```

Desde un clon, ejecuta `./install.sh`. Para actualizar, ejecuta `./update.sh`; para quitar LinkHop, `./uninstall.sh`. Añade `--yes` para aceptar todos los valores por defecto, `--dry-run` para ver los pasos sin ejecutarlos y `--help` para el resto de opciones.

- `install.sh` necesita PHP 8.2 o superior (usa `php8.3` si está instalado; define `PHP_BIN` para elegir otro), Composer, Node.js 20 o superior y una base de datos: SQLite o MySQL/MariaDB. Escribe un `.env` privado y un `start.sh` que arranca el servidor web, el worker de la cola y el planificador.
- `update.sh` copia primero la base de datos a `backups/` (SQLite o MySQL/MariaDB), pone el sitio en modo de mantenimiento, descarga el código nuevo, recompila, migra y restaura la versión anterior si falla un paso antes de las migraciones. Si no puede hacer una copia de la base de datos, se detiene; `--no-backup` continúa sin copia.
- `uninstall.sh` elimina los paquetes instalados y los archivos compilados, y conserva tu `.env`, la base de datos, las subidas y los registros. `--purge` también los borra y te pide que escribas antes el nombre del sitio.

> [!NOTE]
> LinkHop guarda las cuentas en un hub HOP ID ([HEL.ink](https://github.com/navi-crwn/HEL.ink)); no existe un modo sin hub. El instalador puede configurar un hub junto a LinkHop (lo habitual, alrededor de un minuto) o conectarse a un hub que ya ejecutes. Para instalar el hub y varias apps de una vez, usa `install-family.sh` del repositorio HEL.ink.

### Instalación manual

Necesitas PHP 8.2 o superior con las extensiones habituales de Laravel y `gd` o `imagick` (códigos QR en PNG), Composer, Node.js 20.19 o superior y una base de datos. El [hub](https://github.com/navi-crwn/HEL.ink) debe funcionar sobre la misma base de datos: es el dueño de las tablas de cuentas (`users`, `api_tokens` y otras) y es quien inicia la sesión de las personas.

```bash
# Los paquetes compartidos hop/auth, hop/ui y hop/safety vienen de GitHub a través de Composer.
git clone https://github.com/navi-crwn/LinkHop.git
cd LinkHop
composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate          # después edita .env, véase más abajo
php artisan migrate --force       # solo las tablas de esta app
php artisan storage:link
php artisan serve --port=8001
```

Para trabajar en local, define `APP_ENV=local`, `APP_URL=http://localhost:8001`, `PUBLIC_URL=http://localhost:8001`, `HOP_HUB_URL=http://localhost:8000` (el hub con `php artisan serve`) y `SESSION_SECURE_COOKIE=false`, y en el hub `LINKHOP_URL=http://localhost:8001`. Un invitado que abre LinkHop es enviado al inicio de sesión del hub y vuelve con la sesión iniciada.

En producción, mantén en marcha dos cosas: un worker de la cola (`php artisan queue:work --tries=3`) y el planificador (una línea de cron `* * * * * cd /ruta/a/linkhop && php artisan schedule:run`). El planificador ejecuta el análisis de abusos, el monitor de destinos, los reintentos de webhooks, la limpieza de privacidad, el borrado de enlaces de invitados y la copia de seguridad.

**Propiedad de la base de datos.** Cada app HOP ejecuta solo sus propias migraciones sobre la base de datos compartida. Las de LinkHop están en `database/migrations`. El historial de las tablas de cuentas se conserva en `database/migrations-archive` y ya no se ejecuta; las pruebas construyen el esquema a partir de él. No ejecutes `migrate:fresh` contra una base de datos que use el hub.

<details>
<summary><b>Ajustes en <code>.env</code></b></summary>

El archivo `.env.example` lo enumera todo. Estos son los ajustes que más probablemente tocarás.

| Ajuste | Significado |
| --- | --- |
| `APP_URL` | El panel y la API (`https://link.hel.ink` en el servicio alojado). |
| `PUBLIC_URL` | Donde se accede a los enlaces cortos, los códigos QR y el sitemap (`https://hel.ink`). En una instalación con un solo host, usa el mismo valor que `APP_URL`. |
| `HOP_APP`, `HOP_HUB_URL`, `HOP_SSO_SECRET` | La conexión con el hub. `HOP_APP` es `linkhop`; el secreto es el mismo, de al menos 32 caracteres, que el del `.env` del hub. El inicio de sesión, el acceso con Google, la verificación en dos pasos y el restablecimiento de contraseña son del hub, así que aquí no hace falta configurar nada de eso. |
| `DB_*` | La misma base de datos que el hub. |
| `MAIL_*` | Correo saliente: avisos a los propietarios de enlaces, monitor de destinos y alertas de copias de seguridad. |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile en el acortador de invitados y en el formulario de denuncia. En producción, sin la clave secreta el formulario de invitados rechaza todos los envíos. |
| `GUEST_LINK_IDLE_DAYS`, `GUEST_LINK_MAX_DAYS` | Los enlaces de invitados se borran tras 14 días sin clics y, como máximo, a los 30 días. El servidor solo puede acortar estos plazos. |
| `LIMIT_*` | Cuotas de `config/limits.php`, por ejemplo 10 enlaces por minuto y 200 al día por dirección, 200 al día y 1000 enlaces activos por cuenta. |
| `SAFE_BROWSING_KEY`, `URLHAUS_AUTH_KEY` | Consultas opcionales para el análisis de abusos. |
| `GEOLITE_LICENSE_KEY` | Opcional. Sin ella, `geolite:update` descarga las bases de datos gratuitas DB-IP Lite. |
| `BIO_ENABLED`, `BIOHOP_URL`, `PIXELHOP_URL` | El paquete bio incluido y las direcciones de las otras apps. |
| `HOP_CONTROL_URL` | A dónde envía `/admin` a los administradores: el centro de control de HOP. |
| `LEGACY_SLUG_UNTIL` | La fecha en que termina la redirección antigua `hel.ink/slug`. Déjalo vacío en una instalación nueva. |
| `TRUSTED_PROXIES` | Las direcciones del proxy inverso, o `cloudflare`. |
| `SENTRY_LARAVEL_DSN`, `BACKUP_DISKS` | El seguimiento de errores y los discos en los que se escribe la copia de seguridad (por defecto `local`). |

**Ubicación de los visitantes.** El país y la ciudad salen de una base de datos local DB-IP Lite que `geolite:update` actualiza cada semana. Cuando no tiene respuesta para una dirección pública, LinkHop consulta hasta dos servicios web de localización: freeipapi.com, ip-api.com, ipapi.co, ipinfo.io o abstractapi.com. La dirección del visitante solo se guarda como un hash con clave.

**Copias de seguridad.** El planificador ejecuta `backup:run` todos los días a las 02:45. El archivo contiene la base de datos, `storage/app/public` y el `.env`, y se escribe en los discos de `BACKUP_DISKS`. Una copia de MySQL o MariaDB necesita `mysqldump` en el servidor. El `.env` guarda tus secretos, así que protege el disco de las copias o define `BACKUP_ARCHIVE_PASSWORD`.

**Detrás de Cloudflare.** LinkHop envía una Content Security Policy que solo confía en los scripts que llevan el nonce de la página. Email Address Obfuscation (Scrape Shield) y Rocket Loader (Speed, Optimization) de Cloudflare inyectan scripts propios sin ese nonce, por lo que los navegadores los bloquean y los bloqueos quedan en `storage/logs/csp-*.log`. Desactiva ambos en el panel de Cloudflare. LinkHop no los necesita. Añadirlos a la política no funcionaría con una política de nonce, porque `'strict-dynamic'` ignora las listas de hosts permitidos.

**Un solo host.** El servicio alojado divide LinkHop en dos: el panel en `link.hel.ink` y los enlaces cortos en `hel.ink/l/`. Las reglas de nginx para esa división están en el repositorio del hub, en `deploy/nginx/`. Para un solo host, define `APP_URL` y `PUBLIC_URL` con el mismo valor.

</details>

## Uso

**Acortar un enlace.** Pega la dirección larga, elige opcionalmente un slug, una contraseña o una fecha de caducidad, pulsa Shorten y copia el resultado. Abre Advanced en la página de inicio para las opciones de invitado; tras iniciar sesión, New link ofrece todas las opciones anteriores.

**Páginas bio.** Inicia sesión en [bio.hel.ink](https://bio.hel.ink) con el mismo HOP ID, elige un nombre y comparte `hel.ink/b/tunombre`.

## API

Los tokens de API se crean en el hub (HOP ID, Settings, API) y funcionan en todas las apps HOP. LinkHop los lee de la tabla compartida `api_tokens`. Un token lleva ámbitos (scopes): `link:create`, `link:read` y `link:delete`. Un token creado antes de que existieran los ámbitos no tiene ninguno y puede hacerlo todo. La API responde en `link.hel.ink/api` y en `hel.ink/api`, que reenvía allí hasta el 27 de noviembre de 2026. El límite de frecuencia es por token, 100 solicitudes por hora salvo que el token fije el suyo.

Crear un enlace corto (`link:create`). BioHop hace esta llamada cuando un bloque bio quiere un enlace corto.

```bash
curl -X POST https://link.hel.ink/api/shorten \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com", "alias": "my-link"}'
```

| Campo | | |
| --- | --- | --- |
| `url` | obligatorio | Una dirección completa con `http://` o `https://`, de hasta 2048 caracteres. Se rechaza cuando la comprobación de seguridad la marca. |
| `alias` | opcional | El slug: de 3 a 255 caracteres de `A-Z a-z 0-9 _ -`. Las palabras reservadas y los slugs ya usados se rechazan. Aleatorio si se omite. |
| `folder` | opcional | Un nombre de carpeta de la cuenta. Se crea si no existe. |
| `password` | opcional | De 4 a 50 caracteres; los visitantes deben escribirla. |
| `expires_at` | opcional | Una fecha y hora ISO en el futuro. |
| `starts_at` | opcional | Una fecha y hora antes de la cual los visitantes ven "Not live yet". |
| `max_clicks` | opcional | El enlace se detiene tras este número de clics (de 1 a 10.000.000). |
| `fallback_url` | opcional | A dónde van los visitantes tras la fecha de caducidad o el límite de clics. No puede ser otro enlace corto. |
| `redirect_type` | opcional | `301`, `302` (el valor por defecto), `307` o `308`. |
| `content_warning`, `warning_reason` | opcional | `none`, `sensitive` o `adult`, y una nota de hasta 255 caracteres que ven los visitantes. |
| `routing_rules`, `ab_variants`, `app_links` | opcional | Enrutamiento por visitante, una división A/B y deep links. Los formatos están en la página API docs del panel. |

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

Los errores mantienen siempre la misma forma, `{"success": false, "error": "…", "message": "…"}`. `401` es un token desconocido o caducado, `403` un ámbito que falta o una cuenta suspendida o sin verificar, `422` un error de validación (`messages` enumera los campos) y `429` una cuota o un límite de frecuencia.

Envía una cabecera `Idempotency-Key` con `POST /api/shorten` para reintentar con seguridad. La misma clave con el mismo cuerpo devuelve la primera respuesta (`Idempotent-Replayed: true`) y no crea nada dos veces. La misma clave con otro cuerpo da `422`. Las claves se conservan 24 horas.

Lee un enlace (`link:read`) con `GET /api/links/{slug}`, que devuelve el mismo `data` más `clicks`. Elimina uno (`link:delete`) con `DELETE /api/links/{slug}`. Ambos responden `404` ante un slug que no está en la cuenta del token.

Los webhooks se configuran en Ajustes: hasta cinco direcciones https, cada una llamada en `link.created`, `link.updated`, `link.deleted`, `link.limit_reached` y `link.expired`. Cada mensaje lleva `X-LinkHop-Signature`, un HMAC-SHA256 de la marca de tiempo y el cuerpo. Una entrega fallida se reintenta tras 1 minuto, 5 minutos, 30 minutos y 2 horas, y un webhook que falla diez entregas seguidas se desactiva.

Una configuración de ShareX puede descargarse desde la página API docs del panel.

## FAQ

**¿Es gratis?** Sí. No hay plan de pago ni funciones bloqueadas.

**¿Necesito una cuenta?** No. Cualquiera puede acortar un enlace sin cuenta. Los enlaces de invitados se borran tras 14 días sin clics y, como máximo, a los 30 días. Un HOP ID gratuito conserva tus enlaces, muestra sus estadísticas y te da acceso también a las demás apps HOP.

**¿Dónde está mi página bio?** En BioHop, en `hel.ink/b/` seguido del nombre que elijas. Edítala en [bio.hel.ink](https://bio.hel.ink).

**¿Qué registra un clic?** La hora, el país y la ciudad, el dispositivo, el navegador y el referente, además de un hash con clave de la dirección y el navegador que cambia cada día y sirve para contar visitantes únicos. La dirección en sí no se guarda, y los registros de clics se borran a los 30 días.

**¿Cómo denuncio un enlace malo?** Usa el formulario de denuncia en `/report-abuse`. Las denuncias van a la cola de abusos del centro de control de HOP, junto a lo que detecta el análisis automático.

**¿Puedo usar mi propio dominio?** Sí, alojando tu propia copia junto al hub. `APP_URL` es el host del panel, `PUBLIC_URL` el host de los enlaces cortos y `HOP_HUB_URL` el hub.

### Páginas bio

En hel.ink, las páginas bio son de BioHop. Una instalación en un solo servidor sin BioHop todavía puede servirlas desde el paquete `hop/bio` incluido (`packages/bio`) definiendo `BIO_ENABLED=true`. Con BioHop al lado, déjalo en `false`: entonces no registra rutas y no necesita tablas. `php artisan db:seed --class=ExampleBioSeeder` crea en una instalación de desarrollo una página bio de ejemplo y algunos enlaces cortos de ejemplo.

## Por dentro

| Capa | Tecnologías |
| --- | --- |
| Backend | Laravel 12, PHP 8.2 o superior, SQLite o MySQL/MariaDB |
| Frontend | Tailwind CSS 4, Alpine.js 3, Vite 7, y GSAP, Lenis y Three.js para la página de inicio |
| Gráficos y mapas | Chart.js 4, Leaflet 1.9 con mosaicos de OpenStreetMap |
| Cuentas | HOP ID en el hub, mediante `hop/auth` (cliente SSO y los middleware `hop.auth` y `hop.token`) |
| Paquetes compartidos | `hop/auth`, `hop/ui` (selector de apps, fuentes), `hop/safety` (seguridad de URL y contenido), `hop/bio` (incluido en `packages/bio`) |
| Servicios | Cloudflare Turnstile, DB-IP Lite y servicios web de consulta opcionales para la ubicación de los visitantes, Google Safe Browsing y URLhaus opcionales, Sentry |

| Carpeta | Qué contiene |
| --- | --- |
| `app/`, `routes/`, `resources/` | La app Laravel: controladores, modelos, vistas Blade, scripts y estilos. |
| `config/hop.php`, `config/limits.php`, `config/shortener.php` | La lista de la familia HOP (la misma lista `apps` que la del hub), las cuotas de enlaces y los ajustes de enlaces de invitados, slugs y monitor. Los patrones y umbrales de seguridad vienen de `hop/safety` (`config('safety')`). |
| `database/migrations/`, `database/migrations-archive/` | Las tablas propias de LinkHop y el historial de las tablas de cuentas (ahora del hub, no se ejecuta). |
| `lang/` | Los nueve archivos de idioma. |
| `tests/` | Pruebas Pest y PHPUnit (`php artisan test`) y las pruebas de navegador con usuarios sintéticos en `tests/e2e/` ([cómo ejecutarlas](tests/e2e/README.md)). |
| `docs/` | El sitio web en [navi-crwn.github.io/LinkHop](https://navi-crwn.github.io/LinkHop/). `docs/readme/` guarda las capturas del README; `scripts/readme-images.mjs` las vuelve a tomar. |
| `scripts/` | Utilidades: `build-changelog.py` (el registro de cambios dentro de la app), `check-hop-family.sh` (la tabla de la familia en el README en inglés) y `readme-images.mjs`. |

Todas las dependencias y sus licencias están en [CREDITS.md](CREDITS.md).

## Contribuir

1. Haz un fork del repositorio y crea una rama.
2. Haz tu cambio y ejecuta `vendor/bin/pint` y `php artisan test`.
3. Haz push y abre un pull request contra `main`.

Añade pruebas para el comportamiento nuevo. Comunica los problemas de seguridad en privado, como se describe en [SECURITY.md](SECURITY.md), y usa las plantillas de issues para todo lo demás.

## Licencia

MIT. Consulta [LICENSE](LICENSE).

---

<p align="center">Creado por <a href="https://github.com/navi-crwn">Ivan Novskies (@navi-crwn)</a> · <a href="https://link.hel.ink">LinkHop</a> · <a href="https://navi-crwn.github.io/LinkHop/">Sitio web</a></p>
