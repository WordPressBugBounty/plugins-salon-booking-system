#!/bin/bash
# Delete remote branches older than specified date
# Use with caution!

echo "🔍 Finding old branches..."

# Branches to NEVER delete
PROTECTED_BRANCHES="develop|master|main|HEAD|release|hotfix|production"

# How old? (format: YYYY-MM-DD)
CUTOFF_DATE="${1:-2024-01-01}"

echo "📅 Cutoff date: $CUTOFF_DATE"
echo ""

# List branches older than cutoff date
git for-each-ref --sort=-committerdate --format='%(refname:short)|%(committerdate:short)' refs/remotes/origin | \
  grep -v -E "$PROTECTED_BRANCHES" | \
  while IFS='|' read branch date; do
    if [[ "$date" < "$CUTOFF_DATE" ]]; then
      branch_name=$(echo "$branch" | sed 's/origin\///')
      echo "  📅 $date - $branch_name"
    fi
  done > /tmp/old-branches.txt

COUNT=$(cat /tmp/old-branches.txt | wc -l | tr -d ' ')

echo ""
echo "📊 Found $COUNT branches older than $CUTOFF_DATE"
echo ""
echo "Preview (first 20):"
head -20 /tmp/old-branches.txt
echo ""

if [ "$COUNT" -gt 0 ]; then
  read -p "❓ Delete these $COUNT old branches from Bitbucket? (yes/no): " confirm
  
  if [ "$confirm" = "yes" ]; then
    cat /tmp/old-branches.txt | cut -d' ' -f3 | \
      xargs -I {} sh -c 'echo "🗑️  Deleting: {}" && git push origin --delete {}'
    
    echo "✅ Done! Deleted $COUNT old branches."
  else
    echo "❌ Cancelled. No branches deleted."
  fi
else
  echo "✅ No branches older than $CUTOFF_DATE found."
fi

rm -f /tmp/old-branches.txt

