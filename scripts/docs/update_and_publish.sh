#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

COMMIT_MESSAGE="${1:-docs: refresh backend documentation}"
VENV="$ROOT/.venv-docs"
WORKFLOW="documentation.yml"

required_commands=(composer git npx php python3)
for command_name in "${required_commands[@]}"; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        printf 'Required command is not installed: %s\n' "$command_name" >&2
        exit 1
    fi
done

BRANCH="$(git branch --show-current)"
if [[ -z "$BRANCH" ]]; then
    printf 'Cannot publish documentation from a detached HEAD.\n' >&2
    exit 1
fi

if ! git remote get-url origin >/dev/null 2>&1; then
    printf 'Git remote "origin" is not configured.\n' >&2
    exit 1
fi

# Documentation publishing must not silently commit application code or local files.
unexpected_changes=()
while IFS= read -r changed_path; do
    [[ -z "$changed_path" ]] && continue
    case "$changed_path" in
        docs/*|mkdocs.yml|README.md|requirements-docs.txt|scripts/docs/*|.github/workflows/documentation.yml|.gitignore)
            ;;
        *)
            unexpected_changes+=("$changed_path")
            ;;
    esac
done < <(git status --porcelain=v1 | sed -E 's/^...//' | sed -E 's/.* -> //')

if (( ${#unexpected_changes[@]} > 0 )); then
    printf 'Uncommitted non-documentation changes were found:\n' >&2
    printf '  %s\n' "${unexpected_changes[@]}" >&2
    printf 'Commit or stash those changes, then run this command again.\n' >&2
    exit 1
fi

printf 'Installing backend dependencies...\n'
composer install --no-interaction --no-progress --prefer-dist

printf 'Installing pinned documentation dependencies...\n'
if [[ ! -x "$VENV/bin/python" ]]; then
    python3 -m venv "$VENV"
fi
"$VENV/bin/python" -m pip install --disable-pip-version-check --requirement requirements-docs.txt

printf 'Regenerating OpenAPI and route documentation...\n'
"$VENV/bin/python" scripts/docs/build_openapi.py

printf 'Checking route coverage and documentation links...\n'
"$VENV/bin/python" scripts/docs/check_route_coverage.py
"$VENV/bin/python" scripts/docs/check_links.py

printf 'Validating OpenAPI...\n'
npx --yes @redocly/cli@2.2.2 lint docs/api/openapi.yaml --extends minimal

printf 'Building MkDocs in strict mode...\n'
"$VENV/bin/mkdocs" build --strict

git add -- \
    docs \
    mkdocs.yml \
    README.md \
    requirements-docs.txt \
    scripts/docs \
    .github/workflows/documentation.yml \
    .gitignore

git diff --cached --check

if git diff --cached --quiet; then
    printf 'No documentation changes need to be committed.\n'
else
    git commit -m "$COMMIT_MESSAGE"
fi

printf 'Pushing branch %s to origin...\n' "$BRANCH"
git push origin "$BRANCH"

COMMIT_SHA="$(git rev-parse HEAD)"
REMOTE_URL="$(git remote get-url origin)"
REPOSITORY="$(printf '%s' "$REMOTE_URL" | sed -E 's#^git@github.com:##; s#^https://github.com/##; s#\.git$##')"
OWNER="${REPOSITORY%%/*}"
REPOSITORY_NAME="${REPOSITORY##*/}"
OWNER_LOWERCASE="$(printf '%s' "$OWNER" | tr '[:upper:]' '[:lower:]')"
PAGES_URL="https://${OWNER_LOWERCASE}.github.io/${REPOSITORY_NAME}/"

if command -v gh >/dev/null 2>&1; then
    printf 'Waiting for the GitHub Actions documentation workflow...\n'
    run_id=""
    for _ in {1..12}; do
        run_id="$(gh run list --workflow "$WORKFLOW" --commit "$COMMIT_SHA" --limit 1 --json databaseId --jq '.[0].databaseId // empty')"
        [[ -n "$run_id" ]] && break
        sleep 5
    done

    if [[ -z "$run_id" ]]; then
        printf 'Push succeeded, but the documentation workflow was not found yet.\n' >&2
        printf 'Check: https://github.com/%s/actions/workflows/%s\n' "$REPOSITORY" "$WORKFLOW"
        exit 1
    fi

    gh run watch "$run_id" --exit-status
    printf 'Documentation deployment completed: %s\n' "$PAGES_URL"
else
    printf 'Push completed. Install and authenticate GitHub CLI to wait for deployment.\n'
    printf 'Expected documentation URL: %s\n' "$PAGES_URL"
fi
