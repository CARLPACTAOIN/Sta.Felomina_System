---
paths:
  - 'routes/**'
---

# Routes

## Use UserRole for staff route boundaries
Internal workspace routes use the UserRole enum and role middleware. Captain-only staff/audit routes stay separate; Purok Chairpersons must never receive document administration routes.
