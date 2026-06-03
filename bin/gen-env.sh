#!/usr/bin/env bash
set -euo pipefail

EXAMPLE="${ENV_EXAMPLE:-.env.example}"
TARGET="${ENV_FILE:-.env}"

[ -f "$EXAMPLE" ] || { echo "Не найден $EXAMPLE" >&2; exit 1; }

declare -A current
if [ -f "$TARGET" ]; then
    while IFS= read -r line || [ -n "$line" ]; do
        case "$line" in ''|\#*) continue ;; esac
        current["${line%%=*}"]="${line#*=}"
    done < "$TARGET"
fi

tmp="$(mktemp)"
declare -A seen

while IFS= read -r line <&3 || [ -n "$line" ]; do
    case "$line" in
        '') printf '\n' >> "$tmp"; continue ;;
        \#*) continue ;;
    esac
    key="${line%%=*}"
    seen["$key"]=1
    if [ -n "${current[$key]:-}" ]; then
        value="${current[$key]}"
    else
        printf 'Значение для %s [%s]: ' "$key" "${line#*=}" >&2
        read -r answer || answer=""
        value="${answer:-${line#*=}}"
    fi
    printf '%s=%s\n' "$key" "$value" >> "$tmp"
done 3< "$EXAMPLE"

if [ -f "$TARGET" ]; then
    while IFS= read -r line || [ -n "$line" ]; do
        case "$line" in ''|\#*) continue ;; esac
        [ -z "${seen[${line%%=*}]:-}" ] && printf '%s\n' "$line" >> "$tmp"
    done < "$TARGET"
fi

mv "$tmp" "$TARGET"
echo "Готово: $TARGET" >&2
