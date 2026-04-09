# Accessibility Review

Accessibility on `shellr.net`, `dma.shellr.net`, and `docs.shellr.net` is treated as an operational quality issue rather than a visual polish task.

## Current Baseline

- skip links exist on the main public surfaces
- keyboard focus states are intentionally visible
- mobile table handling on DMA avoids clipped statistics
- public status has a server-rendered snapshot instead of relying purely on a JavaScript app

## What Gets Checked

- keyboard-only navigation on the landing page and DMA
- visible focus indicators against the dark and light themes
- reduced-motion behavior for reveal effects
- responsive table behavior on DMA
- semantic navigation and breadcrumb structure on the docs site

## Known Limits

- DMA remains data-heavy, so mobile readability still depends on horizontal table scrolling in some views
- Uptime Kuma is still a JavaScript-heavy app behind the public snapshot layer

## Why This Matters

For a small platform, accessibility is part of operational discipline: if navigation breaks under keyboard-only use, zoom, or reduced motion, that is a real product and maintenance issue.
