#!/usr/bin/env python3
"""Snapshot all main-branch commits, tags and merged PRs; no runtime GitHub calls.

Run from a checkout: python3 scripts/build-changelog.py --repo HEL.ink
Use --family /path/to/checkouts in the hub to aggregate the five public apps.
Requires git and authenticated gh with read access. Never reads working files.
"""
import argparse
import datetime
import json
import pathlib
import re
import subprocess

PUBLIC = {"HEL.ink": "HOP ID", "LinkHop": "LinkHop", "BioHop": "BioHop", "PixelHop": "PixelHop", "NoteHop": "NoteHop"}


def command(args, cwd):
    return subprocess.check_output(args, cwd=cwd, text=True)


def display_title(title):
    """Keep product history readable; source URLs retain the original record."""
    title = re.sub(r"^[\w-]+/(?=[\w-]+ )", "", title)
    title = re.sub(r"^(Merge main into) .+$", r"\1 the feature branch", title)
    return re.sub(r"(?<=Ignore the local )\.[\w-]+/", "workspace", title)


def history(root, repo):
    revision = command(["git", "rev-parse", "origin/main"], root).strip()
    raw = command(["git", "log", "origin/main", "--format=%H%x1f%cI%x1f%s%x1e"], root)
    commits = []
    for row in raw.split("\x1e"):
        row = row.strip()
        if not row:
            continue
        sha, date, title = row.split("\x1f", 2)
        commits.append({"kind": "commit", "date": date, "title": title, "url": f"https://github.com/navi-crwn/{repo}/commit/{sha}", "id": sha[:8]})
    tags = []
    for tag in command(["git", "tag", "--merged", "origin/main"], root).splitlines():
        date = command(["git", "log", "-1", "--format=%cI", f"refs/tags/{tag}^{{}}"], root).strip()
        from urllib.parse import quote
        tags.append({"kind": "tag", "date": date, "title": tag, "url": f"https://github.com/navi-crwn/{repo}/releases/tag/{quote(tag, safe='')}", "id": tag})
    # REST pagination is unbounded; gh pr list's default limit would omit history.
    raw_prs = command(["gh", "api", "--paginate", "--jq", '.[] | select(.merged_at != null and .base.ref == "main") | {merged_at,title,html_url,number} | @json', f"repos/navi-crwn/{repo}/pulls?state=closed&base=main&per_page=100"], root)
    prs = []
    for row in raw_prs.splitlines():
        p = json.loads(row)
        prs.append({"kind": "pr", "date": p["merged_at"], "title": display_title(p["title"]), "url": p["html_url"], "id": f'#{p["number"]}'})
    pr_titles = {entry["id"]: entry["title"] for entry in prs}
    for entry in commits:
        merge = re.match(r"Merge pull request (#\d+) from ", entry["title"])
        if merge and merge[1] in pr_titles:
            entry["title"] = f"Merge pull request {merge[1]}: {pr_titles[merge[1]]}"
        else:
            entry["title"] = display_title(entry["title"])
    entries = commits + tags + prs
    for entry in entries:
        entry["app"] = PUBLIC[repo]
        entry["repo"] = repo
    return revision, entries


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repo", choices=PUBLIC, required=True)
    parser.add_argument("--family", type=pathlib.Path)
    args = parser.parse_args()
    root = pathlib.Path(__file__).resolve().parents[1]
    repos = PUBLIC if args.family else {args.repo: PUBLIC[args.repo]}
    revisions, entries = {}, []
    for repo in repos:
        revision, rows = history(args.family / repo if args.family else root, repo)
        revisions[repo] = revision
        entries.extend(rows)
    entries.sort(key=lambda e: (datetime.datetime.fromisoformat(e["date"].replace("Z", "+00:00")), e["repo"], e["id"]), reverse=True)
    output = root / ("web/changelog.json" if args.repo in ["PixelHop", "NoteHop"] else "resources/changelog.json")
    output.write_text(json.dumps({"schema": 1, "sources": revisions, "entries": entries}, ensure_ascii=False, indent=2) + "\n")
    print(f"{output}: {len(entries)} entries from {len(revisions)} repositories")


if __name__ == "__main__":
    main()
