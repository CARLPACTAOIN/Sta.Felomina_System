---
paths:
  - 'resources/views/**'
---

# Views

## Keep unfinished modules honest
Use shared foundation notices and empty states when a PRD module lacks persisted domain behavior. Do not place prototype sample records or fake operational actions in production views; enable controls only with tested backend behavior.

## Livewire and Flux UI boundaries
Use existing Flux and Blade components for Livewire interfaces. Keep authoritative state, validation, and authorization server-side; use Alpine only for client-only interaction and never simulate unimplemented writes.
