# LinkHop brand files

Every logo, mark and favicon the site uses lives here, and only here.

| File | What it is | Used for |
| --- | --- | --- |
| `linkhop-wordmark.svg` | "LinkHop" with the bunny-head **o** and the arrow, gradient | Site and app headers, auth pages, footer (`<x-wordmark>`) |
| `linkhop-wordmark-white.svg` | Same, white | Dark/colour backgrounds |
| `linkhop-mark.svg` | Bunny head in the gradient tile | Source of the favicon and app icon |
| `linkhop-mark-gradient.svg` | Bunny head, gradient, no tile | Home page fallback picture |
| `linkhop-mark-white.svg` | Bunny head, white, no tile | Source of `logo.png` |
| `favicon.png` | Tile, 256 px | Favicon, apple-touch-icon, `<x-application-logo>` |
| `logo-dark.png` | Tile, 512 px | Logo on light backgrounds, OpenGraph image |
| `logo.png` | White bunny, 512 px | Logo on dark backgrounds, emails, floating auth tiles |

`public/favicon.ico` is rendered from `linkhop-mark.svg` too (browsers fetch `/favicon.ico` directly).

## Using them

```blade
<img src="{{ route('brand.asset', 'linkhop-wordmark.svg') }}">
<link rel="icon" href="{{ route('brand.favicon') }}">   {{-- shortcuts: brand.logo, brand.logo.dark, brand.favicon --}}
```

The URLs carry a version (`/brand/3f2a9c1e/favicon.png`) that changes whenever a file here changes, so
browsers and Cloudflare pick up a new logo immediately. Old plain paths (`/brand/logo.png`) still work.

## Changing the logo

Edit the design in `scripts/linkhop-logo.cjs`, then regenerate everything at once:

```bash
npm i --no-save opentype.js@1.3.4 @fontsource/poppins@5.1.0
node scripts/linkhop-logo.cjs
```

Or replace the files here by hand with the same names. Either way, nothing else needs to change.
