"""Unit tests for scripts/build-changelog.py: python3 -m unittest scripts/test_build_changelog.py"""
import importlib.util
import pathlib
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("bc", pathlib.Path(__file__).with_name("build-changelog.py"))
bc = importlib.util.module_from_spec(spec)
spec.loader.exec_module(bc)

GOOD = """# Changelog

## [Unreleased]

## [1.2.0] - 2026-11-14 - A plain title

One line about the release.

### Fixed

- **Settings.** The tab stays put after you revoke a token, so you see the result, because the page used to reload.

## [1.1.0] - 2026-11-10 - Older release

### Added

- **Home.** A new section, which helps, because people asked.

[Unreleased]: https://example.test
"""


def problems_of(text):
    return bc.parse(text, "HEL.ink")[1]


class ParseTest(unittest.TestCase):
    def test_a_good_file_gives_releases_newest_first_with_items(self):
        with tempfile.TemporaryDirectory() as d:
            root = pathlib.Path(d)
            (root / "CHANGELOG.md").write_text(GOOD, encoding="utf-8")
            doc, problems = bc.build({"HEL.ink": root})
        self.assertEqual(problems, [])
        self.assertEqual(doc["schema"], 2)
        self.assertEqual([e["version"] for e in doc["entries"]], ["1.2.0", "1.1.0"])
        self.assertEqual(doc["sources"], {"HEL.ink": "1.2.0"})
        first = doc["entries"][0]
        self.assertEqual(first["kind"], "tag")  # older readers know this kind
        self.assertEqual(first["summary"], "One line about the release.")
        self.assertEqual(first["items"][0]["where"], "Settings")
        self.assertEqual(first["id"], "v1.2.0")
        for field in ("kind", "date", "title", "url", "id", "app", "repo"):
            self.assertIn(field, first)

    def test_unreleased_is_never_shown(self):
        releases, _ = bc.parse(GOOD, "HEL.ink")
        self.assertNotIn("Unreleased", [r["version"] for r in releases])

    def test_a_release_without_a_title_is_refused(self):
        self.assertTrue(problems_of(GOOD.replace(" - A plain title", "")))

    def test_a_release_without_items_is_refused(self):
        self.assertTrue(any("no items" in p for p in problems_of("## [1.0.0] - 2026-11-01 - Empty\n\n### Added\n")))

    def test_an_item_must_name_its_place(self):
        self.assertTrue(problems_of(GOOD.replace("- **Settings.** ", "- ")))

    def test_words_that_do_not_belong_on_the_page_are_refused(self):
        for bad in ("Train 6 fixes", "Merge pull request #5", "from fix/token-tab", "see `app.php`", "Kereta 5"):
            self.assertTrue(problems_of(GOOD.replace("The tab stays put", bad)), bad)

    def test_the_word_constraint_does_not_hit_ordinary_words(self):
        self.assertEqual(problems_of(GOOD.replace("The tab stays put", "Trained staff and a strain of docs/ pages are fine")), [])

    def test_the_same_version_twice_is_refused(self):
        self.assertTrue(problems_of(GOOD + "\n## [1.1.0] - 2026-11-09 - Again\n\n### Added\n\n- **Home.** X, y, z.\n"))


if __name__ == "__main__":
    unittest.main()
