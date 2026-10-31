<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh.md">简体中文</a> · <b>Español</b> · <a href="README.ar.md">العربية</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.ja.md">日本語</a> · <a href="README.id.md">Bahasa Indonesia</a></p>

# LinkHop — LinkHop · Enlaces cortos, códigos QR y analítica de clics

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/LinkHop/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/LinkHop/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="LinkHop · Acortador de enlaces HOP" width="100%"></picture></p>

<p align="center"><b>Acorta enlaces, cuenta cada clic, analiza a tus visitantes por país y dispositivo, y protégelos con detección automática de amenazas.</b></p>

<p align="center">
  <a href="https://link.hel.ink">link.hel.ink</a> ·
  <a href="#run">Instálalo tú mismo</a> ·
  <a href="#features">Características</a> ·
  <a href="#faq">Preguntas frecuentes</a> ·
  <a href="#family">Familia HOP</a>
</p>

---

## El acortador de HOP

Acortador de enlaces rápido y respetuoso con la privacidad con slugs personalizados, códigos QR y análisis profundo. Parte de HOP.

<a id="features"></a>

## Características

- **Acortamiento ultrarrápido**: Slugs personalizados o identificadores aleatorios servidos en hel.ink/l/{slug} con baja latencia.
- **Códigos QR dinámicos**: Genera códigos QR en SVG/PNG de alta resolución para imprimir o compartir en línea.
- **Analítica profunda de clics**: Monitorea países (GeoIP), dispositivos, navegadores, sistemas operativos y referentes en tiempo real.
- **Seguridad automatizada**: Escaneo proactivo a través de hop/safety y listas negras compartidas para proteger a tu audiencia.
- **Controles detallados**: Configura fecha de caducidad, límite de clics y contraseña para enlaces sensibles.
- **Carpetas y etiquetas**: Organiza tus enlaces en carpetas y etiqueta campañas para una gestión ordenada.
- **Inicio de sesión con HOP ID**: Inicia sesión con tu cuenta central HOP ID sin credenciales redundantes.
- **API REST para desarrolladores**: API completa con tokens de autenticación por ámbitos para automatizar tus flujos.

<a id="run"></a>

## Instala tu propio LinkHop

LinkHop se ejecuta sobre una infraestructura moderna de PHP y Laravel.

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

## Preguntas frecuentes

**¿Qué pasó con las páginas link-in-bio?**
Las páginas bio se han trasladado a BioHop (bio.hel.ink) como una aplicación dedicada. Los enlaces antiguos redirigen automáticamente.

**¿Puedo usar slugs personalizados?**
Sí, los usuarios registrados pueden elegir slugs propios siempre que estén disponibles.

**¿Cómo se protege la privacidad en las estadísticas?**
Los datos se agregan sin almacenar información personal ni vender perfiles, y los registros se limpian periódicamente.

**¿Es LinkHop gratuito y de código abierto?**
Sí, LinkHop se distribuye bajo la licencia permisiva MIT.

<a id="family"></a>

## La familia HOP

LinkHop forma parte de la familia de herramientas web abiertas HOP.

| Aplicación | Dirección en vivo | Código | Qué hace |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | Portal de cuentas, acceso HOP ID |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | este repositorio | Enlaces cortos, códigos QR y analítica |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Páginas de link-in-bio y temas |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Alojamiento de imágenes y archivos |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Notas privadas con cifrado en el cliente |

<a id="security"></a>

## Ayuda y soporte

¿Encontraste un error o necesitas ayuda? Abre una incidencia en <a href="https://github.com/navi-crwn/LinkHop/issues">GitHub Issues</a>o envía un pull request.

---

<p align="center"><sub>LinkHop · parte de la familia HOP · <a href="https://link.hel.ink">link.hel.ink</a></sub></p>
