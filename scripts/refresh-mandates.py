#!/usr/bin/env python3
"""Refresh the embedded live-mandates fallback snapshot in index.html from the
Atlas public job feed. The site fetches the feed live in the browser; this keeps
the no-JS / pre-fetch / fetch-failure fallback current too. Run by CI on a
schedule. Exits 0 and rewrites index.html only when the snapshot changed."""
import json, re, sys, urllib.request, pathlib

API = "https://api.recruitwithatlas.com/public-graphql"
AGENCY = "Cordell-Partners"
QUERY = ("query($input: PublicJobOpeningInput!, $limit: Int!, $page: Int!) { "
         "publicJobOpenings(input: $input, limit: $limit, page: $page) { "
         "items { id jobRole salary location { locality } } } }")

def clean(s):
    return re.sub(r"\s+", " ", (s or "").replace("—", "-").replace("–", "-")).strip()

def fetch():
    body = json.dumps({"query": QUERY,
                       "variables": {"input": {"agencyAlias": AGENCY}, "limit": 100, "page": 1}}).encode()
    req = urllib.request.Request(API, data=body,
        headers={"content-type": "application/json", "apollo-require-preflight": "true"})
    with urllib.request.urlopen(req, timeout=30) as r:
        data = json.load(r)
    items = (((data or {}).get("data") or {}).get("publicJobOpenings") or {}).get("items")
    if not items:
        print("No items returned; leaving snapshot unchanged.", file=sys.stderr)
        sys.exit(0)
    return [[it["id"], clean(it.get("jobRole")),
             ((it.get("location") or {}).get("locality") or ""), clean(it.get("salary"))]
            for it in items]

def main():
    idx = pathlib.Path(__file__).resolve().parent.parent / "index.html"
    html = idx.read_text()
    rows = fetch()
    snap = json.dumps(rows, ensure_ascii=False, separators=(",", ":"))
    new_line = "  var SNAPSHOT = " + snap + ";"
    updated, n = re.subn(r"  var SNAPSHOT = \[.*?\];", lambda _m: new_line, html, count=1, flags=re.S)
    if n != 1:
        print("SNAPSHOT marker not found", file=sys.stderr); sys.exit(1)
    if updated == html:
        print(f"Snapshot already current ({len(rows)} roles)."); return
    idx.write_text(updated)
    print(f"Snapshot refreshed: {len(rows)} roles.")

if __name__ == "__main__":
    main()
