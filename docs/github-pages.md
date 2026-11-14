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
