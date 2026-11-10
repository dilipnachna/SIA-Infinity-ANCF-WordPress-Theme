#!/usr/bin/env python3
"""Regression: exactly three topical cards, thumbnail and headline only."""
import re
from urllib.request import urlopen

with urlopen("http://127.0.0.1:18087/solar-mission-launch/", timeout=20) as response:
    page = response.read().decode("utf-8", errors="replace")
section = re.search(r'<section class="sia-related">(.*?)</section>', page, flags=re.S)
assert section, "Related Stories section is missing"
html = section.group(1)
cards = re.findall(r'<article class="sia-story-card sia-story-card--standard sia-story-card--related">(.*?)</article>', html, flags=re.S)
assert len(cards) == 3, f"Expected three relevant cards, got {len(cards)}"
expected = {
    "Solar Mission Satellite Update",
    "Solar Mission Rocket Progress",
    "Solar Mission Research Report",
}
for card in cards:
    assert 'class="sia-media' in card, "Thumbnail link missing"
    assert 'class="sia-story-card__title"' in card, "Heading missing"
    assert 'class="sia-meta"' not in card, "Date/author metadata should not appear"
    assert 'class="sia-eyebrow"' not in card, "Category label should not appear"
    assert 'class="sia-story-card__excerpt"' not in card, "Description should not appear"
    titles = re.findall(r'<h3[^>]*>\s*<a[^>]*>(.*?)</a>\s*</h3>', card, flags=re.S)
    assert len(titles) == 1, "Each card must have exactly one heading"
    assert titles[0] in expected, f"Unrelated card selected: {titles[0]}"
    expected.remove(titles[0])
assert not expected, f"Missing topical cards: {expected}"
sidebar = re.search(r'<aside class="sia-single-sidebar".*?</aside>', page, flags=re.S)
if sidebar:
    for title in ("Solar Mission Satellite Update", "Solar Mission Rocket Progress", "Solar Mission Research Report"):
        assert title not in sidebar.group(0), "Related card duplicated in sidebar"
# Adversarial: same generic category and 'USA' tag do not establish topical similarity.
with urlopen(BASE := "http://127.0.0.1:18087/newport-fishermen-rescue/", timeout=20) as response:
    unrelated_page = response.read().decode("utf-8", errors="replace")
unrelated_section = re.search(r'<section class="sia-related">(.*?)</section>', unrelated_page, flags=re.S)
if unrelated_section:
    assert "Social Security Benefits Forecast 2032" not in unrelated_section.group(1), "False match: generic USA tag"
    assert "James Talarico Campaign on Saturday Night Live" not in unrelated_section.group(1), "False match: same broad category"
with urlopen("http://127.0.0.1:18087/travel-jaisalmer-road/", timeout=20) as response:
    travel_page = response.read().decode("utf-8", errors="replace")
travel_related = re.search(r'<section class="sia-related">(.*?)</section>', travel_page, flags=re.S)
if travel_related:
    assert "Army Officer Injured in Jaisalmer Road Accident" not in travel_related.group(1), "False match: travel guide vs. road accident"

print("PASS: exactly three topical Related Stories cards with thumbnail and heading only")
