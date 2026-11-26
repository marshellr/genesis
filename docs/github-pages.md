# GitHub Pages

## Goal

Publish technical documentation and project overviews for `docs.shellr.net` without serving those pages from the VM.

## Why GitHub Pages

- no runtime load on the production VM
- versioned documentation
- simple review and publishing flow
- enough for static, technical documentation

## Current approach

- plain static site under `/docs/site`
- `/docs/site` is the only source of truth for site content
- the Genesis docs workflow validates the sources. The publish script syncs them to the separate Pages repository, whose workflow publishes the site
- custom domain handled through `CNAME`

Both the source documentation workflow and the Pages publishing workflow check local HTML links, assets and fragment targets without external requests. The Pages job stops before publishing if a local target is missing. After publishing, the GitHub runner verifies HTTPS, status 200 and exact source bytes for the homepage and four core pages, with bounded retries for propagation. Keep the publishing repository's copy of `check-docs-links.py` under `.github/scripts` in sync with `scripts/check-docs-links.py`; that directory is preserved by the publish script.
