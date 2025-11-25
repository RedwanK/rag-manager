import datetime
import os, re, json, hashlib, urllib.request, urllib.parse, urllib.error
from pathlib import Path

REPO = os.environ["GITHUB_REPOSITORY"]
SHA = os.environ.get("GITHUB_SHA", "main")
TOKEN = os.environ["GITHUB_TOKEN"]

# Fichiers à ignorer (archive, templates, etc.)
IGNORE_DIRS = {".git", ".github", "node_modules", "vendor", ".venv", "venv"}
CACHE_PATH = Path(".github/todos-cache.json")

checkbox_re = re.compile(r"^\s*[-*]\s+\[(?P<checked>[ xX])\]\s+(?P<text>.+)$")
due_re = re.compile(r"\bdue:\s*(\d{4}-\d{2}-\d{2})\b", re.IGNORECASE)
label_re = re.compile(r"(?:^|\s)#([A-Za-z0-9._/-]+)")
prio_re = re.compile(r"(?:^|\s)!(p[123])\b", re.IGNORECASE)
task_id_body_re = re.compile(r"_Task ID:\s*`(?P<tid>[0-9a-f]{12})`_", re.IGNORECASE)

_project_flag = os.environ.get("GITHUB_PROJECT_SYNC")
PROJECT_SYNC = False
if _project_flag is not None:
    PROJECT_SYNC = _project_flag.strip().lower() not in {"", "0", "false", "no"}
PROJECT_TITLE = os.environ.get("GITHUB_PROJECT_TITLE", "").strip()
_start_field = os.environ.get("GITHUB_PROJECT_START_FIELD", "Start date").strip()
PROJECT_START_FIELD_NAME = _start_field or None
_end_field = os.environ.get("GITHUB_PROJECT_END_FIELD", "Target date").strip()
PROJECT_END_FIELD_NAME = _end_field or None

OWNER, REPO_NAME = REPO.split("/", 1)

if not PROJECT_TITLE:
    PROJECT_TITLE = REPO_NAME

_PROJECT_CACHE = None

def gh_api(method, path, data=None):
    url = f"https://api.github.com{path}"
    req = urllib.request.Request(url, method=method)
    req.add_header("Authorization", f"Bearer {TOKEN}")
    req.add_header("Accept", "application/vnd.github+json")
    if data is not None:
        body = json.dumps(data).encode("utf-8")
        req.add_header("Content-Type", "application/json")
    else:
        body = None
    with urllib.request.urlopen(req, body) as resp:
        return json.loads(resp.read().decode())

def gh_graphql(query, variables=None):
    body = json.dumps({
        "query": query,
        "variables": variables or {},
    }).encode("utf-8")
    req = urllib.request.Request(
        "https://api.github.com/graphql",
        data=body,
        method="POST",
    )
    req.add_header("Authorization", f"Bearer {TOKEN}")
    req.add_header("Accept", "application/vnd.github+json")
    req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req) as resp:
            payload = json.loads(resp.read().decode())
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode()
        raise RuntimeError(f"GraphQL HTTP error: {detail}") from exc
    errors = payload.get("errors")
    if errors:
        raise RuntimeError(f"GraphQL errors: {errors}")
    return payload["data"]

def _create_date_field(project_id, name):
    data = gh_graphql("""
        mutation($projectId: ID!, $name: String!) {
          createProjectV2Field(input: {
            projectId: $projectId,
            name: $name,
            dataType: DATE
          }) {
            projectV2Field {
              ... on ProjectV2Field {
                id
                name
                dataType
              }
            }
          }
        }
    """, {"projectId": project_id, "name": name})
    field = data["createProjectV2Field"]["projectV2Field"]
    if not field or field.get("dataType") != "DATE":
        raise RuntimeError("Impossible de créer un champ date ProjectV2.")
    return field["id"]

def refresh_cache_from_github(cache):
    """
    Ensure local cache is in sync with existing GitHub issues to prevent duplicates.
    """
    remote = {}
    duplicates = []
    page = 1
    while True:
        res = gh_api("GET", f"/repos/{REPO}/issues?state=all&labels=from-markdown&per_page=100&page={page}")
        if not res:
            break
        for issue in res:
            if issue.get("pull_request"):
                continue
            body = issue.get("body") or ""
            match = task_id_body_re.search(body)
            if not match:
                continue
            tid = match.group("tid")
            number = issue["number"]
            state = issue["state"]
            existing = remote.get(tid)
            if existing:
                # Keep the oldest issue as canonical, close the others if needed.
                if number < existing["number"]:
                    duplicates.append(existing)
                    remote[tid] = {"number": number, "state": state}
                else:
                    duplicates.append({"number": number, "state": state})
            else:
                remote[tid] = {"number": number, "state": state}
        page += 1

    for duplicate in duplicates:
        if duplicate["state"] != "closed":
            try:
                gh_api("PATCH", f"/repos/{REPO}/issues/{duplicate['number']}", {"state": "closed"})
            except Exception:
                continue

    for tid, data in remote.items():
        cache["open"][tid] = data["number"]

def ensure_project_context():
    global _PROJECT_CACHE
    if not PROJECT_SYNC:
        return None
    if _PROJECT_CACHE is not None:
        return _PROJECT_CACHE

    owner_data = gh_graphql("""
        query($login: String!) {
          repositoryOwner(login: $login) {
            __typename
            id
            ... on Organization {
              projectsV2(first: 100) { nodes { id title } }
            }
            ... on User {
              projectsV2(first: 100) { nodes { id title } }
            }
          }
        }
    """, {"login": OWNER})

    owner_node = owner_data.get("repositoryOwner")
    if not owner_node:
        raise RuntimeError(f"Impossible de récupérer le propriétaire GitHub '{OWNER}'.")
    owner_id = owner_node["id"]
    projects = owner_node.get("projectsV2", {}).get("nodes", [])
    project = next((p for p in projects if p["title"].strip().lower() == PROJECT_TITLE.lower()), None)

    if project:
        project_id = project["id"]
    else:
        created = gh_graphql("""
            mutation($ownerId: ID!, $title: String!) {
              createProjectV2(input: {
                ownerId: $ownerId,
                title: $title
              }) {
                projectV2 { id }
              }
            }
        """, {"ownerId": owner_id, "title": PROJECT_TITLE})
        project_id = created["createProjectV2"]["projectV2"]["id"]

    fields_data = gh_graphql("""
        query($projectId: ID!) {
          node(id: $projectId) {
            ... on ProjectV2 {
              id
              fields(first: 50) {
                nodes {
                  ... on ProjectV2Field {
                    id
                    name
                    dataType
                  }
                }
              }
            }
          }
        }
    """, {"projectId": project_id})

    node = fields_data["node"]
    field_nodes = node.get("fields", {}).get("nodes", [])

    start_field_id = None
    end_field_id = None

    for field in field_nodes:
        name = (field.get("name") or "").strip().lower()
        if not name:
            continue
        if field.get("dataType") == "DATE":
            if PROJECT_START_FIELD_NAME and name == PROJECT_START_FIELD_NAME.lower():
                start_field_id = field["id"]
            if PROJECT_END_FIELD_NAME and name == PROJECT_END_FIELD_NAME.lower():
                end_field_id = field["id"]

    if PROJECT_START_FIELD_NAME and not start_field_id:
        start_field_id = _create_date_field(project_id, PROJECT_START_FIELD_NAME)
    if PROJECT_END_FIELD_NAME and not end_field_id:
        end_field_id = _create_date_field(project_id, PROJECT_END_FIELD_NAME)

    _PROJECT_CACHE = {
        "project_id": project_id,
        "owner_id": owner_id,
        "start_field_id": start_field_id,
        "end_field_id": end_field_id,
    }
    return _PROJECT_CACHE

def _update_date_field(project_id, item_id, field_id, date_value):
    if not field_id:
        return
    gh_graphql("""
        mutation($projectId: ID!, $itemId: ID!, $fieldId: ID!, $date: Date) {
          updateProjectV2ItemFieldValue(input: {
            projectId: $projectId,
            itemId: $itemId,
            fieldId: $fieldId,
            value: { date: $date }
          }) {
            projectV2Item { id }
          }
        }
    """, {
        "projectId": project_id,
        "itemId": item_id,
        "fieldId": field_id,
        "date": date_value,
    })

def sync_project_item(issue_number, due_date, checked):
    context = ensure_project_context()
    if not context or not issue_number:
        return
    issue_query = gh_graphql("""
        query($owner: String!, $repo: String!, $number: Int!) {
          repository(owner: $owner, name: $repo) {
            issue(number: $number) {
              id
              projectItems(first: 50) {
                nodes {
                  id
                  project { id }
                }
              }
            }
          }
        }
    """, {"owner": OWNER, "repo": REPO_NAME, "number": int(issue_number)})

    repo = issue_query.get("repository")
    if not repo or not repo.get("issue"):
        return
    issue = repo["issue"]
    issue_id = issue["id"]
    project_id = context["project_id"]
    item_nodes = issue.get("projectItems", {}).get("nodes", [])

    item_id = None
    for item in item_nodes:
        project = item.get("project") or {}
        if project.get("id") == project_id:
            item_id = item["id"]
            break

    created = False
    if not item_id:
        mutation = gh_graphql("""
            mutation($projectId: ID!, $contentId: ID!) {
              addProjectV2ItemById(input: {
                projectId: $projectId,
                contentId: $contentId
              }) {
                item { id }
              }
            }
        """, {"projectId": project_id, "contentId": issue_id})
        item_id = mutation["addProjectV2ItemById"]["item"]["id"]
        created = True

    if context["start_field_id"] and created and not checked:
        today = datetime.datetime.utcnow().date().isoformat()
        _update_date_field(project_id, item_id, context["start_field_id"], today)

    if context["end_field_id"]:
        if not checked and due_date:
            _update_date_field(project_id, item_id, context["end_field_id"], due_date)
        else:
            # Clear end date when no due date is present or the task is completed
            _update_date_field(project_id, item_id, context["end_field_id"], None)

def list_md_files():
    for p in Path(".").rglob("*.md"):
        if any(part in IGNORE_DIRS for part in p.parts):
            continue
        yield p

def task_id(path, line_no, text):
    h = hashlib.sha1(f"{path}:{line_no}:{text}".encode("utf-8")).hexdigest()
    return h[:12]

def load_cache():
    if CACHE_PATH.exists():
        return json.loads(CACHE_PATH.read_text(encoding="utf-8"))
    return {"open": {}, "closed": []}

def save_cache(cache):
    CACHE_PATH.parent.mkdir(parents=True, exist_ok=True)
    CACHE_PATH.write_text(json.dumps(cache, indent=2), encoding="utf-8")

def ensure_labels(labels):
    existing = {}
    page = 1
    while True:
        res = gh_api("GET", f"/repos/{REPO}/labels?per_page=100&page={page}")
        if not res: break
        for l in res:
            existing[l["name"]] = True
        page += 1
    to_create = [l for l in labels if l not in existing]
    for name in to_create:
        gh_api("POST", f"/repos/{REPO}/labels", {"name": name})

def main():
    cache = load_cache()
    cache.setdefault("open", {})
    cache.setdefault("closed", [])
    # Synchronize cache with existing GitHub issues so we do not recreate duplicates.
    refresh_cache_from_github(cache)
    seen_ids = set()
    new_tasks = []

    for md in list_md_files():
        rel = md.as_posix()
        lines = md.read_text(encoding="utf-8", errors="ignore").splitlines()
        section = None
        for i, line in enumerate(lines, start=1):
            if line.startswith("#"):
                section = line.strip("# ").strip()
            m = checkbox_re.match(line)
            if not m: continue
            checked = m.group("checked").strip().lower() == "x"
            text = m.group("text").strip()
            tid = task_id(rel, i, text)
            seen_ids.add(tid)

            # Parse metadata
            due = None
            due_m = due_re.search(text)
            if due_m:
                due = due_m.group(1)
            labels = set(label_re.findall(text))
            prio = prio_re.search(text)
            if prio:
                labels.add(f"priority/{prio.group(1).lower()}")

            labels.add("from-markdown")

            # Build issue title (short) and body (rich)
            title = text
            # Nettoyage du title des marqueurs
            title = due_re.sub("", title)
            title = prio_re.sub("", title)
            title = re.sub(r"(?:^|\s)#[A-Za-z0-9._/-]+", "", title).strip()
            if len(title) > 120:
                title = title[:117] + "…"

            link = f"https://github.com/{REPO}/blob/{SHA}/{urllib.parse.quote(rel)}#L{i}"
            body = []
            body.append(f"Source: [{rel}:{i}]({link})")
            if section:
                body.append(f"Section: **{section}**")
            if due:
                body.append(f"**Due:** {due}")
            body.append("")
            body.append("```md")
            body.append(line.strip())
            body.append("```")
            body.append("")
            body.append(f"_Task ID: `{tid}`_")
            body = "\n".join(body)

            new_tasks.append({
                "id": tid,
                "checked": checked,
                "title": title,
                "body": body,
                "labels": sorted(labels),
                "due": due,
            })

    # Crée labels si besoin
    all_labels = sorted({l for t in new_tasks for l in t["labels"]})
    ensure_labels(all_labels)

    # Sync: créer/mettre à jour/fermer
    # 1) Créer/MAJ
    for t in new_tasks:
        issue_no = cache["open"].get(t["id"])
        if issue_no:
            # Update state/title/body/labels si besoin
            try:
                gh_api("PATCH", f"/repos/{REPO}/issues/{issue_no}", {
                    "title": t["title"],
                    "body": t["body"],
                    "labels": t["labels"],
                    "state": "closed" if t["checked"] else "open",
                })
            except Exception:
                # Si l’issue n’existe plus, on recrée
                issue_no = None

        if not issue_no:
            if t["checked"]:
                # Ne pas créer d’issue pour une tâche déjà cochée
                continue
            res = gh_api("POST", f"/repos/{REPO}/issues", {
                "title": t["title"],
                "body": t["body"],
                "labels": t["labels"],
            })
            issue_no = res["number"]
        cache["open"][t["id"]] = issue_no
        if issue_no:
            sync_project_item(issue_no, t.get("due"), t["checked"])

    # 2) Fermer les issues dont la ligne a disparu du repo
    vanished = [tid for tid in list(cache["open"].keys()) if tid not in seen_ids]
    for tid in vanished:
        issue_no = cache["open"].get(tid)
        if issue_no:
            try:
                gh_api("PATCH", f"/repos/{REPO}/issues/{issue_no}", {"state": "closed"})
            except Exception:
                pass
        cache["closed"].append(tid)
        del cache["open"][tid]

    save_cache(cache)

if __name__ == "__main__":
    main()
