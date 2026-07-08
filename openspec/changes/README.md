# Changes Directory

This directory holds SDD change proposals, specs, designs, and task plans.

Each change lives in its own subdirectory:

```
openspec/changes/
  <change-slug>/
    proposal.md    — what and why
    spec.md        — requirements and scenarios
    design.md      — technical approach and architecture
    tasks.md       — implementation breakdown
    verify.md      — verification results
```

## Naming Convention

Use kebab-case slugs derived from the change title:
- `add-stripe-webhooks`
- `tenant-service-catalog`
- `guest-booking-flow`

## Status Lifecycle

```
proposed → spec'd → designed → tasks → implementing → done
```

Archive completed changes to `openspec/changes/<slug>/archive.md` after merge.
