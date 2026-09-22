# The Events Calendar x Zaplane — Local Verification
Date: 2026-09-21. Test site: kodezen (LocalWP). TEC version: 6.17.5.

## Scope
- Exactly 13 requested triggers and 19 actions, registered in PHP and `assets/json/integrations.json`.
- Optional trigger filters for IDs, status, and related-post type/ID; omitted filters match all.
- Event Created defers a TEC-created event until `tribe_events_update_meta`, so date meta is populated before the workflow run begins. One hook event may be consumed by multiple workflows.
- A venue replacement fires both the old venue's unlink and new venue's link. Relation triggers are TEC venue/organizer metadata only; not arbitrary WordPress post relationships.
- The former four Event Tickets triggers are removed from this integration's registry. A read-only scan of saved workflow versions found NO legacy TEC trigger references on this test site. No existing workflows were deleted or migrated.
- Delete actions and delete triggers concern permanent WordPress deletion, not Trash.

## Executed tests and results
- PHP lint for TEC integration, PHPUnit tests and frontend asset metadata: pass.
- Frontend `npm run build`: exit 0; regenerated `assets/build/app.js`, `app.asset.php` and CSS. The merge-conflict markers previously present in compiled JS and PHP have been eliminated. JS syntax check passes.
- Build emitted 40 warnings, mainly imports/bundle size; these are not claimed to be resolved by TEC changes.
- Live WordPress+TEC direct integration/hook fixture: 37 PASS, 0 FAIL (earlier session). Its temporary data were cleaned before the instruction to preserve subsequent test data.
- PHPunit 9.6.36, isolated `EventscalendarTest.php`: **21 tests, 338 assertions, PASS**. Includes deferred creation, multi-consumer hook semantics, venue replacement, optional filters, manifest hooks/schema parity.
- Saved Zaplane workflow E2E: workflow #107, event #221, run #107: completed but initial Event Created date was empty (diagnostic test).
- Date fix E2E: workflow #108, event #222, run #108: 5/5 checks passed; both trigger and action have the date.
- Final E2E after all current PHP edits: workflow #109, event #226, run #109: 5/5 checks passed; trigger -> saved Run -> queued NodeRuns -> downstream Get Event Details, correct IDs and dates.
- Real relation test: event #223, old venue #224, new venue #225: 6/6 checks passed; old unlink and new link were observed.
- Test workflows #107, #108, #109 remain paused, with their runs and test events intact. Relation fixtures remain in the site.

## Remaining limits / release notes
- The isolated integration suite passed; the full repository PHPUnit suite cannot be loaded in the current vendor tree because the Mockery development dependency is absent. Do not call the entire project test suite green.
- Existing test data are preserved. Temporary web test harness PHP files are retained but disabled against direct HTTP re-execution after completion.
- Changes are local working-tree edits: not committed, pushed, or deployed to a production server.

