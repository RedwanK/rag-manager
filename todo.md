# Lot 1 Tasks

## User Management (Symfony Security)
- [x] Implement `User` entity with email as user identifier, password hash, roles array, status, timestamps; expose getters for `UserInterface`/`PasswordAuthenticatedUserInterface`. #auth #security
- [x] Configure `security.yaml` (password hashers, user provider, firewall, access control) aligned with Symfony Security defaults. #auth #config
- [x] Create Admin-only CRUD for users (listing, create, edit roles/status, reset password). #auth #ui #admin
- [x] Seed an initial Admin user via fixture or console command for first access. #auth #ops

## GitHub Sync and Storage
- [x] Add `RepositoryConfig` entity (repo name, owner, url, default branch, encrypted token, last sync status/time/message). #github-sync #db
- [x] Service to validate repo connectivity/token scopes against GitHub API with clear error messages. #github-sync #backend
- [x] Manual sync job/command to fetch repository tree and persist `DocumentNode` cache. #github-sync #sync
- [x] Persist `SyncLog` entries tying sync runs to triggering user and status. #github-sync #observability
- [x] Redact tokens after save in UI and encrypt at rest in storage. #security #github-sync

## UI
- [x] Tree view UI backed by cached `DocumentNode` data, showing path, size, last modified, last sync status/time. #ui #github-sync
- [x] Authorized action to trigger sync from the UI (roles: Admin/Reviewer). #ui #auth #github-sync
- [x] Admin UI to configure repository settings and token updates. #ui #admin #github-sync
- [x] Enforce role-based visibility for Admin/Reviewer/Viewer across pages and actions. #ui #auth

## Utils
- [x] Use Symfony translations instead of raw string in the application (php and twig files) #utils
- [x] Complete PHPDoc for each functions, class and related items # utils

# Lot 2 Tasks

## Ingestion queue & storage
- [x] Create `ingestion_queue_item` entity (path, branch, sha, size_bytes, storage_path, status queued|processing|indexed|failed|download_failed, source, timestamps, message, FK repo/doc/user) persisted in MySQL. #db #ingestion #rag
- [x] Create `ingestion_log` entity linked to queue item (level info|warning|error, message, context json, created_at). #db #logs #ingestion
- [x] Extend `document_node` with ingestion_status + last_ingested_at to surface last known ingestion state. #db #ingestion #ui
- [x] Add `rag_shared_dir` app config and secure path building for downloaded artifacts. #config #ops #ingestion
- [x] On enqueue, download file from GitHub default branch and write to `rag_shared_dir`; mark status queued or download_failed with message. #ingestion #github-sync #fs
- [x] Enforce duplicate protection (no re-enqueue when queued/processing) and size/extension validation with user-friendly errors. #ingestion #validation #ui

## UI (tree/table)
- [x] Add “Ajouter à la file” action on files (not folders) with toast/flash feedback and role gating. #ui #ingestion #auth
- [x] Render badges/colors for directory, unindexed, queued, processing, indexed, failed/download_failed states in tree and list views. #ui #ingestion
- [x] Detail panel shows ingestion status, last attempt, size/branch and links to item logs. #ui #logs #ingestion
- [ ] Provide retry/re-index action for failed/download_failed items (creates/reuses queue item with audit of user). #ui #ingestion

## Improvements
- [x] Create an extension guesser based on the name and / or path of a document to update the document type with accurate value #improvements 
- [ ] Fix form errors when creating / editing a user. #improvements

# Lot 3 Tasks

## Incremental sync and data integrity
- [x] Implement path+repository upsert for `DocumentNode` (update metadata if present, create if missing) without purge cycles. #lot3 #github-sync #db
- [x] Handle GitHub deletions via Doctrine SoftDeletable (no physical DELETE) to keep `ingestion_queue_item` / `ingestion_log` references intact. #lot3 #github-sync #db
- [x] Preserve ingestion fields during resync (no status/timestamp resets); log per-document errors, skip to next item, and avoid dirty partial writes. #lot3 #github-sync #observability
- [x] Optimize sync diffing with a path hash map to detect create/update/delete and avoid bulk delete/insert loops. #lot3 #github-sync #performance

## Tree UI
- [x] Fix tree layout to avoid horizontal clipping; use ellipsis+tooltip for long paths and keep alignment/scroll within the canvas. #lot3 #ui #ux
- [x] Make “Add to queue” conditional: show on indexable files in `unindexed`/`failed`/`download_failed`, provide “Retry” for failed states (re-enqueue to `queued`), hide/disable with tooltip for `queued`/`processing`/`indexed`. #lot3 #ui #ingestion
- [x] Add tree filters (Indexed, Indexable, Failed, All) with the selection persisted during navigation. #lot3 #ui #ingestion

## Cached documents list
- [x] Add columns: name+extension, path, ingestion status, ingestion date (last attempt/success), size in MB (2 decimals), branch, plus enqueue action with the same state rules as the tree. #lot3 #ui #ingestion
- [x] Default sort by most recent ingestion; allow sort by size/name; ensure displayed status comes from the latest queue/ingestion state (not overwritten by sync). #lot3 #ui #ingestion #github-sync

## Ingestion logs view
- [x] Create a paginated ingestion logs screen with filters: level (info|warning|error), queue status, date range, document path search, enqueuing user. #lot3 #ui #logs #ingestion
- [x] Display each log row with timestamp, level, document, queue item status, message, source (user/system), and links to the related queue item/document. #lot3 #ui #logs #ingestion
