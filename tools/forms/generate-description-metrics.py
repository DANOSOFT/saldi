#!/usr/bin/env python3
"""Regenerate numeric glyph advances using Saldi's PostScript font encoding.

20260930 CDX/PHR Bundle font measurements without requiring per-line subprocesses.
Run from any directory; Ghostscript must be installed. No font programs are copied.
"""
from pathlib import Path
import json
import subprocess

root = Path(__file__).resolve().parents[2]
initialization = (root / 'includes/faktinit.ps').read_text()
fonts = {}
for family in ['Helvetica', 'Times', 'Courier', 'Palatino', 'NewCenturySchlbk']:
    for style in ['', 'Bold', 'Italic', 'BoldItalic']:
        suffix = '-' + style if style else '-Roman' if family in ['Times', 'Palatino', 'NewCenturySchlbk'] else ''
        font = family + suffix
        code = initialization + '\n/' + font + '-ISOLatin9 findfont 1000 scalefont setfont\n'
        code += '0 1 255 { /code exch def /s 1 string def s 0 code put s stringwidth pop == } for\nquit\n'
        result = subprocess.run(
            ['gs', '-q', '-dNODISPLAY', '-dBATCH', '-'], input=code,
            text=True, capture_output=True, check=True,
        )
        widths = [round(float(value)) for value in result.stdout.splitlines()]
        if len(widths) != 256:
            raise RuntimeError('Expected 256 advance widths for ' + font)
        fonts[family + ('-' + style if style else '')] = widths
output = root / 'includes/formFuncIncludes/metrics/latin9.json'
output.write_text('{\n' + ',\n'.join(
    '  ' + json.dumps(font) + ':' + json.dumps(widths, separators=(',', ':'))
    for font, widths in fonts.items()
) + '\n}\n')
