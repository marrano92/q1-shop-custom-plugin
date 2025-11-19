#!/bin/bash

# Script to install Git hooks for q1-shop-custom-plugin
# This script copies the pre-commit hook to .git/hooks/

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
HOOKS_DIR="$SCRIPT_DIR/hooks"
GIT_HOOKS_DIR="$SCRIPT_DIR/.git/hooks"

if [ ! -d "$GIT_HOOKS_DIR" ]; then
    echo "Error: .git/hooks directory not found. Are you in a Git repository?"
    exit 1
fi

if [ ! -f "$HOOKS_DIR/pre-commit" ]; then
    echo "Error: hooks/pre-commit not found."
    exit 1
fi

# Copy pre-commit hook
cp "$HOOKS_DIR/pre-commit" "$GIT_HOOKS_DIR/pre-commit"
chmod +x "$GIT_HOOKS_DIR/pre-commit"

echo "✓ Git hooks installed successfully!"
echo "  - pre-commit: Auto-increments JavaScript version on commit"

