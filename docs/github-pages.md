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
- deployment is done from the VM through a dedicated publish script into the Pages repo checkout under `/projects/genesis/docs/publish/marshellr.github.io`
- custom domain handled through `CNAME`
