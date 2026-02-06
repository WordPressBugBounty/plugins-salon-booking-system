#!/bin/bash
# List all remote branches sorted by date (newest first)
# Export to CSV for review

OUTPUT_FILE="remote-branches-$(date +%Y%m%d).csv"

echo "📋 Generating branch list..."
echo ""

# Create CSV header
echo "Branch,Last Commit Date,Author,Merged into develop?" > "$OUTPUT_FILE"

# List all branches with details
git for-each-ref --sort=-committerdate --format='%(refname:short)|%(committerdate:short)|%(authorname)' refs/remotes/origin | \
  grep -v "HEAD" | \
  while IFS='|' read branch date author; do
    branch_name=$(echo "$branch" | sed 's/origin\///')
    
    # Check if merged into develop
    if git branch -r --merged origin/develop | grep -q "origin/$branch_name"; then
      merged="YES"
    else
      merged="NO"
    fi
    
    echo "$branch_name,$date,$author,$merged" >> "$OUTPUT_FILE"
  done

echo "✅ Branch list saved to: $OUTPUT_FILE"
echo ""
echo "📊 Statistics:"
echo "  Total branches: $(tail -n +2 "$OUTPUT_FILE" | wc -l)"
echo "  Merged into develop: $(grep ',YES$' "$OUTPUT_FILE" | wc -l)"
echo "  Not merged: $(grep ',NO$' "$OUTPUT_FILE" | wc -l)"
echo ""
echo "💡 Tip: Open this CSV in Excel/Google Sheets to review and decide which to keep/delete"

