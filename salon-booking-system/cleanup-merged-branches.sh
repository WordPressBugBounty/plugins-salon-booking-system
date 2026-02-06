#!/bin/bash
# Delete remote branches that are already merged into develop
# SAFE: Only deletes branches that are fully merged

echo "🔍 Finding branches merged into develop..."

# Branches to NEVER delete
PROTECTED_BRANCHES="develop|master|main|HEAD"

# Get list of merged branches
git branch -r --merged origin/develop | \
  grep "origin/" | \
  grep -v "$PROTECTED_BRANCHES" | \
  sed 's/origin\///' | \
  while read branch; do
    echo "  ✓ Merged: $branch"
  done

echo ""
echo "📊 Found $(git branch -r --merged origin/develop | grep 'origin/' | grep -v "$PROTECTED_BRANCHES" | wc -l) merged branches"
echo ""
read -p "❓ Delete these merged branches from Bitbucket? (yes/no): " confirm

if [ "$confirm" = "yes" ]; then
  git branch -r --merged origin/develop | \
    grep "origin/" | \
    grep -v "$PROTECTED_BRANCHES" | \
    sed 's/origin\///' | \
    xargs -I {} sh -c 'echo "🗑️  Deleting: {}" && git push origin --delete {}'
  
  echo "✅ Done! Deleted merged branches."
else
  echo "❌ Cancelled. No branches deleted."
fi

