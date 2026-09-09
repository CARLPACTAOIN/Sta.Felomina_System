# Project AI Operating Contract

Keep this always-loaded file limited to cross-cutting rules. Detailed framework
guidance belongs in on-demand skills and path-scoped `.ai/rules`.

## Sources and Context Routing

| Source | Authority | Load when |
|---|---|---|
| `PRD_V1.md` | Approved product scope, rules, workflows, and acceptance criteria | Product behavior changes; use its Agent Context Map |
| `PRD_PROGRESS_AUDIT.md` | Evidence-backed current implementation status | Planning a PRD slice or reporting progress |
| `MEMORY.md` | Durable verified facts and approved decisions | Prior context or architectural history matters |
| `HANDOFF.md` | Current short-term baton | Resuming work or selecting the next task |
| `.ai/rules/index.md` | Path-scoped engineering rules | Before planning or editing any matched path |

Read the minimum relevant context. Do not load the full PRD for an ordinary
single-domain task. Never duplicate the same detail across coordination files or
store secrets, personal data, guesses, transcripts, or long command output in them.

Before planning or editing files:

1. Open `.ai/rules/index.md` when it exists.
2. Read every rule file whose globs match the paths in scope.
3. Search `.ai/rules` for task keywords to catch related constraints.
4. Activate every installed skill relevant to the task.

Use Laravel Boost's `record-rule` tool for durable path rules; do not edit rule
files manually. Keep `boost.json` guidelines disabled: Boost MCP and on-demand
skills provide targeted help without restoring a large always-loaded bundle.

## Authority and Conflicts

- The user's current approved instruction controls the current task.
- The latest approved PRD or addendum controls product intent.
- This file controls cross-cutting procedure; matching `.ai/rules` control paths.
- Code, migrations, routes, and passing tests prove implementation but do not
  silently redefine product requirements.
- `docs/barangay_document_system_ui_prototype.html` is a visual reference only,
  never implementation evidence or product truth.
- Surface conflicts that would alter scope or mandatory business rules; do not guess.

## Delivery Loop

1. Select the smallest end-to-end PRD slice and its acceptance criteria.
2. Inspect existing code, sibling patterns, and tests before designing changes.
3. Confirm installed package versions before using version-sensitive APIs.
4. Search version-matched Laravel documentation before framework-dependent changes.
5. Implement success, authorization, validation, and important failure paths.
6. Run the narrowest relevant checks, then broader checks justified by risk.
7. Update only coordination files whose meaning materially changed.

For progress evidence, never award credit for mockups, placeholders, generated
scaffolding, file presence alone, or untested claims. Do not use invented
completion percentages.

## Laravel Engineering Guardrails

- Runtime is PHP 8.4; use the installed Laravel/package major versions.
- Prefer Laravel Boost tools for application information, documentation, schema,
  read-only database inspection, URLs, and recent browser logs when available.
- Use `php artisan make:* --no-interaction` for framework files and inspect command
  help when options are uncertain.
- Follow existing structure and reuse existing components before introducing new ones.
- Do not add dependencies or new top-level directories without approval.
- Keep business state, validation, and authorization server-side.
- Prefer named routes and `route()` for application links.
- Do not create ad-hoc verification scripts when focused tests prove the behavior.
- Create documentation only when explicitly requested.

## Verification

- Add or update tests for every code behavior change and its important failure modes.
- Prefer focused Pest feature tests and model factories.
- Run `php artisan test --compact <path-or-filter>` while iterating.
- After modifying PHP, run `vendor/bin/pint --dirty --format agent`.
- Run static analysis, asset builds, or the full suite when the change warrants them.
- Do not delete tests without approval.

## Coordination Maintenance

- `PRD_PROGRESS_AUDIT.md`: current state only; update evidence when a capability
  is verified, regresses, or becomes materially blocked.
- `MEMORY.md`: durable facts/decisions only; replace stale facts and mark
  superseded decisions.
- `HANDOFF.md`: replace stale task state; leave the exact next action and checks run.
- At project completion, every required PRD criterion must be verified or
  explicitly approved out, and the handoff must contain no unfinished baton.
