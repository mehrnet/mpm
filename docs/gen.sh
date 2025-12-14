#!/bin/sh
################################################################################
# MPM Documentation Generator (gen.sh)
#
# PURPOSE:
#   Generates a single multilingual HTML documentation page from markdown files.
#   This script combines markdown documentation in English, French, and Persian
#   into a single dynamic HTML file with language switching capabilities.
#
# HOW IT WORKS:
#   1. Combines all markdown files (*.md) with language markers (<!-- LANG:en -->)
#   2. Filters out html-ignore blocks (content between <!--html-ignore--> and <!--/html-ignore-->)
#      - Useful for removing TOCs, cross-file references, or docs-specific content
#      - Uses XML-style closing tag syntax for familiarity
#   3. Converts combined markdown to HTML using 'marked' CLI tool
#   4. Processes HTML with awk to:
#      - Add unique IDs to all h1/h2 headings for navigation
#      - Wrap content sections with language-specific <section data-lang="xx">
#      - Generate a table of contents (navbar) from headings
#   5. Injects processed content and navbar into template.html placeholders
#   6. Removes documentation comment block and LANG markers from final output
#
# FILES:
#   - Input: INSTALL.md, USAGE.md, PACKAGES.md (+ _FA, _FR variants)
#   - Template: template.html (contains HTML structure, CSS, JS framework)
#   - Output: index.html (final single-page documentation)
#
# REQUIREMENTS:
#   - POSIX-compatible shell (sh, bash, zsh)
#   - 'marked' CLI tool for markdown to HTML conversion
#   - awk (standard on all Unix-like systems)
#
# USAGE:
#   ./gen.sh          # Generates index.html from markdown files
#
################################################################################

set -e

cd "$(dirname "$0")"

# Check template exists
if [ ! -f template.html ]; then
    echo "Error: template.html not found" >&2
    exit 1
fi

# Define files to process (in order)
FILES="
INSTALL.md
USAGE.md
PACKAGES.md
INSTALL_FA.md
USAGE_FA.md
PACKAGES_FA.md
USAGE_FR.md
INSTALL_FR.md
PACKAGES_FR.md
"

# Create temp files for HTML content
TMP_ALL_MD="/tmp/all_$$.md"
TMP_ALL_HTML="/tmp/all_$$.html"
TMP_NAVBAR="/tmp/navbar_$$.html"
TMP_COMBINED="/tmp/combined_$$.html"

# Combine all markdown files with language markers
{
    for file in $FILES; do
        if [ -f "$file" ]; then
            # Extract language code from filename
            # INSTALL.md -> en, INSTALL_FA.md -> fa, INSTALL_FR.md -> fr
            case "$file" in
                *_FA.md) lang="fa" ;;
                *_FR.md) lang="fr" ;;
                *_EN.md) lang="en" ;;
                *.md) lang="en" ;;
                *) lang="en" ;;
            esac
            echo "<!-- LANG:$lang -->"
            cat "$file"
        fi
    done
} > "$TMP_ALL_MD"

# Remove html-ignore blocks from markdown (for single-page HTML we don't need TOCs or cross-file references)
# This removes everything between <!--html-ignore--> and <!--/html-ignore--> markers (XML-style closing tag)
# Matches: <!--html-ignore-->, <!-- html-ignore -->, <!--  html-ignore  -->, etc.
# Example usage in markdown:
#   <!--html-ignore-->
#   ## Table of Contents
#   - [Installation](INSTALL.md)
#   <!--/html-ignore-->
sed -i '/<!--[[:space:]]*html-ignore[[:space:]]*-->/,/<!--[[:space:]]*\/html-ignore[[:space:]]*-->/d' "$TMP_ALL_MD"

# Convert combined markdown to HTML
marked "$TMP_ALL_MD" > "$TMP_ALL_HTML" 2>/dev/null

# Use the combined HTML as the combined content
cp "$TMP_ALL_HTML" "$TMP_COMBINED"
TMP_COMBINED_IDS="${TMP_COMBINED}.with_ids"

# Pass 1 & 2 combined: Process content and generate navbar with language wrapping
{
    awk '
    BEGIN {
        id_counter = 0
        current_lang = "en"
        prev_lang = "en"
        first_lang_marker = 1
    }
    /<!-- LANG:(en|fa|fr) -->/ {
        match($0, /<!-- LANG:([a-z]{2}) -->/, lang_arr)
        current_lang = lang_arr[1]
        
        # Close previous language section if language changed
        if (current_lang != prev_lang && !first_lang_marker) {
            print "    </section>"
        }
        
        # Open new language section
        if (current_lang != prev_lang || first_lang_marker) {
            hidden = (current_lang == "en") ? "" : " hidden"
            printf "    <section class=\"content-section\" data-lang=\"%s\"%s>\n", current_lang, hidden
            prev_lang = current_lang
            first_lang_marker = 0
        }
        
        # Keep the marker for Pass 2
        print
        next
    }
    /<h1[^>]*id="[^"]*"/ {
        print
        next
    }
    /<h1[^>]*>/ {
        match($0, /<h1([^>]*)>(.+)<\/h1>/, arr)
        if (arr[2]) {
            printf "<h1%s id=\"heading-%d\">%s</h1>\n", arr[1], id_counter, arr[2]
            id_counter++
        }
        next
    }
    /<h2[^>]*id="[^"]*"/ {
        print
        next
    }
    /<h2[^>]*>/ {
        match($0, /<h2([^>]*)>(.+)<\/h2>/, arr)
        if (arr[2]) {
            printf "<h2%s id=\"heading-%d\">%s</h2>\n", arr[1], id_counter, arr[2]
            id_counter++
        }
        next
    }
    {
        print
    }
    END {
        print "    </section>"
    }
    ' "$TMP_COMBINED"
} > "$TMP_COMBINED_IDS"

# Pass 2: Generate navbar from the ID'd headings, grouped by language
awk '
BEGIN {
    current_lang = ""
    prev_lang = ""
    current_h1_section = ""
    navbar = ""
}
/<!-- LANG:(en|fa|fr) -->/ {
    match($0, /<!-- LANG:([a-z]{2}) -->/, lang_arr)
    current_lang = lang_arr[1]
    
    # Close previous language section if language changed
    if (current_lang != prev_lang && prev_lang != "") {
        if (current_h1_section != "") {
            navbar = navbar "        </div>\n"
        }
        navbar = navbar "    </div>\n"
        current_h1_section = ""
    }
    
    # Open new language section
    if (current_lang != prev_lang) {
        hidden = (current_lang == "en") ? "" : " hidden"
        navbar = navbar "    <div data-lang=\"" current_lang "\"" hidden ">\n"
        prev_lang = current_lang
    }
    next
}
/<h1[^>]*id="[^"]*"/ {
    match($0, /id="([^"]*)"/, id_arr)
    match($0, />(.+)<\/h1>/, text_arr)
    if (text_arr[1]) {
        heading_id = id_arr[1]
        heading = text_arr[1]
        if (current_h1_section != "") {
            navbar = navbar "        </div>\n"
        }
        navbar = navbar "        <div class=\"toc-section\">\n"
        navbar = navbar "            <div class=\"toc-title\">" heading "</div>\n"
        current_h1_section = heading_id
    }
    next
}
/<h2[^>]*id="[^"]*"/ {
    match($0, /id="([^"]*)"/, id_arr)
    match($0, />(.+)<\/h2>/, text_arr)
    if (text_arr[1] && current_h1_section != "") {
        heading_id = id_arr[1]
        heading = text_arr[1]
        navbar = navbar "            <a href=\"#" heading_id "\" class=\"toc-link\" data-section=\"" heading_id "\">" heading "</a>\n"
    }
    next
}
{
    next
}
END {
    if (current_h1_section != "") {
        navbar = navbar "        </div>\n"
    }
    if (prev_lang != "") {
        navbar = navbar "    </div>\n"
    }
    print navbar
}
' "$TMP_COMBINED_IDS" > "$TMP_NAVBAR"

# Replace original combined file with ID-enhanced version
mv "$TMP_COMBINED_IDS" "$TMP_COMBINED"

# Use awk to replace placeholders while reading files
awk -v all_html_file="$TMP_COMBINED" \
    -v navbar_file="$TMP_NAVBAR" '
    BEGIN {
        while ((getline line < all_html_file) > 0) all_html = all_html line "\n"
        while ((getline line < navbar_file) > 0) navbar_html = navbar_html line "\n"
        close(all_html_file)
        close(navbar_file)
    }
    /%NAVBAR_HTML%/ { print navbar_html; next }
    /<!-- LANG:(en|fa|fr) -->/ { next }
    /%ALL_HTML%/ { print all_html; next }
    { print }
' template.html > index.html

# Clean up the final HTML output
# 1. Remove ONLY the first HTML comment block (<!-- ... -->), without touching <!DOCTYPE html>
#    This is the big documentation block at the top of the file
# 2. Remove all <!-- LANG:xx --> marker comments (they were only needed during processing)
sed -i '0,/^-->/ { /<!--/,/^-->/d }; /<!-- LANG:/d' index.html

# Cleanup
rm -f "$TMP_ALL_MD" "$TMP_ALL_HTML" "$TMP_NAVBAR" "$TMP_COMBINED" "${TMP_COMBINED}.with_ids"
