#!/usr/bin/env python3
"""
Convert Markdown to HTML with better markdown parsing
"""
import sys
import os
import re

def parse_markdown(md_content):
    """Parse markdown content to HTML"""
    html = md_content
    
    # Horizontal rules
    html = re.sub(r'^---\s*$', '<hr>', html, flags=re.MULTILINE)
    
    # Headers (must be done in order from h3 to h1)
    html = re.sub(r'^### (.+)$', r'<h3>\1</h3>', html, flags=re.MULTILINE)
    html = re.sub(r'^## (.+)$', r'<h2>\1</h2>', html, flags=re.MULTILINE)
    html = re.sub(r'^# (.+)$', r'<h1>\1</h1>', html, flags=re.MULTILINE)
    
    # Bold
    html = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', html)
    
    # Italic
    html = re.sub(r'\*(.+?)\*', r'<em>\1</em>', html)
    
    # Inline code
    html = re.sub(r'`([^`]+)`', r'<code>\1</code>', html)
    
    # Process line by line for lists and paragraphs
    lines = html.split('\n')
    result = []
    in_ul = False
    in_ol = False
    in_p = False
    
    for i, line in enumerate(lines):
        stripped = line.strip()
        
        # Check for unordered list
        if stripped.startswith('- '):
            if in_p:
                result.append('</p>')
                in_p = False
            if not in_ul:
                if in_ol:
                    result.append('</ol>')
                    in_ol = False
                result.append('<ul>')
                in_ul = True
            content = stripped[2:].strip()
            # Process inline formatting in list items
            content = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', content)
            content = re.sub(r'`([^`]+)`', r'<code>\1</code>', content)
            result.append(f'  <li>{content}</li>')
        
        # Check for ordered list
        elif re.match(r'^\d+\.\s+', stripped):
            if in_p:
                result.append('</p>')
                in_p = False
            if not in_ol:
                if in_ul:
                    result.append('</ul>')
                    in_ul = False
                result.append('<ol>')
                in_ol = True
            content = re.sub(r'^\d+\.\s+', '', stripped)
            # Process inline formatting
            content = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', content)
            content = re.sub(r'`([^`]+)`', r'<code>\1</code>', content)
            result.append(f'  <li>{content}</li>')
        
        # Empty line
        elif not stripped:
            if in_p:
                result.append('</p>')
                in_p = False
            if in_ul:
                result.append('</ul>')
                in_ul = False
            if in_ol:
                result.append('</ol>')
                in_ol = False
            result.append('')
        
        # Regular paragraph or header
        elif stripped.startswith('<h') or stripped.startswith('<hr>'):
            if in_p:
                result.append('</p>')
                in_p = False
            if in_ul:
                result.append('</ul>')
                in_ul = False
            if in_ol:
                result.append('</ol>')
                in_ol = False
            result.append(line)
        
        # Regular text line
        else:
            if not in_p and stripped:
                result.append('<p>')
                in_p = True
            if in_p:
                result.append(line)
    
    # Close any open tags
    if in_p:
        result.append('</p>')
    if in_ul:
        result.append('</ul>')
    if in_ol:
        result.append('</ol>')
    
    return '\n'.join(result)

def markdown_to_html(md_file, html_file):
    """Convert markdown file to styled HTML"""
    try:
        # Read markdown file
        with open(md_file, 'r', encoding='utf-8') as f:
            md_content = f.read()
        
        # Parse markdown to HTML
        html_content = parse_markdown(md_content)
        
        # CSS styling
        css_style = """
        <style>
        @media print {
            @page {
                size: A4;
                margin: 2cm;
            }
            body {
                font-size: 11pt;
            }
            h1 { page-break-after: avoid; }
            h2 { page-break-after: avoid; }
            h3 { page-break-after: avoid; }
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }
        h1 {
            font-size: 28pt;
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 30px;
        }
        h2 {
            font-size: 20pt;
            color: #34495e;
            margin-top: 30px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
            padding-left: 10px;
        }
        h3 {
            font-size: 16pt;
            color: #555;
            margin-top: 25px;
            margin-bottom: 10px;
        }
        ul, ol {
            margin-left: 25px;
            margin-bottom: 15px;
        }
        li {
            margin-bottom: 8px;
        }
        p {
            margin-bottom: 12px;
        }
        strong {
            color: #2c3e50;
            font-weight: 600;
        }
        em {
            font-style: italic;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        hr {
            border: none;
            border-top: 2px solid #ecf0f1;
            margin: 30px 0;
        }
        </style>
        """
        
        # Wrap in HTML structure
        full_html = f"""<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Integration Oversigt - Custom Audio</title>
    {css_style}
</head>
<body>
{html_content}
</body>
</html>"""
        
        # Write HTML file
        with open(html_file, 'w', encoding='utf-8') as f:
            f.write(full_html)
        
        print(f"✓ Successfully converted {md_file} to {html_file}")
        print(f"  Open {html_file} in your browser and use 'Print to PDF' (Ctrl+P) to create PDF")
        return True
        
    except Exception as e:
        print(f"Error: {e}")
        import traceback
        traceback.print_exc()
        return False

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python3 md_to_html_improved.py <markdown_file> [output_html]")
        sys.exit(1)
    
    md_file = sys.argv[1]
    if not os.path.exists(md_file):
        print(f"Error: File {md_file} not found")
        sys.exit(1)
    
    if len(sys.argv) >= 3:
        html_file = sys.argv[2]
    else:
        html_file = os.path.splitext(md_file)[0] + ".html"
    
    markdown_to_html(md_file, html_file)

