# Lessons Learned

## Hostname boundaries

Dedicated hostnames keep routing and access control understandable. The public DMA surface is a static showcase; its interactive backend is outside the web root.

## Resource limits

Log retention, metrics, images and backup staging need explicit limits. Capacity promises and measured filesystem usage are different signals.

## Verify the artifact that runs

A successful CI build does not prove a later host rebuild is identical. Build and scan once, deploy the immutable digest, then check the running image and embedded commit before writing the deployment receipt.

## Restore applications as well as data

An import and integrity check establish data evidence. Start the applications against the recovered data and state the timing assumptions explicitly. A fast database exercise does not measure full replacement-infrastructure recovery.

## State visibility has a detection limit

Internal state cards help an operator review services, capacity and backup freshness. Without alert delivery or an external notifying monitor, a failure may remain unnoticed and may also make the dashboard unavailable.
