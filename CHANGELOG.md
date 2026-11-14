# Changelog

All notable changes to LinkHop (hel.ink) are written here in plain language, newest first: what changed, where you
see it, what it means for you, and why. The page https://link.hel.ink/changelog is built from this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.4.0] - 2026-11-14 - QR codes page and calmer analytics

A new QR codes page inside the dashboard, and a tidier create dialog, analytics page and sidebar.

### Added

- **QR codes page.** The "QR codes" entry in the dashboard menu now opens a page inside the dashboard instead of the public tool. It lists the QR codes of your short links and the codes you keep yourself (Wi-Fi, contact card, link or text). Create QR makes a new one, Save keeps it, and each saved code can be viewed, downloaded as PNG or SVG, renamed or deleted. What a saved code carries, such as a Wi-Fi password, is stored encrypted and shown only to you, up to 50 codes per account. Asked for because the menu used to leave the dashboard and a made code could not be kept.

### Changed

- **Create dialog.** The Folder picker is now on the left, next to Tags, and the right side shows only the link preview, also for the QR and Deep link entries of the Create menu. Reported by a user: the folder belongs with the other fields you fill in.
- **Analytics.** The list of links is as wide as its button instead of stretching across the page. On wide screens (1280 px and up) the chart sits beside the four key numbers and is taller. The smaller tabs under a tab (Countries, Domains, Devices, Links) now look smaller and sit right under their tab, and the main tabs wrap onto a second line on a phone instead of scrolling sideways. Reported by a user: the page looked stretched and the two rows of tabs looked alike.
- **Sidebar.** "Part of Hop Easy Link" is now at the bottom of the sidebar on every dashboard page, no longer under the page content, where its place changed with the length of the page.
- **Public pages when signed in.** On information pages, the changelog, the public QR tool and the link checker, the logo and the back link now take a signed-in person to the dashboard and say so; visitors still go to the front page.
- **Public top bar on a phone.** The Sign up (or Dashboard) button now lives in the menu on small screens. In German and Russian the bar was wider than the screen and pushed the menu button out of sight.

### Fixed

- **QR sheet in the create dialog.** Reset now only restores the colours and logo; it used to switch the QR code off as well. After the sheet closes, the keyboard focus returns to a control you can see.
- **Confirm questions.** Questions such as "Delete this QR code?" did nothing when the family's own dialogs could not load and the browser's plain question was used instead; the action now goes ahead after you confirm.

### Security

- **Framework update.** The web framework LinkHop runs on is updated to a version that closes a security advisory published on 2026-11-13, together with a few small packages it relies on. Nothing changes in how the site looks or works.

### Removed

- **Control center settings.** Eight site and search settings that never changed any page are no longer offered to the control center; an older control center that still sends them gets no error and nothing is stored.

## [2.3.3] - 2026-11-14 - API rules and cookies

### Fixed

- **API alias rule.** A new link made through the token API takes an alias of 3 to 48 letters, numbers and hyphens, the same as the link form. Older links with an underscore stay readable and can still be deleted through the API.
- **API link password.** A link password set through the token API must be at least six characters, the same as the link form (it used to accept four). The API page now states both rules.
- **Language cookie and caches.** Pages now tell shared caches that they depend on the visitor's cookies, so a cache never keeps one visitor's language for another. Public images and files keep their own caching.
- **First visit.** A visit without a language cookie no longer receives the one-time clean-up cookies; the clean-up runs on the first visit that carries a language cookie. A plain page is now free of preference cookies, like the bio pages.

## [2.3.2] - 2026-11-13 - A calmer create dialog and analytics

### Changed

- **Create dialog.** A short core (destination, short link, tags) with the preview beside it, and every other option group in its own small sheet that opens from a button: campaign tags, QR code, social preview, schedule and limits, protection, open in the app, routing and split, WhatsApp rotator. A button that was filled in shows a short summary.
- **Create button.** The top bar action is now "Create". The main button opens the dialog as before, and the arrow beside it offers Link, QR code and Deep link. On pages without the dialog these entries open the links page with the matching sheet.
- **Analytics.** More compact, especially on a phone: one toolbar row (link picker, time range, and Location and Device together in one Filter menu), one card with the four key numbers, the chart, and one card with tabs for every breakdown.
- **Languages.** About 150 texts of the create dialog and analytics that were English in every language are now translated in all nine languages.

### Fixed

- **Analytics chart and map.** The chart, the map and the country focus work again on the reworked page; a script error stopped the whole block from loading. Pages are now checked for this automatically.
- **QR chip.** Opening the QR sheet no longer switches the QR on; a switch inside the sheet (or the Create menu entry) does, and it switches off again for the next link. The dialog and its sheets are solid surfaces instead of half see-through.
- **Create dialog address.** The link that opens the dialog is read once, so the dialog no longer reopens after the page reloads for a new link. The campaign tag sheet no longer carries over the previous link's fields, the QR sheet keeps its buttons in view on a short window, and the WhatsApp rotator and app store sheets say why they are paused.
- **Analytics texts.** The page has a hidden main heading again for screen readers, and "24h", "via proxy/VPN" and the empty list texts follow the reader's language.

## [2.3.1] - 2026-11-12 - Analytics list, slugs and search engines

### Fixed

- **Analytics link list.** The open list of links was painted under the number cards; it now sits above them with a solid background, and Escape closes it and returns to its button.
- **Create dialog.** On wide screens the preview column stays in view while the options scroll.
- **Slug fields.** All forms now accept exactly what the server accepts: letters, digits and hyphens, 3 to 48. The dialog used to allow underscores and any length, so such links came back with an error.
- **API deleting links.** A hub token with the "edit and delete short links" permission can delete a link through the API, and the docs page says so in nine languages.

### Changed

- **Search engines.** Every public page names its canonical address, its language versions and a picture for sharing, the same way the hub does, and the home page and QR page describe what they do in their titles.
- **Theme and language.** The chosen theme and language are shared with the other HOP apps through cookies, so a choice made in one app holds in the others.

## [2.3.0] - 2026-11-12 - WhatsApp rotator, claiming guest links and app store links

### Added

- **WhatsApp rotator.** A link can hold up to 10 WhatsApp numbers, each with a weight and an optional prefilled message; every visit goes to one of them, in turn or by weight, so a team can share incoming chats. Accounts only.
- **Claiming guest links.** After signing in, the browser that made guest links sees "You made N links before signing in" with a Claim button on the dashboard and My links. Claiming respects your active-link limit and says how many are left.
- **App store link.** One short link that sends each system to its own app store: Google Play, the App Store, the Mac App Store or the Microsoft Store; everyone else goes to the destination.

### Changed

- **Open in the app.** The list of known apps now comes from the shared safety package and is stricter: only secure (https) destinations are opened in an app, and the address must match the app's own name exactly. Everything else is a normal redirect, never an error.
- **WhatsApp rotator counts.** Each number counts only the clicks from the time it was added.

## [2.2.0] - 2026-11-11 - QR page, PayHop and a family look

### Added

- **QR page.** A public page that makes QR codes in your browser: Wi-Fi, contact card, and link or text, with colours, shapes, a centre logo, scan advice, PNG and SVG download and a printable table card in nine languages. Nothing you type is sent to a server.
- **QR codes link.** A "QR codes" link in the top bar and account menu, and a note on the link page: the code holds the short address, so a new destination keeps the printed code working.
- **PayHop.** PayHop joins the family and is named on the FAQ, features and home pages in nine languages.
- **Account check.** Switching accounts at the hub no longer leaves the old account here: pages that show the account confirm it with the hub every few minutes.
- **Changelog page.** The changelog page can be rebuilt on each update.

### Changed

- **Family assets.** The family's shared pictures and 3D marks are updated, so the home page shows the new marks.
- **Sitemap.** The sitemap lists only pages of the address that serves it.
- **Error pages.** The public error pages use the family look in light and dark, and their texts were already in nine languages.
- **Short link notice.** The page about the move to /l/ is now translated in nine languages.
- **Reserved words.** Words used by other HOP apps, such as qr, wifi, pay and send, can no longer become a new short link; existing links keep working.
- **Family row.** Every app shows "Part of Hop Easy Link" with the HOP ID mark at the start of its footer, the footer no longer has a language row, and signing out brings you back to this app.

### Removed

- **Unused pictures.** Screenshots and image files that nothing used.

### Fixed

- **Guest form.** The form on the home page has a tidier layout; the human check opens only when asked and no longer overlaps the rows below.
- **Changelog page.** The page lists every release again.

## [2.1.0] - 2026-11-10 - FileHop and guest link limits

### Added

- **FileHop.** FileHop joins the family and is named wherever LinkHop lists its sibling apps, in nine languages.
- **Link picker for other apps.** Other HOP apps can list your links for you to pick from, with a token that allows reading links.
- **Guest link limits.** Guest links are limited per hour and per rolling 24 hours, and many different visitors sending the same destination in a short time are held behind the warning page. Why: to stop abuse without bothering normal visitors.

### Changed

- **Controls.** Inputs, selects, buttons and search follow the family's control standard: one shape and height, comfortable touch targets on phones, and no motion for people who ask for less.

### Fixed

- **Phishing kit.** Links of a known account-takeover kit are now recognised and held for review, also on ordinary domains.

## [2.0.0] - 2025-12-12 - Link in Bio

### Added

- **Link in Bio.** Bio pages at hel.ink/b/username with themes, animations, fonts, social icons, many block types, password protection, QR codes and analytics.
- **Brand icons.** More than 150 brand icons for bio pages.
- **Admin bio view.** Admins can monitor and manage all bio pages.

### Changed

- **Name.** The service is now called HEL.ink, and marketing pages and the footer were updated.

### Fixed

- **Pages.** Broken brand icons, a link to a missing page and inconsistent branding on error pages.

### Security

- **Protection.** Content security headers, stricter rate limits on the API and a better abuse reporting system.

## [1.0.0] - 2025-11-18 - First release

### Added

- **Short links.** Custom slugs, password protection, expiry dates, folders and tags.
- **QR codes and analytics.** QR codes as PNG or SVG, click analytics with country, device, browser and referrer.
- **Accounts.** Sign-in with Google, two-factor authentication, email verification and an API with tokens.
- **Admin and safety.** Admin dashboard, user management, IP bans, a domain blacklist, abuse reports and a human check on forms.
