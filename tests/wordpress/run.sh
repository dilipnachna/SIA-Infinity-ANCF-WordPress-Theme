#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/../.." && pwd)"
qa_dir="$root/tests/wordpress"
cd "$root"
umask 077
printf 'QA_DB_PASS=%s\nQA_ROOT_PASS=%s\n' "$(openssl rand -hex 20)" "$(openssl rand -hex 20)" > "$qa_dir/.env"
compose() { docker compose --project-directory "$qa_dir" -f "$qa_dir/compose.yaml" "$@"; }
cleanup() {
    code=$?
    if [ "$code" -ne 0 ]; then
        docker logs sia-ancf-ci-wp --tail 90 || true
        docker logs sia-ancf-ci-db --tail 35 || true
    fi
    compose down -v --remove-orphans || true
    rm -f "$qa_dir/.env" "$qa_dir/fixture.json"
}
trap cleanup EXIT
compose config --quiet
compose up -d
ready=0
for i in $(seq 1 75); do
    if curl -fsS --max-time 4 'http://127.0.0.1:18087/wp-admin/install.php?step=1' >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 2
done
if [ "$ready" != 1 ]; then
    echo "WordPress test installer was not reachable" >&2
    exit 1
fi
docker cp theme/sia-ancf-news sia-ancf-ci-wp:/var/www/html/wp-content/themes/
docker exec sia-ancf-ci-wp php /qa-scripts/install.php
docker exec sia-ancf-ci-wp php /qa-scripts/fixture.php > "$qa_dir/fixture.json"
python3 -m json.tool "$qa_dir/fixture.json" >/dev/null
python3 "$qa_dir/http_assertions.py" generic
docker exec sia-ancf-ci-wp php /qa-scripts/config.php categories
python3 "$qa_dir/http_assertions.py" categories
docker exec sia-ancf-ci-wp php /qa-scripts/config.php custom-slugs
python3 "$qa_dir/http_assertions.py" custom-slugs
docker exec sia-ancf-ci-wp php /qa-scripts/config.php news-only
python3 "$qa_dir/http_assertions.py" news-only
echo "PASS: All 4 real WordPress runtime configurations"
