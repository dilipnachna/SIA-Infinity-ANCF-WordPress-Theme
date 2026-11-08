#!/usr/bin/env python3
"""HTTP-level checks against a disposable loopback-only WordPress installation."""
import json
import re
import sys
from urllib.error import HTTPError
from urllib.request import urlopen

BASE = "http://127.0.0.1:18087"
MODE = sys.argv[1] if len(sys.argv) == 2 else ""

def fetch(path):
    try:
        with urlopen(BASE + path, timeout=15) as response:
            return response.status, response.read().decode("utf-8", errors="replace")
    except HTTPError as error:
        return error.code, error.read().decode("utf-8", errors="replace")

def assert_page(path):
    code, body = fetch(path)
    assert code == 200, f"{path}: expected HTTP 200, got {code}"
    assert "Fatal error" not in body, path
    return body

def schema_types(document):
    result = []
    for text in re.findall(
        r'<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>',
        document,
        re.I | re.S,
    ):
        raw = json.loads(text)
        for item in raw.get("@graph", [raw]) if isinstance(raw, dict) else raw:
            if isinstance(item, dict):
                value = item.get("@type")
                result.extend(value if isinstance(value, list) else [value])
    return result

homepage = assert_page("/")
assert ("Climate Field Research Notes" if MODE == "custom-slugs" else "Universal Science Bulletin") in homepage, "Relevant fixture story missing on homepage"
assert len(re.findall(r"<h1\b", homepage, re.I)) == 1, "Homepage must have one H1"
assert re.search(r'<meta\s+name="description"\s+content="[^"]+"', homepage), "Homepage meta description absent"
assert_page("/universal-science-bulletin/")
assert_page("/city-council-public-report/")
assert_page("/feed/")
assert_page("/sitemap_index.xml")
assert_page("/wp-sitemap.xml")
assert_page("/wp-content/themes/sia-ancf-news/style.css")

if MODE == "generic":
    assert "Article" in schema_types(assert_page("/universal-science-bulletin/"))
    code, _ = fetch("/news-sitemap.xml")
    assert code == 404, "A site without a news category must not publish an all-posts news sitemap by default"
elif MODE == "categories":
    assert "NewsArticle" in schema_types(assert_page("/universal-science-bulletin/"))
    assert "Article" in schema_types(assert_page("/city-council-public-report/"))
    sitemap = assert_page("/news-sitemap.xml")
    assert "universal-science-bulletin" in sitemap
    assert "city-council-public-report" not in sitemap
elif MODE == "custom-slugs":
    assert "NewsArticle" in schema_types(assert_page("/climate-field-research/"))
    assert "Article" in schema_types(assert_page("/library-opening/"))
    assert "Article" in schema_types(assert_page("/universal-science-bulletin/"))
    sitemap = assert_page("/news-sitemap.xml")
    assert "climate-field-research" in sitemap
    assert "universal-science-bulletin" not in sitemap
elif MODE == "news-only":
    assert "NewsArticle" in schema_types(assert_page("/universal-science-bulletin/"))
    assert "NewsArticle" in schema_types(assert_page("/city-council-public-report/"))
    sitemap = assert_page("/news-sitemap.xml")
    assert "city-council-public-report" in sitemap
    assert "universal-science-bulletin" in sitemap
    assert len(re.findall(r"<loc>", sitemap)) >= 8
else:
    raise SystemExit(f"Unrecognized mode: {MODE}")
print(f"PASS: WordPress real HTTP compatibility ({MODE})")
