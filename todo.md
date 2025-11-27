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
- [ ] Create `ingestion_queue_item` entity (path, branch, sha, size_bytes, storage_path, status queued|processing|indexed|failed|download_failed, source, timestamps, message, FK repo/doc/user) persisted in MySQL. #db #ingestion #rag
- [ ] Create `ingestion_log` entity linked to queue item (level info|warning|error, message, context json, created_at). #db #logs #ingestion
- [ ] Extend `document_node` with ingestion_status + last_ingested_at to surface last known ingestion state. #db #ingestion #ui
- [ ] Add `rag_shared_dir` app config and secure path building for downloaded artifacts. #config #ops #ingestion
- [ ] On enqueue, download file from GitHub default branch and write to `rag_shared_dir`; mark status queued or download_failed with message. #ingestion #github-sync #fs
- [ ] Enforce duplicate protection (no re-enqueue when queued/processing) and size/extension validation with user-friendly errors. #ingestion #validation #ui

## UI (tree/table)
- [ ] Add “Ajouter à la file” action on files (not folders) with toast/flash feedback and role gating. #ui #ingestion #auth
- [ ] Render badges/colors for directory, unindexed, queued, processing, indexed, failed/download_failed states in tree and list views. #ui #ingestion
- [ ] Detail panel shows ingestion status, last attempt, size/branch and links to item logs. #ui #logs #ingestion
- [ ] Provide retry/re-index action for failed/download_failed items (creates/reuses queue item with audit of user). #ui #ingestion
