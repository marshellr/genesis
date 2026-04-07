# Deployment Flow

## Goal

Keep deployment reviewable and recoverable while avoiding unnecessary CI infrastructure on the VM itself.

## Flow

```mermaid
sequenceDiagram
    participant Dev as Developer
    participant GH as GitHub
    participant GA as GitHub Actions
    participant VM as shellr VM

    Dev->>GH: Push
    GH->>GA: Trigger workflow
    GA->>GA: Validate config and build artifact
    GA->>VM: Upload staged release over SSH
    VM->>VM: Validate compose and Nginx config
    VM->>VM: Update containers
    VM->>VM: Run healthchecks
    alt deployment healthy
        VM->>GA: Success
    else unhealthy
        VM->>VM: Restore previous state
        VM->>GA: Failure
    end
```

## Characteristics

- staged deployment before activation
- config validation before switch
- health-gated success path
- rollback as an explicit operational step

## Documentation Publishing

Documentation is deployed separately through GitHub Pages and does not run on the VM.
