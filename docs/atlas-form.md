# Candidate application form - Atlas automation

Each live mandate on the site opens an in-page application form.
When a candidate submits it, their CV and details go straight into the matching Atlas project pipeline - no manual re-keying.

## What happens on submit

1. The browser posts the form (including the CV file) to a small server endpoint, `api/apply.php`.
2. `apply.php` uploads the CV to Atlas (`POST /api/v1/files`, type `resume`).
3. It then creates the applicant in the role's project (`POST /api/v1/projects/{id}/applicants`) with the CV, the candidate's name, email and phone, and the extra answers (current salary, expected salary, location preference, nationality) written into the applicant notes.
4. Atlas parses the CV automatically and the candidate appears in the pipeline for that role.

The role's Atlas project id is the same id the site already uses for the public job feed, so the form needs no extra lookup.

## Why a server endpoint is required

The Atlas Open API that creates applicants is authenticated with a bearer token.
That token must never be exposed in the browser, so the call is made server-side by `apply.php`, which holds the token in server configuration.
A pure static page cannot do this safely, which is why the form needs PHP (or any server) behind it.

## Requirements

- PHP 7.4 or newer with the cURL extension (standard on the current cordell-partners.com hosting).
- An Atlas Open API token with permission to upload files and create applicants.

## Install

1. Copy the `api/` folder to the PHP host so the endpoint is reachable at `.../api/apply.php`.
2. In `api/`, copy `config.sample.php` to `config.php`.
3. Put the Atlas token in `config.php` (`atlas_token`), or set the `ATLAS_API_TOKEN` environment variable on the host (the environment variable wins if both are set).
   `config.php` is git-ignored and must never be committed.
4. In `config.php`, set `allowed_origins` to every domain the site is served from.
5. Point the page at the endpoint:
   - When the site and `api/apply.php` are on the same domain (for example once the redesign is live on cordell-partners.com), the default `APPLY_ENDPOINT = 'api/apply.php'` in `index.html` already works.
   - While the site is on a different host (for example the GitHub Pages preview), set `APPLY_ENDPOINT` in `index.html` to the absolute URL, e.g. `https://cordell-partners.com/api/apply.php`, and make sure that domain is listed in `allowed_origins`.

## Verify before go-live

From the `api/` folder on the host (or any machine with PHP and the token):

```
ATLAS_API_TOKEN=xxxxx php test-apply.php <projectId> <path-to-a-test-cv.pdf>
```

Use a disposable test role, or delete the test applicant in Atlas afterwards.
The script prints each step's HTTP status and response, confirming the token, the project id, and both endpoints.

## Notes

- The form accepts PDF, DOC and DOCX up to 8 MB (configurable in `config.php`).
- A hidden honeypot field silently drops bots; optional per-IP rate limiting can be switched on in `config.php`.
- If Atlas is unreachable or misconfigured, the candidate sees a clear message asking them to email info@cordell-partners.com, and the server logs the detail - the token is never shown to the browser.
- General "send your CV in confidence" submissions (not tied to a specific role) still go to the Atlas job board, because an applicant must be attached to a project. A talent-pool version can be added later using the people endpoints if wanted.
