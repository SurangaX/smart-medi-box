#!/usr/bin/env python3
"""Generate a modern professional PDF report for the Smart Medi Box wiring guide."""

import os
from fpdf import FPDF

# ---------------------------------------------------------------------------
# Paths (relative to this script's location)
# ---------------------------------------------------------------------------
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
MD_PATH = os.path.join(SCRIPT_DIR, "WIRING_MASTER_SHEET.md")
PDF_PATH = os.path.join(SCRIPT_DIR, "WIRING_MASTER_SHEET.pdf")

# ---------------------------------------------------------------------------
# Color palette
# ---------------------------------------------------------------------------
C_PRIMARY    = (15,  50,  95)   # Deep navy blue
C_ACCENT     = (230, 126,  34)  # Warm orange
C_TEXT       = (40,  40,  40)   # Dark charcoal
C_WHITE      = (255, 255, 255)
C_ROW_A      = (245, 250, 255)  # Light blue row
C_ROW_B      = (255, 250, 245)  # Light cream row
C_WARN       = (200,  50,  50)  # Warning red
C_OK         = (40,  140,  40)  # Success green
C_SUBHEAD    = (230, 126,  34)  # Sub-section orange
C_RULE       = (200, 200, 210)  # Rule / divider gray

# ---------------------------------------------------------------------------
# Unicode → ASCII normaliser
# ---------------------------------------------------------------------------
_UNICODE_MAP = {
    'Ω': 'Ohm', '→': '->', '←': '<-', '◄': '<', '►': '>',
    '▼': 'v',   '▲': '^',  '°': 'deg','±': '+/-','µ': 'u',
    '•': '*',   '✓': '[OK]','✔': '[OK]','❌': '[NO]',
    '✅': '[YES]','⚠️': '[!]','⬆': '[UP]',
}

def _clean(text: str) -> str:
    for k, v in _UNICODE_MAP.items():
        text = text.replace(k, v)
    return text.encode('ascii', errors='ignore').decode('ascii')


# ---------------------------------------------------------------------------
# PDF class
# ---------------------------------------------------------------------------
class ModernPDF(FPDF):
    """A4 portrait report with a branded header/footer on every content page."""

    W = 210
    H = 297
    MARGIN = 12
    CONTENT_W = 210 - 24  # 186 mm usable width

    def __init__(self):
        super().__init__(orientation="P", unit="mm", format="A4")
        self._page_num = 0

    # ------------------------------------------------------------------
    # Page lifecycle
    # ------------------------------------------------------------------
    def add_page(self, *args, **kwargs):
        super().add_page(*args, **kwargs)
        self._page_num += 1

    def header(self):
        if self._page_num <= 1:
            return
        # Navy bar
        self.set_fill_color(*C_PRIMARY)
        self.rect(0, 0, self.W, 11, style='F')
        # Orange accent line
        self.set_fill_color(*C_ACCENT)
        self.rect(0, 11, self.W, 1.2, style='F')
        # Title text
        self.set_font("helvetica", "B", 9)
        self.set_text_color(*C_WHITE)
        self.set_xy(self.MARGIN, 2.5)
        self.cell(150, 6, "Smart Medi Box  |  Professional Wiring Guide  v2.1")
        # Page number (right-aligned)
        self.set_font("helvetica", "", 8)
        self.set_xy(self.W - 32, 2.5)
        self.cell(20, 6, f"Page {self._page_num}", align="R")
        self.set_text_color(*C_TEXT)
        self.set_y(14)

    def footer(self):
        if self._page_num <= 1:
            return
        self.set_y(-10)
        self.set_draw_color(*C_RULE)
        self.line(self.MARGIN, self.get_y(), self.W - self.MARGIN, self.get_y())
        self.set_font("helvetica", "", 7)
        self.set_text_color(140, 140, 140)
        self.cell(0, 5, "Professional Edition  |  Optimised & Ready for Build  |  SurangaX", align="C")

    # ------------------------------------------------------------------
    # Section heading
    # ------------------------------------------------------------------
    def section(self, title: str):
        if self.get_y() > 262:
            self.add_page()
        y = self.get_y()
        # Navy background
        self.set_fill_color(*C_PRIMARY)
        self.rect(self.MARGIN, y, self.CONTENT_W, 9, style='F')
        # Orange left tab
        self.set_fill_color(*C_ACCENT)
        self.rect(self.MARGIN, y, 3, 9, style='F')
        # Title
        self.set_font("helvetica", "B", 10)
        self.set_text_color(*C_WHITE)
        self.set_xy(self.MARGIN + 5, y + 1.8)
        self.cell(0, 5, title)
        self.ln(10)
        self.set_text_color(*C_TEXT)

    # ------------------------------------------------------------------
    # Sub-section heading
    # ------------------------------------------------------------------
    def subsection(self, title: str):
        if self.get_y() > 268:
            self.add_page()
        y = self.get_y()
        # Soft background
        self.set_fill_color(235, 242, 255)
        self.rect(self.MARGIN, y, self.CONTENT_W, 7, style='F')
        # Left accent stripe
        self.set_fill_color(*C_ACCENT)
        self.rect(self.MARGIN, y, 2, 7, style='F')
        self.set_font("helvetica", "B", 9)
        self.set_text_color(*C_PRIMARY)
        self.set_xy(self.MARGIN + 4, y + 1)
        self.cell(0, 5, title)
        self.ln(8)
        self.set_text_color(*C_TEXT)

    # ------------------------------------------------------------------
    # Table renderer
    # ------------------------------------------------------------------
    def table(self, headers: list, rows: list):
        if not headers or not rows:
            return
        if self.get_y() > 245:
            self.add_page()

        n_cols = len(headers)
        # Compute per-column widths (proportional to longest content)
        col_max = [len(h) for h in headers]
        for row in rows:
            for c, cell in enumerate(row):
                if c < n_cols:
                    col_max[c] = max(col_max[c], len(str(cell)))
        total_chars = sum(col_max) or 1
        col_widths = [self.CONTENT_W * (w / total_chars) for w in col_max]

        # Header row
        self.set_font("helvetica", "B", 8)
        self.set_text_color(*C_WHITE)
        self.set_fill_color(*C_PRIMARY)
        self.set_draw_color(*C_PRIMARY)
        self.set_x(self.MARGIN)
        for i, h in enumerate(headers):
            self.cell(col_widths[i], 7, h, border=1, align="C", fill=True)
        self.ln()

        # Data rows with zebra colouring
        self.set_font("helvetica", "", 8)
        self.set_text_color(*C_TEXT)
        self.set_draw_color(180, 190, 210)
        for r_idx, row in enumerate(rows):
            if self.get_y() > 268:
                self.add_page()
                # Re-draw header on continuation page
                self.set_font("helvetica", "B", 8)
                self.set_text_color(*C_WHITE)
                self.set_fill_color(*C_PRIMARY)
                self.set_draw_color(*C_PRIMARY)
                self.set_x(self.MARGIN)
                for i, h in enumerate(headers):
                    self.cell(col_widths[i], 7, h, border=1, align="C", fill=True)
                self.ln()
                self.set_font("helvetica", "", 8)
                self.set_text_color(*C_TEXT)
                self.set_draw_color(180, 190, 210)

            fill_color = C_ROW_A if r_idx % 2 == 0 else C_ROW_B
            self.set_fill_color(*fill_color)
            self.set_x(self.MARGIN)
            for c_idx, cell in enumerate(row):
                if c_idx < n_cols:
                    self.cell(col_widths[c_idx], 6, str(cell), border=1, align="L", fill=True)
            self.ln()

        self.set_draw_color(*C_RULE)
        self.ln(2)

    # ------------------------------------------------------------------
    # Body text helpers
    # ------------------------------------------------------------------
    def body(self, text: str, bold=False, color=None):
        if self.get_y() > 272:
            self.add_page()
        self.set_font("helvetica", "B" if bold else "", 8.5)
        self.set_text_color(*(color or C_TEXT))
        self.set_x(self.MARGIN)
        self.multi_cell(self.CONTENT_W, 4, text, new_x='LMARGIN', new_y='NEXT')

    def bullet(self, text: str, warn=False, ok=False):
        color = C_WARN if warn else (C_OK if ok else C_TEXT)
        bold  = warn
        prefix = "  [!] " if warn else "   >  "
        self.body(prefix + text, bold=bold, color=color)

    def spacer(self, h=2):
        self.ln(h)


# ---------------------------------------------------------------------------
# Cover page
# ---------------------------------------------------------------------------
def _cover(pdf: ModernPDF):
    pdf.add_page()

    # ---- Full-width top banner ----
    pdf.set_fill_color(*C_PRIMARY)
    pdf.rect(0, 0, pdf.W, 120, style='F')

    # Decorative diagonal accent strip
    pdf.set_fill_color(*C_ACCENT)
    pdf.rect(0, 117, pdf.W, 6, style='F')

    # Main title
    pdf.set_font("helvetica", "B", 44)
    pdf.set_text_color(*C_WHITE)
    pdf.set_xy(10, 22)
    pdf.multi_cell(pdf.W - 20, 15, "SMART MEDI BOX", align="C")

    # Subtitle
    pdf.set_font("helvetica", "B", 15)
    pdf.set_text_color(230, 200, 150)
    pdf.set_xy(10, 60)
    pdf.cell(pdf.W - 20, 9, "Professional Wiring & Configuration Guide", align="C")

    # Version / status badges
    pdf.set_font("helvetica", "", 10)
    pdf.set_text_color(190, 210, 240)
    pdf.set_xy(10, 76)
    pdf.cell(pdf.W - 20, 5, "Version 2.1  |  Optimised & Corrected  |  Status: Production Ready", align="C")

    # ---- Info card ----
    card_y = 135
    pdf.set_fill_color(248, 250, 255)
    pdf.set_draw_color(210, 220, 235)
    pdf.rect(pdf.MARGIN, card_y, pdf.CONTENT_W, 98, style='FD')

    # Card header
    pdf.set_fill_color(*C_PRIMARY)
    pdf.rect(pdf.MARGIN, card_y, pdf.CONTENT_W, 10, style='F')
    pdf.set_font("helvetica", "B", 10)
    pdf.set_text_color(*C_WHITE)
    pdf.set_xy(pdf.MARGIN + 4, card_y + 2)
    pdf.cell(pdf.CONTENT_W - 8, 6, "Document Contents")

    # Feature list
    features = [
        ("System Overview",           "Dual-rail 12V / 5V power architecture"),
        ("Power Design",              "Buck converter, capacitor placement, heat budgets"),
        ("Microcontroller",           "Arduino Leonardo -- complete pin map"),
        ("GSM Module",                "SIM800L EVB -- 4-wire integration"),
        ("LCD Display",               "12864B V2.3 ST7920 -- serial SPI mode"),
        ("Sensors",                   "DHT22, DS18B20, RTC DS3231"),
        ("RFID Module",               "RC522 -- 3.3 V SPI wiring"),
        ("Motors & Actuators",        "Stepper, Servo MG995, Solenoid, Buzzer"),
        ("Power Budget",              "5 V / 12 V rail current budgets"),
        ("Key Design Rules",          "Critical checklist for safe assembly"),
        ("First Power-On Procedure",  "Step-by-step bring-up guide"),
        ("Troubleshooting",           "GSM, sensor, motor, reset failure guides"),
    ]

    pdf.set_font("helvetica", "", 8.5)
    pdf.set_text_color(*C_TEXT)
    row_h = 6.5
    col_label_w = 62
    for idx, (label, desc) in enumerate(features):
        ry = card_y + 14 + idx * row_h
        bg = (235, 243, 255) if idx % 2 == 0 else (248, 250, 255)
        pdf.set_fill_color(*bg)
        pdf.rect(pdf.MARGIN + 2, ry, pdf.CONTENT_W - 4, row_h, style='F')
        # Label
        pdf.set_font("helvetica", "B", 8.5)
        pdf.set_text_color(*C_PRIMARY)
        pdf.set_xy(pdf.MARGIN + 4, ry + 1.2)
        pdf.cell(col_label_w, 4, label)
        # Description
        pdf.set_font("helvetica", "", 8.5)
        pdf.set_text_color(*C_TEXT)
        pdf.set_xy(pdf.MARGIN + 4 + col_label_w, ry + 1.2)
        pdf.cell(pdf.CONTENT_W - col_label_w - 8, 4, desc)

    # Tagline at bottom of cover
    pdf.set_y(pdf.H - 22)
    pdf.set_font("helvetica", "I", 8)
    pdf.set_text_color(130, 130, 130)
    pdf.multi_cell(
        pdf.W - 20, 3.8,
        "Complete hardware reference for the Smart Medi Box project.  "
        "All wiring specifications, pin assignments, power budgets and safety procedures "
        "for system assembly and commissioning.",
        align="C",
    )


# ---------------------------------------------------------------------------
# Markdown → PDF renderer
# ---------------------------------------------------------------------------
def _render_md(pdf: ModernPDF, md_text: str):
    pdf.add_page()
    pdf.set_auto_page_break(auto=True, margin=14)
    pdf.set_left_margin(pdf.MARGIN)
    pdf.set_right_margin(pdf.MARGIN)

    lines = md_text.split('\n')
    tbl_headers: list | None = None
    tbl_rows:    list        = []

    def flush_table():
        nonlocal tbl_headers, tbl_rows
        if tbl_headers and tbl_rows:
            pdf.table(tbl_headers, tbl_rows)
        tbl_headers = None
        tbl_rows = []

    i = 0
    while i < len(lines):
        raw  = lines[i]
        line = _clean(raw)
        s    = line.strip()

        # ----- Heading level 1  (#) -----
        if raw.startswith('# '):
            flush_table()
            title = _clean(raw[2:]).strip()
            if title:
                pdf.spacer(2)
                pdf.set_draw_color(*C_RULE)
                pdf.line(pdf.MARGIN, pdf.get_y(), pdf.W - pdf.MARGIN, pdf.get_y())
                pdf.spacer(1.5)
                pdf.set_font("helvetica", "B", 13)
                pdf.set_text_color(*C_PRIMARY)
                pdf.set_x(pdf.MARGIN)
                pdf.multi_cell(pdf.CONTENT_W, 6, title, new_x='LMARGIN', new_y='NEXT')
                pdf.spacer(1)

        # ----- Heading level 2  (##) -----
        elif raw.startswith('## '):
            flush_table()
            pdf.spacer(3)
            pdf.section(_clean(raw[3:]).strip())

        # ----- Heading level 3  (###) -----
        elif raw.startswith('### '):
            flush_table()
            pdf.spacer(2)
            pdf.subsection(_clean(raw[4:]).strip())

        # ----- Horizontal rule (---) -----
        elif s == '---':
            flush_table()
            pdf.spacer(1)
            pdf.set_draw_color(*C_RULE)
            pdf.line(pdf.MARGIN, pdf.get_y(), pdf.W - pdf.MARGIN, pdf.get_y())
            pdf.spacer(2)

        # ----- Table header row -----
        elif raw.startswith('| ') and '|' in raw:
            hdrs = [_clean(h.strip()) for h in raw.split('|')[1:-1]]
            # Peek: is the next non-empty line a separator?
            if i + 1 < len(lines) and lines[i + 1].strip().startswith('|---'):
                flush_table()
                tbl_headers = hdrs
                tbl_rows    = []
                i += 2          # skip separator row
                continue
            elif tbl_headers is not None:
                # Data row
                row = [_clean(c.strip()) for c in raw.split('|')[1:-1]]
                tbl_rows.append(row)

        # ----- Table separator (skip standalone) -----
        elif s.startswith('|---'):
            pass

        # ----- Checkbox bullet  (- [ ] / - [x]) -----
        elif s.startswith('- ['):
            flush_table()
            checked = '[x]' in s or '[X]' in s
            text    = s[5:].strip() if len(s) > 5 else ''
            box     = '[x]' if checked else '[ ]'
            pdf.body(f"  {box}  {text}")

        # ----- Regular bullet  (- or *) -----
        elif s.startswith(('- ', '* ')):
            flush_table()
            text = s[2:].strip()
            warn = '[!]' in text or 'CRITICAL' in text.upper()
            ok   = '[OK]' in text or (text.upper().startswith('OK') and len(text) < 20)
            pdf.bullet(text, warn=warn, ok=ok)

        # ----- Bold / warning inline text (**text**) -----
        elif s.startswith('**') and s.endswith('**') and len(s) > 4:
            flush_table()
            inner = s[2:-2]
            warn  = '[!]' in inner or 'CRITICAL' in inner.upper() or 'IMPORTANT' in inner.upper()
            pdf.spacer(0.5)
            pdf.body(inner, bold=True, color=C_WARN if warn else C_PRIMARY)
            pdf.spacer(0.5)

        # ----- Numbered list item -----
        elif s and s[0].isdigit() and ('. ' in s or ') ' in s):
            flush_table()
            pdf.body('   ' + s)

        # ----- Empty line -----
        elif not s:
            flush_table()
            pdf.spacer(1.5)

        # ----- Plain paragraph text -----
        else:
            flush_table()
            warn = '[!]' in s or 'CRITICAL' in s.upper()
            pdf.body(s, bold=warn, color=C_WARN if warn else C_TEXT)

        i += 1

    flush_table()


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
if __name__ == "__main__":
    with open(MD_PATH, "r", encoding="utf-8") as fh:
        md_content = fh.read()

    pdf = ModernPDF()
    _cover(pdf)
    _render_md(pdf, md_content)
    pdf.output(PDF_PATH)

    print("Modern Professional PDF created successfully!")
    print(f"  Output : {PDF_PATH}")
    print("  Features:")
    print("    - Branded header & footer on every content page")
    print("    - Professional cover page with feature index")
    print("    - Section headings with navy + orange accent design")
    print("    - Sub-section headings with soft blue background")
    print("    - Full-width tables with zebra-striped rows")
    print("    - Proportional column widths (no text truncation)")
    print("    - Warning / OK colour coding for safety notes")
