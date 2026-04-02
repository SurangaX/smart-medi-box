#!/usr/bin/env python3
from fpdf import FPDF
import re

# Read markdown file
with open(r"c:\Users\Suran\Documents\smart-medi-box\WIRING_MASTER_SHEET.md", "r", encoding="utf-8") as f:
    content = f.read()

def clean_unicode(text):
    """Remove/encode special Unicode characters."""
    replacements = {
        'Ω': 'Ohm', '→': '->', '←': '<-', '◄': '<', '►': '>', 
        '▼': 'v', '▲': '^', '°': 'deg', '±': '+/-', 'µ': 'u',
        '•': '*', '✓': '[OK]', '✔': '[OK]', '❌': '[NO]', 
        '✅': '[YES]', '⚠️': '[!]', '⬆': '[UP]',
    }
    for special, ascii_equiv in replacements.items():
        text = text.replace(special, ascii_equiv)
    
    cleaned = text.encode('ascii', errors='ignore').decode('ascii')
    return cleaned

class ModernReportPDF(FPDF):
    def __init__(self):
        super().__init__(orientation="P", unit="mm", format="A4")
        self.WIDTH = 210
        self.HEIGHT = 297
        self.page_num = 0
        
    def add_page(self, *args, **kwargs):
        super().add_page(*args, **kwargs)
        self.page_num += 1
        
    def header(self):
        if self.page_num <= 1:
            return
        
        # Premium gradient-like top bar
        self.set_fill_color(15, 50, 95)
        self.rect(0, 0, self.WIDTH, 10, style='F')
        
        # Accent stripe
        self.set_fill_color(230, 126, 34)
        self.rect(0, 10, self.WIDTH, 1, style='F')
        
        # Header content
        self.set_font("helvetica", "B", 9)
        self.set_text_color(255, 255, 255)
        self.set_xy(12, 2)
        self.cell(140, 6, "Smart Medi Box - Professional Wiring Guide v2.1")
        
        self.set_xy(self.WIDTH - 22, 2)
        self.set_font("helvetica", "", 8)
        self.cell(15, 6, f"Page {self.page_num}", align="R")
        
        self.set_text_color(0, 0, 0)
        self.set_y(13)
        
    def footer(self):
        if self.page_num <= 1:
            return
        
        self.set_y(-9)
        self.set_font("helvetica", "", 7)
        self.set_text_color(130, 130, 130)
        
        # Footer divider
        self.set_draw_color(200, 200, 200)
        self.line(10, self.get_y() - 1, self.WIDTH - 10, self.get_y() - 1)
        
        self.cell(0, 4, "Professional Edition | Optimized & Ready for Build", align="C")
    
    def add_modern_section(self, title):
        """Add modern section header with gradient effect."""
        if self.get_y() > 260:
            self.add_page()
        
        y = self.get_y()
        
        # Background box
        self.set_fill_color(15, 50, 95)
        self.rect(10, y, self.WIDTH - 20, 8, style='F')
        
        # Left accent bar
        self.set_fill_color(230, 126, 34)
        self.rect(10, y, 2.5, 8, style='F')
        
        # Section title
        self.set_font("helvetica", "B", 10)
        self.set_text_color(255, 255, 255)
        self.set_xy(16, y + 1.2)
        self.cell(0, 5, title)
        
        self.ln(8.5)
        return y

def add_cover_page(pdf):
    """Create stunning cover page."""
    pdf.add_page()
    
    # Main banner
    pdf.set_fill_color(15, 50, 95)
    pdf.rect(0, 0, pdf.WIDTH, 110, style='F')
    
    # Accent stripe
    pdf.set_fill_color(230, 126, 34)
    pdf.rect(0, 105, pdf.WIDTH, 5, style='F')
    
    # Main title
    pdf.set_font("helvetica", "B", 42)
    pdf.set_text_color(255, 255, 255)
    pdf.set_xy(10, 15)
    pdf.multi_cell(pdf.WIDTH - 20, 14, "SMART MEDI BOX", align="C")
    
    # Subtitle
    pdf.set_font("helvetica", "B", 16)
    pdf.set_text_color(230, 200, 150)
    pdf.set_xy(10, 50)
    pdf.cell(pdf.WIDTH - 20, 10, "Professional Wiring & Configuration", align="C")
    
    # Version
    pdf.set_font("helvetica", "", 11)
    pdf.set_text_color(180, 180, 180)
    pdf.set_xy(10, 68)
    pdf.cell(pdf.WIDTH - 20, 5, "Version 2.1 - Optimized & Corrected", align="C")
    
    pdf.set_xy(10, 75)
    pdf.cell(pdf.WIDTH - 20, 5, "Status: Production Ready", align="C")
    
    # Content highlights section
    pdf.set_y(145)
    
    pdf.set_font("helvetica", "B", 11)
    pdf.set_text_color(15, 50, 95)
    pdf.multi_cell(pdf.WIDTH - 30, 4, "Key Features:", align="L")
    pdf.ln(2)
    
    highlights = [
        "Complete Arduino Leonardo pin configuration",
        "Dual-rail power distribution (12V + 5V)",
        "SIM800L GSM module integration",
        "Multi-sensor environmental monitoring",
        "Motor and actuator control systems",
        "Comprehensive troubleshooting guide",
    ]
    
    pdf.set_font("helvetica", "", 9)
    pdf.set_text_color(60, 60, 60)
    
    for highlight in highlights:
        pdf.multi_cell(pdf.WIDTH - 30, 3.5, "  > " + highlight)
    
    # Footer text
    pdf.set_y(pdf.HEIGHT - 30)
    pdf.set_font("helvetica", "", 8)
    pdf.set_text_color(100, 100, 100)
    pdf.multi_cell(pdf.WIDTH - 20,
        3.5,
        "Complete hardware reference for the Smart Medi Box project. This professional guide includes all wiring specifications, pin assignments, power budgets, and critical safety procedures for system assembly and debugging.",
        align="C")

# Create PDF
pdf = ModernReportPDF()
add_cover_page(pdf)

# Add main content pages
pdf.add_page()
pdf.set_auto_page_break(auto=True, margin=14)
pdf.set_left_margin(10)
pdf.set_right_margin(10)

# Define color scheme
COLOR_PRIMARY = (15, 50, 95)      # Deep blue
COLOR_ACCENT = (230, 126, 34)      # Orange
COLOR_TEXT = (40, 40, 40)          # Dark gray
COLOR_LIGHT_BG = (245, 250, 255)   # Light blue
COLOR_ALT_BG = (255, 250, 245)     # Light orange

# Process markdown content
lines = content.split('\n')
current_table = None
table_headers = None
table_title = None
capture_table = False

for i, line in enumerate(lines):
    cleaned = clean_unicode(line)
    
    # Skip empty lines
    if not cleaned.strip():
        # Flush pending table
        if current_table and table_headers:
            # Add table title if needed
            if pdf.get_y() > 240:
                pdf.add_page()
            
            pdf.ln(1)
            
            # Modern table header
            pdf.set_font("helvetica", "B", 8)
            pdf.set_text_color(255, 255, 255)
            pdf.set_fill_color(*COLOR_PRIMARY)
            
            col_count = len(table_headers)
            col_width = (pdf.WIDTH - 20) / col_count
            
            for header in table_headers:
                short_h = str(header)[:25]
                pdf.cell(col_width, 6, short_h, border=1, align="C", fill=True)
            pdf.ln()
            
            # Table rows with alternating colors
            pdf.set_font("helvetica", "", 7.5)
            pdf.set_text_color(*COLOR_TEXT)
            
            for row_idx, row in enumerate(current_table):
                bg_color = COLOR_LIGHT_BG if row_idx % 2 == 0 else COLOR_ALT_BG
                pdf.set_fill_color(*bg_color)
                
                for col_idx, cell in enumerate(row):
                    short_c = str(cell)[:25]
                    pdf.cell(col_width, 5, short_c, border=1, align="L", fill=True)
                pdf.ln()
            
            pdf.ln(2)
            current_table = None
            table_headers = None
        continue
    
    # Main title (# )
    if line.startswith('# '):
        title = cleaned.replace('# ', '').strip()
        if "Smart Medi" not in title and "Version" not in title:
            if pdf.get_y() > 250:
                pdf.add_page()
            
            # Premium title styling
            pdf.set_draw_color(*COLOR_PRIMARY)
            pdf.line(10, pdf.get_y(), pdf.WIDTH - 10, pdf.get_y())
            pdf.ln(1.5)
            
            pdf.set_font("helvetica", "B", 14)
            pdf.set_text_color(*COLOR_PRIMARY)
            pdf.multi_cell(0, 6, title, new_x='LMARGIN', new_y='NEXT')
            pdf.ln(1)
    
    # Section header (## )
    elif line.startswith('## '):
        if current_table and table_headers:
            # Flush table before new section
            pass
        
        if pdf.get_y() > 250:
            pdf.add_page()
        
        section = cleaned.replace('## ', '').strip()
        pdf.add_modern_section(section)
        pdf.ln(1)
    
    # Subsection (### )
    elif line.startswith('### '):
        subtitle = cleaned.replace('### ', '').strip()
        pdf.set_font("helvetica", "B", 9.5)
        pdf.set_text_color(*COLOR_ACCENT)
        pdf.multi_cell(0, 4, subtitle, new_x='LMARGIN', new_y='NEXT')
        pdf.ln(0.5)
    
    # Table header row
    elif line.startswith('| ') and '|' in line:
        headers = [clean_unicode(h.strip()) for h in line.split('|')[1:-1]]
        
        # Check if next line is separator
        if i + 1 < len(lines) and lines[i + 1].startswith('|---'):
            table_headers = headers
            current_table = []
            
            # Find section title for this table
            for j in range(i - 1, max(0, i - 10), -1):
                if lines[j].startswith('### '):
                    table_title = clean_unicode(lines[j].replace('### ', ''))
                    break
    
    # Table separator (skip)
    elif line.startswith('|---'):
        continue
    
    # Table data row
    elif line.startswith('| ') and table_headers and '|' in line:
        row = [clean_unicode(c.strip()) for c in line.split('|')[1:-1]]
        current_table.append(row)
    
    # Bullet points
    elif cleaned.strip().startswith('- ['):
        is_checked = '[x]' in cleaned or '[OK]' in cleaned
        text = cleaned.strip()[5:].strip()
        
        pdf.set_font("helvetica", "", 8)
        pdf.set_text_color(*COLOR_TEXT)
        
        box = "[x]" if is_checked else "[ ]"
        pdf.multi_cell(0, 3.2, f"  {box} {text}", new_x='LMARGIN', new_y='NEXT')
    
    elif cleaned.strip().startswith(('- ', '* ')):
        text = cleaned.strip()[2:].strip()
        
        pdf.set_font("helvetica", "", 8)
        
        # Color code by content
        if '[!]' in text or 'CRITICAL' in text.upper() or '(!)' in text:
            pdf.set_text_color(200, 50, 50)
            pdf.set_font("helvetica", "B", 8)
        elif '[OK]' in text or 'OK' in text.upper():
            pdf.set_text_color(50, 140, 50)
        else:
            pdf.set_text_color(*COLOR_TEXT)
        
        pdf.multi_cell(0, 3.2, f"  > {text}", new_x='LMARGIN', new_y='NEXT')
    
    # Numbered items
    elif cleaned.strip() and cleaned.strip()[0].isdigit() and ('. ' in cleaned or ': ' in cleaned):
        pdf.set_font("helvetica", "", 8)
        pdf.set_text_color(*COLOR_TEXT)
        pdf.multi_cell(0, 3.2, f"  {cleaned}", new_x='LMARGIN', new_y='NEXT')
    
    # Regular text
    elif cleaned.strip():
        pdf.set_font("helvetica", "", 8)
        
        if '[!]' in cleaned or 'CRITICAL' in cleaned.upper():
            pdf.set_text_color(200, 50, 50)
            pdf.set_font("helvetica", "B", 8)
        else:
            pdf.set_text_color(*COLOR_TEXT)
        
        pdf.multi_cell(0, 3.5, cleaned, new_x='LMARGIN', new_y='NEXT')

# Flush any final table
if current_table and table_headers:
    if pdf.get_y() > 250:
        pdf.add_page()
    
    pdf.ln(1)
    
    pdf.set_font("helvetica", "B", 8)
    pdf.set_text_color(255, 255, 255)
    pdf.set_fill_color(*COLOR_PRIMARY)
    
    col_count = len(table_headers)
    col_width = (pdf.WIDTH - 20) / col_count
    
    for header in table_headers:
        short_h = str(header)[:25]
        pdf.cell(col_width, 6, short_h, border=1, align="C", fill=True)
    pdf.ln()
    
    pdf.set_font("helvetica", "", 7.5)
    pdf.set_text_color(*COLOR_TEXT)
    
    for row_idx, row in enumerate(current_table):
        bg_color = COLOR_LIGHT_BG if row_idx % 2 == 0 else COLOR_ALT_BG
        pdf.set_fill_color(*bg_color)
        
        for col_idx, cell in enumerate(row):
            short_c = str(cell)[:25]
            pdf.cell(col_width, 5, short_c, border=1, align="L", fill=True)
        pdf.ln()

# Save output
output = r"c:\Users\Suran\Documents\smart-medi-box\WIRING_MASTER_SHEET.pdf"
pdf.output(output)
print(f"✓ Modern Professional PDF Report Created!")
print(f"  Features:")
print(f"    • Modern gradient UI with premium styling")
print(f"    • Alternating row colors for better readability")
print(f"    • Professional color scheme (Deep Blue + Orange)")
print(f"    • Sectioned table layouts with headers")
print(f"    • Enhanced typography and spacing")
print(f"    • Professional cover page")
