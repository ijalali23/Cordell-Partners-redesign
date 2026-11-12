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
api/                  Candidate application endpoint (PHP)
  apply.php             Receives the application + CV, pushes it into Atlas
  config.sample.php     Copy to config.php and add the Atlas token (git-ignored)
  test-apply.php        CLI check for the Atlas flow before go-live
docs/
  audit.html            Review of the previous site + Atlas integration analysis
  atlas-form.md         How the application form automates into Atlas (deploy guide)
```

## Live mandates (Atlas integration)

The "Live mandates" section reads Cordell's public Atlas job feed in the visitor's browser:

- Endpoint: `https://api.recruitwithatlas.com/public-graphql`
- Agency alias: `Cordell-Partners`

A role published in Atlas appears on the site on the next page load, with no website edit.
If the feed cannot be reached, a snapshot embedded in the page is shown instead.
Each role opens an in-page application form that pushes the candidate and CV straight
into that role's Atlas pipeline (see `docs/atlas-form.md`); the form needs the PHP
endpoint in `api/` and an Atlas Open API token configured server-side.

The embedded fallback snapshot is refreshed daily from the live feed by
`.github/workflows/refresh-mandates.yml` (via `scripts/refresh-mandates.py`), so
even the no-JS fallback stays current. Run it on demand from the Actions tab.

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
- Deploy the `api/` endpoint on PHP hosting and add the Atlas token to make the
  candidate application form live (see `docs/atlas-form.md`). The contact form is
  still front-end only.

## Branches & deployment

- **`main`** - production. The release branch deployed to the live site.
- **`develop`** - development, testing and enhancements. Day-to-day work happens
  here; pushes run sanity checks (`.github/workflows/validate.yml`).

Workflow: branch off `develop`, open a pull request into `main`, and merge to
`main` to release.

**Production:** the site is hosted on the cordell-partners.com PHP hosting
(the static pages plus the `api/` application endpoint), served at
https://cordell-partners.com. See `docs/atlas-form.md` for the deploy steps.

**Optional preview:** a static copy can be published to GitHub Pages
(`.github/workflows/deploy.yml`, enable under Settings - Pages - Source: GitHub
Actions). The application form does not run on Pages because it needs PHP.

## History

Migrated out of the `WiredThatWay` repo (was `cordell-partners-redesign/`) into
this standalone repository.
