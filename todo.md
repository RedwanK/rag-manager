# Lot 1 Tasks

## User Management (Symfony Security)
- [x] Implement `User` entity with email as user identifier, password hash, roles array, status, timestamps; expose getters for `UserInterface`/`PasswordAuthenticatedUserInterface`. #auth #security
- [x] Configure `security.yaml` (password hashers, user provider, firewall, access control) aligned with Symfony Security defaults. #auth #config
- [ ] Create Admin-only CRUD for users (listing, create, edit roles/status, reset password). #auth #ui #admin
- [x] Seed an initial Admin user via fixture or console command for first access. #auth #ops

## GitHub Sync and Storage
- [ ] Add `RepositoryConfig` entity (repo name, owner, url, default branch, encrypted token, last sync status/time/message). #github-sync #db
- [ ] Service to validate repo connectivity/token scopes against GitHub API with clear error messages. #github-sync #backend
- [ ] Manual sync job/command to fetch repository tree and persist `DocumentNode` cache. #github-sync #sync
- [ ] Persist `SyncLog` entries tying sync runs to triggering user and status. #github-sync #observability
- [ ] Redact tokens after save in UI and encrypt at rest in storage. #security #github-sync

## UI
- [ ] Tree view UI backed by cached `DocumentNode` data, showing path, size, last modified, last sync status/time. #ui #github-sync
- [ ] Authorized action to trigger sync from the UI (roles: Admin/Reviewer). #ui #auth #github-sync
- [ ] Admin UI to configure repository settings and token updates. #ui #admin #github-sync
- [ ] Enforce role-based visibility for Admin/Reviewer/Viewer across pages and actions. #ui #auth
