#!/usr/bin/env python3
"""Build the /changelog data of the HOP apps from the plain-language CHANGELOG.md files.

One repository:   python3 scripts/build-changelog.py --repo HEL.ink
The whole family: python3 scripts/build-changelog.py --repo HEL.ink --family /path/to/checkouts \
                      --output storage/app/changelog.json
Check (CI):       python3 scripts/build-changelog.py --repo HEL.ink --check

The only input is the CHANGELOG.md of each repository (no git history, no network, no GitHub login),
so a build gives the same bytes every time. Release headings look like

    ## [1.3.2] - 2026-11-14 - Short title for the release

    Optional one-line summary.

    ### Fixed
    - **Where it is.** What changed and what it means for the person using the app, and why.

Section names: Added, Changed, Fixed, Security, Removed, Deprecated. `## [Unreleased]` is never shown.
`--check` fails when a release has no title or no items, an item does not start with a bold place, or any
text holds a word that does not belong on a public page (the word "train", "Merge pull request", branch
names, file names in backticks), and, when the snapshot is committed, when it differs from a fresh build
(for the hub, only the entries of HEL.ink are compared: the other apps are added by a --family build).
"""
import argparse
import json
import pathlib
import re
import sys

PUBLIC = {"HEL.ink": "HOP ID", "LinkHop": "LinkHop", "BioHop": "BioHop", "PixelHop": "PixelHop", "NoteHop": "NoteHop", "FileHop": "FileHop", "PayHop": "PayHop"}
LARAVEL = {"HEL.ink", "LinkHop", "BioHop"}
KINDS = {"added", "changed", "fixed", "security", "removed", "deprecated"}
HEADING = re.compile(r"^## \[(?P<version>[^\]]+)\](?: - (?P<date>\d{4}-\d\d-\d\d))?(?: - (?P<title>.+))?$")
FORBIDDEN = [
    (re.compile(r"\btrains?\b|kereta", re.I), "the word train/kereta"),
    (re.compile(r"merge pull request|remote-tracking|co-authored|generated with", re.I), "git or tool wording"),
    (re.compile(r"(?<![\w/])(?:fix|feat|docs|chore|release|hotfix|refactor)/[\w.-]+"), "a branch name"),
    (re.compile(r"`"), "code or file names in backticks"),
]


def parse(text, repo):
    """Return (releases, problems) for one CHANGELOG.md."""
    releases, problems, current, kind = [], [], None, None
    for number, line in enumerate(text.splitlines(), 1):
        where = f"{repo} CHANGELOG.md:{number}"
        if line.startswith("## "):
            current, kind = None, None
            m = HEADING.match(line.rstrip())
            if not m:
                problems.append(f"{where}: unreadable release heading")
                continue
            if m["version"].lower() == "unreleased":
                continue
            if not m["date"] or not m["title"]:
                problems.append(f"{where}: release {m['version']} needs a date and a title (## [x.y.z] - YYYY-MM-DD - Title)")
                continue
            current = {"version": m["version"], "date": m["date"], "title": m["title"].strip(), "summary": "", "items": [], "line": number}
            releases.append(current)
        elif current is None:
            continue
        elif line.startswith("### "):
            kind = line[4:].strip().lower()
            if kind not in KINDS:
                problems.append(f"{where}: unknown section {line[4:].strip()!r}")
                kind = None
        elif line.startswith("- "):
            if kind is None:
                problems.append(f"{where}: an item outside a section (Added, Changed, Fixed, ...)")
                continue
            m = re.match(r"^- \*\*(?P<where>[^*]+?)\.?\*\* (?P<text>\S.*)$", line)
            if not m:
                problems.append(f"{where}: an item starts with a bold place, e.g. - **Settings.** What changed, what it means, why")
                continue
            current["items"].append({"type": kind, "where": m["where"].strip(), "text": m["text"].strip(), "line": number})
        elif line.strip() and kind is None and not line.startswith("["):
            current["summary"] = (current["summary"] + " " + line.strip()).strip()
        elif line.strip() and kind is not None and not line.startswith("["):
            problems.append(f"{where}: text under a section must be a '- **Place.** ...' item")
    for r in releases:
        if not r["items"]:
            problems.append(f"{repo} CHANGELOG.md:{r['line']}: release {r['version']} has no items")
        seen = [r["title"], r["summary"]] + [i["where"] + " " + i["text"] for i in r["items"]]
        for blob in seen:
            for pattern, what in FORBIDDEN:
                if pattern.search(blob):
                    problems.append(f"{repo} CHANGELOG.md release {r['version']}: {what} in {blob[:60]!r}")
    versions = [r["version"] for r in releases]
    problems += [f"{repo} CHANGELOG.md: version {v} appears twice" for v in sorted(set(versions)) if versions.count(v) > 1]
    return releases, problems


def entries_of(repo, releases):
    out = []
    for r in releases:
        out.append({
            "kind": "tag",  # the label older readers already translate as "Release"
            "date": r["date"] + "T00:00:00Z",
            "title": r["title"],
            "url": f"https://github.com/navi-crwn/{repo}/releases/tag/v{r['version']}",
            "id": "v" + r["version"],
            "app": PUBLIC[repo],
            "repo": repo,
            "version": r["version"],
            "summary": r["summary"],
            "items": [{"type": i["type"], "where": i["where"], "text": i["text"]} for i in r["items"]],
        })
    return out


def version_key(v):
    return tuple(int(p) if p.isdigit() else 0 for p in re.split(r"[.+-]", v))


def build(roots):
    """roots: {repo: directory}. Returns (document, problems)."""
    entries, sources, problems = [], {}, []
    for repo, root in roots.items():
        path = root / "CHANGELOG.md"
        if not path.is_file():
            problems.append(f"{repo}: {path} not found")
            continue
        releases, found = parse(path.read_text(encoding="utf-8"), repo)
        problems += found
        if releases:
            sources[repo] = max((r["version"] for r in releases), key=version_key)
        entries += entries_of(repo, releases)
    entries.sort(key=lambda e: (e["date"], version_key(e["version"]), e["repo"]), reverse=True)
    return {"schema": 2, "sources": sources, "entries": entries}, problems


def text_of(document):
    return json.dumps(document, ensure_ascii=False, indent=2) + "\n"


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--repo", choices=PUBLIC, required=True)
    parser.add_argument("--family", type=pathlib.Path, help="directory that holds a checkout of every public repository")
    parser.add_argument("--check", action="store_true", help="validate CHANGELOG.md and compare the committed snapshot; write nothing")
    parser.add_argument("--output", type=pathlib.Path, help="write (or, with --check, compare) this file instead of the repository snapshot")
    args = parser.parse_args()
    root = pathlib.Path(__file__).resolve().parents[1]
    snapshot = args.output or root / ("resources/changelog.json" if args.repo in LARAVEL else "web/changelog.json")
    roots = {repo: args.family / repo for repo in PUBLIC} if args.family else {args.repo: root}
    document, problems = build(roots)
    if args.check:
        if not args.family and snapshot.is_file():
            have = json.loads(snapshot.read_text(encoding="utf-8"))
            mine = lambda d: [e for e in d["entries"] if e["repo"] == args.repo]
            if have.get("schema") != 2:
                problems.append(f"{snapshot}: schema is not 2, rebuild it")
            elif mine(have) != mine(document):
                problems.append(f"{snapshot}: the entries of {args.repo} differ from CHANGELOG.md, rebuild it: python3 scripts/build-changelog.py --repo {args.repo}")
        elif not args.family:
            problems.append(f"{snapshot} is missing, build it: python3 scripts/build-changelog.py --repo {args.repo}")
        if problems:
            sys.exit("changelog check failed:\n  " + "\n  ".join(problems))
        print(f"changelog is plain and current: {len(document['entries'])} releases")
        return
    if problems:
        sys.exit("cannot build the changelog:\n  " + "\n  ".join(problems))
    snapshot.parent.mkdir(parents=True, exist_ok=True)
    snapshot.write_text(text_of(document), encoding="utf-8", newline="\n")
    print(f"{snapshot}: {len(document['entries'])} releases from {len(roots)} repositories")


if __name__ == "__main__":
    main()
