# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [2026-05-08]

### Fixed
- Phase 1 non-normal items (DAS labels) were missing from Phase 3 packages when the external LLM screening CSV contained all items (not just Normal ones), causing `total_non_normal = 0`
- `createPhase3` now detects Phase 1 non-normal items by querying Phase 1 annotations directly (`category_ids` non-Normal) instead of relying on items absent from `ai_screenings`
- `store` (CSV import) now computes `total_non_normal` from actual Phase 1 annotations instead of the CSV absence heuristic

### Added
- `syncNonNormalToPhase3` action (`POST phase2/{run}/sync-non-normal`) to add missing Phase 1 non-normal items to an already-created Phase 3 package
- Phase 2 run detail page now shows a live annotation-based non-normal count (not the stale stored value) and displays a **Sync Non-Normal Items** warning button when items are missing from an existing Phase 3 package

## [2026-04-28]

### Added
- Phase 2 AI screening pipeline with OpenAI (batch & sync) and Groq support
- Phase 3 reannotation flow triggered from Phase 2 screening results
- AI Settings page: provider/model selection, batch API toggle, reasoning toggle, confidence threshold, prompt template
- Retry errors functionality: deletes errored rows, dispatches re-run for missing/failed items
- `gpt-4.1` model option added to OpenAI model list

### Fixed
- Duplicate `ai_screenings` migration with conflicting timestamp caused FK constraint failure on fresh installs — removed the duplicate file
- Batch API submission now uses `dispatchSync` so it runs inline during the HTTP request, compatible with shared hosting (no persistent queue worker required)
- Missing `use App\Models\AiScreening` import in `RunPhase2Screening` job caused `Call to undefined method` error
- Stale queue workers holding old class definitions after code changes — resolved by `queue:restart` signal
- `$errorCount` undefined variable in Phase 2 show view — extracted from `$contentdata` array
- Duplicate `ai_screenings` rows caused by `updateOrCreate` without a unique constraint — reverted to `create()` with all-existing-row filtering
- Orphaned `pending` rows from old retry logic — fixed by deleting error rows instead of resetting to pending

## [2026-04-13]

### Fixed
- Package export improvements and bug fixes

## [2026-01-29]

### Added
- Package export report with expandable annotated data details table

## [2026-01-28]

### Added
- Package export report page

## [2026-01-23]

### Added
- Report page (work in progress iterations)

## [2025-12-19]

### Added
- Anti-redundant display feature to prevent showing duplicate annotation targets

## [2025-12-17]

### Fixed
- Bug in package assignment flow

## [2025-12-12]

### Added
- Upload loading indicator for data uploads

## [2025-12-11]

### Added
- Initial commit: base Laravel project setup
- README
