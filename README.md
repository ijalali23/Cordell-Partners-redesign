# Cordell Partners - Website

Marketing website for Cordell Partners, a boutique executive search firm for UAE and GCC financial services.

The site is a single self-contained `index.html` (HTML, CSS and vanilla JavaScript - no build step).
Open it in a browser, or host it on any static host (GitHub Pages, Netlify, Vercel, S3).

## Structure

```
index.html            The website (self-contained; images embedded as data URIs)
brand/                Logos and the rose emblem
  logo-*.jpg            Supplied logo lockups (blue / black / white)
  emblem-white.png      Rose emblem, white, for dark backgrounds
  emblem-blue.png       Rose emblem, royal blue, for light backgrounds
  team/                 Headshots used on the site + originals
docs/
  audit.html            Review of the previous site + Atlas integration analysis
```

## Live mandates (Atlas integration)

The "Live mandates" section reads Cordell's public Atlas job feed in the visitor's browser:

- Endpoint: `https://api.recruitwithatlas.com/public-graphql`
- Agency alias: `Cordell-Partners`

A role published in Atlas appears on the site on the next page load, with no website edit.
If the feed cannot be reached, a snapshot embedded in the page is shown instead.
Each role links to its Atlas application page.

## Brand

- Palette: ink `#0D1417`, ivory `#F6F4EF`, brass `#9A7740`, royal blue `#1E05A9`
- Display: Bodoni Moda (upright; accent words in brass, not italic)
- Body: Hanken Grotesk · Labels: IBM Plex Mono
- Fonts load from Google Fonts; everything else is inline.

## Team

Oscar Orellana-Hyder (Co-founder), Cellou Diallo (Senior Consultant), Ikhlas Jalali (Head of Research), Narissa (Senior Research Lead).

## Open items before launch

- Hero "how we search" figures are illustrative - replace with real anonymised data or the step rail.
- Track-record stats (years / mandates / success rate) need verified figures before launch.
- Add LinkedIn URLs to the team cards.
- Replace the raster rose emblem with an SVG for crispness at any size.
- Wire the contact and CV forms to a backend / Atlas (currently front-end only).

## Branches & deployment

- **`main`** - production. Every push deploys the live site via GitHub Pages
  (`.github/workflows/deploy.yml`). This is the final, live branch.
- **`develop`** - development, testing and enhancements. Day-to-day work happens
  here; pushes run sanity checks (`.github/workflows/validate.yml`).

Workflow: branch off `develop`, open a pull request into `main`, and merging to
`main` publishes automatically.

**Live URL:** https://ijalali23.github.io/cordell-partners-redesign/
(available once the first deploy on `main` finishes under the repo's Actions tab).

## History

Migrated out of the `WiredThatWay` repo (was `cordell-partners-redesign/`) into
this standalone repository.
