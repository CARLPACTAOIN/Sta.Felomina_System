---
paths:
  - 'app/**'
---

# App

## Typed PHP application code
Use explicit parameter and return types, braces for every control structure, constructor property promotion, and TitleCase enum cases. Prefer PHPDoc for non-obvious types and follow sibling structure before introducing a new abstraction.

## Document records own verification identity
For Capstone V1, DocumentRecord owns the permanent verification_token and public /verify/{token} identity. QR image generation/download must encode that existing URL and must not create or reassign a separate QR identity unless the PRD is explicitly changed.
