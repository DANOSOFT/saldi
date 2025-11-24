#!/usr/bin/env python3
"""
Convert Markdown to HTML (can be printed to PDF from browser)
"""
import sys
import os
import re

def markdown_to_html(md_file, html_file):
    """Convert markdown file to styled HTML"""
    try:
        # Read markdown file
        with open(md_file, 'r', encoding='utf-8') as f:
            md_content = f.read()
        
        # Simple markdown to HTML conversion
        html = md_content
        
        # Headers
        html = re.sub(r'^# (.+)$', r'<h1>\1</h1>', html, flags=re.MULTILINE)
        html = re.sub(r'^## (.+)$', r'<h2>\1</h2>', html, flags=re.MULTILINE)
        html = re.sub(r'^### (.+)$', r'<h3>\1</h3>', html, flags=re.MULTILINE)
        
        # Bold
        html = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', html)
        
        # Lists
        lines = html.split('\n')
        in_list = False
        result = []
        for line in lines:
            if line.strip().startswith('- '):
                if not in_list:
                    result.append('<ul>')
                    in_list = True
                content = line.strip()[2:]
                result.append(f'  <li>{content}</li>')
            elif line.strip().startswith('1. '):
                if not in_list:
                    result.append('<ol>')
                    in_list = True
                content = line.strip()[3:]
                result.append(f'  <li>{content}</li>')
            else:
                if in_list:
                    result.append('</ul>' if '- ' in '\n'.join(result[-10:]) else '</ol>')
                    in_list = False
                if line.strip() == '---':
                    result.append('<hr>')
                elif line.strip():
                    result.append(line)
        
        if in_list:
            result.append('</ul>')
        
        html = '\n'.join(result)
        
        # Code blocks
        html = re.sub(r'`(.+?)`', r'<code>\1</code>', html)
        
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
        strong {
            color: #2c3e50;
            font-weight: 600;
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
        p {
            margin-bottom: 12px;
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
{html}
</body>
</html>"""
        
        # Write HTML file
        with open(html_file, 'w', encoding='utf-8') as f:
            f.write(full_html)
        
        print(f"✓ Successfully converted {md_file} to {html_file}")
        print(f"  Open {html_file} in your browser and use 'Print to PDF' to create PDF")
        return True
        
    except Exception as e:
        print(f"Error: {e}")
        import traceback
        traceback.print_exc()
        return False

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python3 md_to_html.py <markdown_file> [output_html]")
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

