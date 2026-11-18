"""نرم‌افزار مدیریت شارژ ساختمان (واحدها، شارژ، دریافتی‌ها، هزینه‌ها، گزارش‌ها)."""
import os
import html
import sqlite3
import webbrowser
from datetime import datetime
from pathlib import Path
from tkinter import ttk, messagebox, filedialog

import customtkinter as ctk
from persiantools.jdatetime import JalaliDate

BASE_DIR = Path(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = BASE_DIR / "building.db"
REPORT_DIR = BASE_DIR / "reports"
FONT = "Vazirmatn"  # اگر نصب نباشد Tk فونت پیش‌فرض را می‌گذارد

EXPENSE_CATEGORIES = ["آب", "برق", "گاز", "نظافت", "آسانسور", "تعمیرات",
                      "نگهبانی", "فضای سبز", "بیمه", "سایر"]
PAY_METHODS = ["کارت به کارت", "نقدی", "واریز به حساب", "چک", "سایر"]
MONTHLY_DESC = "شارژ ماهانه"

ctk.set_appearance_mode("light")
ctk.set_default_color_theme("blue")

# ---------------------------------------------------------------- ابزارها
_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")


def to_en(s):
    return str(s).translate(_DIGITS).strip()


def parse_amount(s):
    s = to_en(s).replace(",", "").replace("،", "")
    if not s.isdigit():
        raise ValueError("مبلغ باید عدد مثبت باشد.")
    return int(s)


def parse_date(s):
    s = to_en(s).replace("-", "/")
    try:
        y, m, d = (int(p) for p in s.split("/"))
        return JalaliDate(y, m, d).strftime("%Y/%m/%d")
    except Exception:
        raise ValueError(f"تاریخ نامعتبر است: {s}\nنمونه درست: 1404/07/15")


def parse_period(s):
    s = to_en(s).replace("-", "/")
    try:
        y, m = (int(p) for p in s.split("/"))
        if not 1 <= m <= 12 or y < 1300:
            raise ValueError
        return f"{y:04d}/{m:02d}"
    except Exception:
        raise ValueError(f"دوره نامعتبر است: {s}\nنمونه درست: 1404/07")


def today():
    return JalaliDate.today().strftime("%Y/%m/%d")


def fmt(n):
    return f"{int(n or 0):,}"


def balance_text(b):
    if b > 0:
        return f"{fmt(b)} بدهکار"
    if b < 0:
        return f"{fmt(-b)} بستانکار"
    return "تسویه"


# ---------------------------------------------------------------- پایگاه داده
SCHEMA = """
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);
CREATE TABLE IF NOT EXISTS units (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    number TEXT NOT NULL UNIQUE,
    owner TEXT, phone TEXT, area REAL, residents INTEGER,
    monthly_charge INTEGER NOT NULL DEFAULT 0, note TEXT);
CREATE TABLE IF NOT EXISTS charges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    unit_id INTEGER NOT NULL REFERENCES units(id) ON DELETE CASCADE,
    period TEXT NOT NULL, amount INTEGER NOT NULL, description TEXT);
CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    unit_id INTEGER NOT NULL REFERENCES units(id) ON DELETE CASCADE,
    date TEXT NOT NULL, amount INTEGER NOT NULL, method TEXT, description TEXT);
CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT NOT NULL, category TEXT, amount INTEGER NOT NULL, description TEXT);
"""


class DB:
    def __init__(self, path):
        self.conn = sqlite3.connect(path)
        self.conn.row_factory = sqlite3.Row
        self.conn.execute("PRAGMA foreign_keys = ON")
        self.conn.executescript(SCHEMA)

    def q(self, sql, params=()):
        return self.conn.execute(sql, params).fetchall()

    def one(self, sql, params=()):
        return self.conn.execute(sql, params).fetchone()

    def x(self, sql, params=()):
        with self.conn:
            return self.conn.execute(sql, params)

    def get_setting(self, key, default=""):
        r = self.one("SELECT value FROM settings WHERE key=?", (key,))
        return r["value"] if r else default

    def set_setting(self, key, value):
        self.x("INSERT OR REPLACE INTO settings(key, value) VALUES(?, ?)", (key, value))

    def units_with_balance(self):
        return self.q("""
            SELECT u.*,
              COALESCE((SELECT SUM(amount) FROM charges c WHERE c.unit_id=u.id), 0) AS charged,
              COALESCE((SELECT SUM(amount) FROM payments p WHERE p.unit_id=u.id), 0) AS paid
            FROM units u ORDER BY CAST(u.number AS INTEGER), u.number""")

    def issue_monthly(self, period):
        """شارژ ماهانه را برای همه واحدها صادر می‌کند؛ واحدهایی که قبلاً گرفته‌اند رد می‌شوند."""
        added = skipped = 0
        with self.conn:
            for u in self.q("SELECT id, monthly_charge FROM units"):
                exists = self.one(
                    "SELECT 1 FROM charges WHERE unit_id=? AND period=? AND description=?",
                    (u["id"], period, MONTHLY_DESC))
                if exists or u["monthly_charge"] <= 0:
                    skipped += 1
                    continue
                self.conn.execute(
                    "INSERT INTO charges(unit_id, period, amount, description) VALUES(?,?,?,?)",
                    (u["id"], period, u["monthly_charge"], MONTHLY_DESC))
                added += 1
        return added, skipped


# ---------------------------------------------------------------- گزارش HTML
REPORT_CSS = """
body{font-family:'Vazirmatn',Tahoma,Arial,sans-serif;background:#f4f6f9;color:#222;margin:0;padding:24px}
.page{background:#fff;max-width:960px;margin:auto;padding:28px 32px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
h1{margin:0 0 4px;font-size:22px;color:#1f3a5f}
h2{font-size:17px;margin:26px 0 8px;color:#1f3a5f;border-right:4px solid #3b82f6;padding-right:8px}
.meta{color:#666;font-size:13px;margin-bottom:12px;border-bottom:2px solid #3b82f6;padding-bottom:10px}
table{width:100%;border-collapse:collapse;font-size:13px;margin-top:6px}
th,td{border:1px solid #d5dbe3;padding:6px 8px;text-align:center}
th{background:#eaf1fb}
tr:nth-child(even) td{background:#fafbfd}
tfoot td{font-weight:bold;background:#f1f5f9}
.cards{display:flex;gap:12px;flex-wrap:wrap}
.card{flex:1;min-width:160px;border:1px solid #d5dbe3;border-radius:8px;padding:12px;text-align:center}
.card b{display:block;font-size:18px;margin-top:4px}
.neg{color:#b91c1c}.pos{color:#15803d}
.btn{background:#3b82f6;color:#fff;border:0;padding:8px 18px;border-radius:6px;font-family:inherit;font-size:14px;cursor:pointer}
.sign{display:flex;justify-content:space-between;margin-top:50px;font-size:13px}
@media print{body{background:#fff;padding:0}.page{box-shadow:none;max-width:none;padding:0}.no-print{display:none}}
"""


def esc(v):
    return html.escape(str(v if v is not None else ""))


def table_html(headers, rows, foot=None):
    h = "".join(f"<th>{esc(x)}</th>" for x in headers)
    body = "".join("<tr>" + "".join(f"<td>{esc(c)}</td>" for c in r) + "</tr>" for r in rows)
    if not rows:
        body = f'<tr><td colspan="{len(headers)}">موردی یافت نشد</td></tr>'
    f = ""
    if foot:
        f = "<tfoot><tr>" + "".join(f"<td>{esc(c)}</td>" for c in foot) + "</tr></tfoot>"
    return f"<table><thead><tr>{h}</tr></thead><tbody>{body}</tbody>{f}</table>"


def cards_html(items):
    return '<div class="cards">' + "".join(
        f'<div class="card">{esc(t)}<b class="{cls}">{esc(v)}</b></div>' for t, v, cls in items) + "</div>"


def open_report(building, title, body, subtitle=""):
    REPORT_DIR.mkdir(exist_ok=True)
    doc = f"""<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{esc(title)}</title>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
<style>{REPORT_CSS}</style></head><body><div class="page">
<div class="no-print" style="text-align:left;margin-bottom:10px">
<button class="btn" onclick="window.print()">🖨 چاپ</button></div>
<h1>{esc(building)} — {esc(title)}</h1>
<div class="meta">{esc(subtitle)} &nbsp;|&nbsp; تاریخ تهیه: {today()}</div>
{body}
</div></body></html>"""
    path = REPORT_DIR / f"report_{datetime.now():%Y%m%d_%H%M%S_%f}.html"
    path.write_text(doc, encoding="utf-8")
    webbrowser.open(path.as_uri())
    return path


# ---------------------------------------------------------------- جدول راست‌به‌چپ
class Table(ttk.Frame):
    """Treeview که ستون اول را سمت راست نشان می‌دهد."""

    def __init__(self, parent, columns, height=12):
        super().__init__(parent)
        ids = [f"c{i}" for i in range(len(columns))]
        self.tree = ttk.Treeview(self, columns=ids[::-1], show="headings",
                                 selectmode="browse", height=height)
        for cid, (title, width) in zip(ids, columns):
            self.tree.heading(cid, text=title)
            self.tree.column(cid, width=width, anchor="center")
        sb = ttk.Scrollbar(self, orient="vertical", command=self.tree.yview)
        self.tree.configure(yscrollcommand=sb.set)
        sb.pack(side="left", fill="y")
        self.tree.pack(side="right", fill="both", expand=True)

    def set_rows(self, rows):
        self.tree.delete(*self.tree.get_children())
        for iid, values in rows:
            self.tree.insert("", "end", iid=str(iid), values=list(values)[::-1])

    def selected_id(self):
        sel = self.tree.selection()
        return int(sel[0]) if sel else None


# ---------------------------------------------------------------- برنامه
class App(ctk.CTk):
    def __init__(self):
        super().__init__()
        self.db = DB(DB_PATH)
        self.title("مدیریت شارژ ساختمان")
        self.geometry("1180x740")
        self.minsize(1000, 640)

        self.f = ctk.CTkFont(family=FONT, size=13)
        self.fb = ctk.CTkFont(family=FONT, size=14, weight="bold")
        style = ttk.Style(self)
        style.theme_use("clam")
        style.configure("Treeview", font=(FONT, 11), rowheight=28)
        style.configure("Treeview.Heading", font=(FONT, 11, "bold"))

        self.unit_map = {}      # متن کمبوباکس -> id واحد
        self.unit_edit_id = None

        self.tabs = ctk.CTkTabview(self, anchor="ne")
        self.tabs.pack(fill="both", expand=True, padx=10, pady=10)
        self.tabs._segmented_button.configure(font=self.fb)
        names = ["گزارش‌ها", "هزینه‌ها", "دریافتی‌ها", "شارژ و بدهی", "واحدها"]
        for n in names:  # به ترتیب معکوس تا «واحدها» سمت راست باشد
            self.tabs.add(n)
        self.build_units(self.tabs.tab("واحدها"))
        self.build_charges(self.tabs.tab("شارژ و بدهی"))
        self.build_payments(self.tabs.tab("دریافتی‌ها"))
        self.build_expenses(self.tabs.tab("هزینه‌ها"))
        self.build_reports(self.tabs.tab("گزارش‌ها"))
        self.tabs.set("واحدها")
        self.refresh_all()

    # ---------- ویجت‌های کمکی
    def entry(self, parent, width=200, value=""):
        e = ctk.CTkEntry(parent, font=self.f, justify="right", width=width)
        if value:
            e.insert(0, value)
        return e

    def combo(self, parent, values, width=200, editable=False):
        c = ctk.CTkComboBox(parent, values=values, font=self.f, dropdown_font=self.f,
                            justify="right", width=width,
                            state="normal" if editable else "readonly")
        if values:
            c.set(values[0])
        return c

    def button(self, parent, text, cmd, color=None):
        kw = {"fg_color": color, "hover_color": color} if color else {}
        return ctk.CTkButton(parent, text=text, command=cmd, font=self.f, width=130, **kw)

    def form(self, parent, fields, cols=3):
        """fields: [(برچسب, ویجت)] — چیدمان راست‌به‌چپ در شبکه."""
        for i, (label, widget) in enumerate(fields):
            r, c = divmod(i, cols)
            base = (cols - 1 - c) * 2
            ctk.CTkLabel(parent, text=label, font=self.f, anchor="e").grid(
                row=r, column=base + 1, padx=(4, 12), pady=6, sticky="e")
            widget.grid(row=r, column=base, padx=4, pady=6, sticky="e")

    def buttons_row(self, parent, items):
        row = ctk.CTkFrame(parent, fg_color="transparent")
        for text, cmd, *color in items:
            self.button(row, text, cmd, *(color or [None])).pack(side="right", padx=5)
        return row

    def selected_unit_id(self, combo):
        uid = self.unit_map.get(combo.get())
        if uid is None:
            raise ValueError("ابتدا یک واحد انتخاب کنید (اگر واحدی نیست، از تب واحدها اضافه کنید).")
        return uid

    def run(self, func):
        """اجرای عمل با نمایش خطای خوانا."""
        try:
            func()
        except ValueError as e:
            messagebox.showerror("خطا", str(e))
        except sqlite3.IntegrityError as e:
            msg = "شماره واحد تکراری است." if "units.number" in str(e) else str(e)
            messagebox.showerror("خطا", msg)

    def refresh_all(self):
        rows = self.db.units_with_balance()
        self.unit_map = {f"واحد {u['number']} - {u['owner'] or ''}".strip(" -"): u["id"] for u in rows}
        names = list(self.unit_map) or [""]
        for c in (self.ch_unit, self.pay_unit, self.rep_unit):
            cur = c.get()
            c.configure(values=names)
            c.set(cur if cur in self.unit_map else names[0])
        self.refresh_units(rows)
        self.refresh_charges()
        self.refresh_payments()
        self.refresh_expenses()
        self.refresh_summary()

    # ================================================================ واحدها
    def build_units(self, tab):
        box = ctk.CTkFrame(tab)
        box.pack(fill="x", padx=6, pady=6)
        self.u_number = self.entry(box)
        self.u_owner = self.entry(box)
        self.u_phone = self.entry(box)
        self.u_area = self.entry(box)
        self.u_res = self.entry(box)
        self.u_charge = self.entry(box)
        self.u_note = self.entry(box)
        self.form(box, [("شماره واحد", self.u_number), ("نام مالک / ساکن", self.u_owner),
                        ("تلفن", self.u_phone), ("متراژ (متر)", self.u_area),
                        ("تعداد نفرات", self.u_res), ("شارژ ماهانه (تومان)", self.u_charge),
                        ("توضیحات", self.u_note)])
        self.buttons_row(tab, [
            ("افزودن واحد", lambda: self.run(self.add_unit)),
            ("ذخیره ویرایش", lambda: self.run(self.update_unit)),
            ("حذف واحد", lambda: self.run(self.delete_unit), "#dc2626"),
            ("فرم جدید", self.clear_unit_form, "gray"),
        ]).pack(fill="x", padx=6, pady=4)
        self.unit_table = Table(tab, [("شماره", 70), ("نام مالک / ساکن", 170), ("تلفن", 120),
                                      ("متراژ", 70), ("نفرات", 60), ("شارژ ماهانه", 110),
                                      ("جمع بدهی صادره", 120), ("جمع پرداختی", 120),
                                      ("وضعیت حساب", 150)], height=15)
        self.unit_table.pack(fill="both", expand=True, padx=6, pady=6)
        self.unit_table.tree.bind("<<TreeviewSelect>>", self.on_unit_select)

    def unit_values(self):
        number = to_en(self.u_number.get())
        if not number:
            raise ValueError("شماره واحد را وارد کنید.")
        area = to_en(self.u_area.get()) or "0"
        res = to_en(self.u_res.get()) or "0"
        try:
            area_v, res_v = float(area), int(res)
        except ValueError:
            raise ValueError("متراژ و تعداد نفرات باید عدد باشند.")
        charge = parse_amount(self.u_charge.get() or "0")
        return (number, self.u_owner.get().strip(), to_en(self.u_phone.get()),
                area_v, res_v, charge, self.u_note.get().strip())

    def add_unit(self):
        self.db.x("INSERT INTO units(number, owner, phone, area, residents, monthly_charge, note)"
                  " VALUES(?,?,?,?,?,?,?)", self.unit_values())
        self.clear_unit_form()
        self.refresh_all()

    def update_unit(self):
        if not self.unit_edit_id:
            raise ValueError("ابتدا یک واحد را از جدول انتخاب کنید.")
        self.db.x("UPDATE units SET number=?, owner=?, phone=?, area=?, residents=?,"
                  " monthly_charge=?, note=? WHERE id=?", self.unit_values() + (self.unit_edit_id,))
        self.refresh_all()
        messagebox.showinfo("ذخیره شد", "اطلاعات واحد به‌روز شد.")

    def delete_unit(self):
        uid = self.unit_table.selected_id()
        if not uid:
            raise ValueError("ابتدا یک واحد را از جدول انتخاب کنید.")
        if messagebox.askyesno("حذف واحد",
                               "با حذف واحد، همه شارژها و دریافتی‌های آن هم پاک می‌شود.\n"
                               "این کار برگشت‌پذیر نیست. ادامه می‌دهید؟", icon="warning"):
            self.db.x("DELETE FROM units WHERE id=?", (uid,))
            self.clear_unit_form()
            self.refresh_all()

    def on_unit_select(self, _e=None):
        uid = self.unit_table.selected_id()
        if not uid:
            return
        u = self.db.one("SELECT * FROM units WHERE id=?", (uid,))
        self.unit_edit_id = uid
        for w, v in [(self.u_number, u["number"]), (self.u_owner, u["owner"]),
                     (self.u_phone, u["phone"]),
                     (self.u_area, f"{u['area']:g}" if u["area"] else ""),
                     (self.u_res, u["residents"] or ""), (self.u_charge, u["monthly_charge"]),
                     (self.u_note, u["note"])]:
            w.delete(0, "end")
            w.insert(0, str(v or ""))

    def clear_unit_form(self):
        self.unit_edit_id = None
        for w in (self.u_number, self.u_owner, self.u_phone, self.u_area,
                  self.u_res, self.u_charge, self.u_note):
            w.delete(0, "end")
        self.unit_table.tree.selection_remove(self.unit_table.tree.selection())

    def refresh_units(self, rows):
        self.unit_table.set_rows([
            (u["id"], (u["number"], u["owner"] or "", u["phone"] or "",
                       f"{u['area']:g}" if u["area"] else "", u["residents"] or "",
                       fmt(u["monthly_charge"]), fmt(u["charged"]), fmt(u["paid"]),
                       balance_text(u["charged"] - u["paid"])))
            for u in rows])

    # ================================================================ شارژ و بدهی
    def build_charges(self, tab):
        top = ctk.CTkFrame(tab)
        top.pack(fill="x", padx=6, pady=6)
        ctk.CTkLabel(top, text="صدور شارژ ماهانه برای همه واحدها", font=self.fb).pack(
            side="right", padx=10)
        self.ch_period = self.entry(top, 110, today()[:7])
        ctk.CTkLabel(top, text="دوره (سال/ماه):", font=self.f).pack(side="right", padx=4)
        self.ch_period.pack(side="right", padx=4)
        self.button(top, "صدور شارژ", lambda: self.run(self.issue_monthly)).pack(
            side="right", padx=10, pady=8)

        box = ctk.CTkFrame(tab)
        box.pack(fill="x", padx=6, pady=6)
        ctk.CTkLabel(box, text="ثبت بدهی موردی برای یک واحد (مثلاً هزینه تعمیر، جریمه، مانده قبلی)",
                     font=self.fb, anchor="e").grid(row=0, column=0, columnspan=6, sticky="e", padx=10)
        inner = ctk.CTkFrame(box, fg_color="transparent")
        inner.grid(row=1, column=0, columnspan=6, sticky="e")
        self.ch_unit = self.combo(inner, [], 220)
        self.ch_one_period = self.entry(inner, 110, today()[:7])
        self.ch_amount = self.entry(inner, 140)
        self.ch_desc = self.entry(inner, 260)
        self.form(inner, [("واحد", self.ch_unit), ("دوره", self.ch_one_period),
                          ("مبلغ (تومان)", self.ch_amount), ("شرح", self.ch_desc)], cols=4)

        self.buttons_row(tab, [
            ("ثبت بدهی", lambda: self.run(self.add_charge)),
            ("حذف ردیف انتخابی", lambda: self.run(self.delete_charge), "#dc2626"),
        ]).pack(fill="x", padx=6, pady=4)
        self.charge_table = Table(tab, [("دوره", 90), ("واحد", 220), ("مبلغ", 130), ("شرح", 300)])
        self.charge_table.pack(fill="both", expand=True, padx=6, pady=6)

    def issue_monthly(self):
        period = parse_period(self.ch_period.get())
        if not self.unit_map:
            raise ValueError("هنوز واحدی ثبت نشده است.")
        if not messagebox.askyesno("صدور شارژ", f"شارژ ماهانه دوره {period} برای همه واحدها صادر شود؟"):
            return
        added, skipped = self.db.issue_monthly(period)
        self.refresh_all()
        messagebox.showinfo("صدور شارژ",
                            f"برای {added} واحد شارژ صادر شد.\n"
                            f"{skipped} واحد رد شد (قبلاً صادر شده یا شارژ ماهانه صفر است).")

    def add_charge(self):
        uid = self.selected_unit_id(self.ch_unit)
        period = parse_period(self.ch_one_period.get())
        amount = parse_amount(self.ch_amount.get())
        desc = self.ch_desc.get().strip() or "بدهی موردی"
        self.db.x("INSERT INTO charges(unit_id, period, amount, description) VALUES(?,?,?,?)",
                  (uid, period, amount, desc))
        self.ch_amount.delete(0, "end")
        self.ch_desc.delete(0, "end")
        self.refresh_all()

    def delete_charge(self):
        cid = self.charge_table.selected_id()
        if not cid:
            raise ValueError("ابتدا یک ردیف را انتخاب کنید.")
        if messagebox.askyesno("حذف", "این ردیف بدهی حذف شود؟"):
            self.db.x("DELETE FROM charges WHERE id=?", (cid,))
            self.refresh_all()

    def refresh_charges(self):
        rows = self.db.q("""SELECT c.*, u.number, u.owner FROM charges c JOIN units u ON u.id=c.unit_id
                            ORDER BY c.period DESC, CAST(u.number AS INTEGER), c.id DESC""")
        self.charge_table.set_rows([
            (r["id"], (r["period"], f"واحد {r['number']} - {r['owner'] or ''}",
                       fmt(r["amount"]), r["description"] or "")) for r in rows])

    # ================================================================ دریافتی‌ها
    def build_payments(self, tab):
        box = ctk.CTkFrame(tab)
        box.pack(fill="x", padx=6, pady=6)
        self.pay_unit = self.combo(box, [], 220)
        self.pay_date = self.entry(box, 200, today())
        self.pay_amount = self.entry(box)
        self.pay_method = self.combo(box, PAY_METHODS, editable=True)
        self.pay_desc = self.entry(box, 200)
        self.form(box, [("واحد", self.pay_unit), ("تاریخ", self.pay_date),
                        ("مبلغ (تومان)", self.pay_amount), ("روش پرداخت", self.pay_method),
                        ("شرح", self.pay_desc)])
        self.buttons_row(tab, [
            ("ثبت دریافتی", lambda: self.run(self.add_payment)),
            ("چاپ رسید", lambda: self.run(self.print_receipt)),
            ("حذف ردیف انتخابی", lambda: self.run(self.delete_payment), "#dc2626"),
        ]).pack(fill="x", padx=6, pady=4)
        self.pay_table = Table(tab, [("تاریخ", 100), ("واحد", 220), ("مبلغ", 130),
                                     ("روش", 130), ("شرح", 280)], height=15)
        self.pay_table.pack(fill="both", expand=True, padx=6, pady=6)

    def add_payment(self):
        uid = self.selected_unit_id(self.pay_unit)
        date = parse_date(self.pay_date.get())
        amount = parse_amount(self.pay_amount.get())
        if amount == 0:
            raise ValueError("مبلغ نمی‌تواند صفر باشد.")
        self.db.x("INSERT INTO payments(unit_id, date, amount, method, description) VALUES(?,?,?,?,?)",
                  (uid, date, amount, self.pay_method.get().strip(), self.pay_desc.get().strip()))
        self.pay_amount.delete(0, "end")
        self.pay_desc.delete(0, "end")
        self.refresh_all()

    def delete_payment(self):
        pid = self.pay_table.selected_id()
        if not pid:
            raise ValueError("ابتدا یک ردیف را انتخاب کنید.")
        if messagebox.askyesno("حذف", "این دریافتی حذف شود؟"):
            self.db.x("DELETE FROM payments WHERE id=?", (pid,))
            self.refresh_all()

    def refresh_payments(self):
        rows = self.db.q("""SELECT p.*, u.number, u.owner FROM payments p JOIN units u ON u.id=p.unit_id
                            ORDER BY p.date DESC, p.id DESC""")
        self.pay_table.set_rows([
            (r["id"], (r["date"], f"واحد {r['number']} - {r['owner'] or ''}", fmt(r["amount"]),
                       r["method"] or "", r["description"] or "")) for r in rows])

    def print_receipt(self):
        pid = self.pay_table.selected_id()
        if not pid:
            raise ValueError("ابتدا یک دریافتی را از جدول انتخاب کنید.")
        p = self.db.one("""SELECT p.*, u.number, u.owner, u.id AS uid FROM payments p
                           JOIN units u ON u.id=p.unit_id WHERE p.id=?""", (pid,))
        u = next(x for x in self.db.units_with_balance() if x["id"] == p["uid"])
        body = table_html(["شرح", "مقدار"], [
            ("شماره رسید", p["id"]), ("واحد", p["number"]), ("نام", p["owner"] or ""),
            ("تاریخ پرداخت", p["date"]), ("مبلغ (تومان)", fmt(p["amount"])),
            ("روش پرداخت", p["method"] or ""), ("شرح", p["description"] or ""),
            ("وضعیت حساب واحد پس از پرداخت‌ها", balance_text(u["charged"] - u["paid"])),
        ]) + '<div class="sign"><span>امضای پرداخت‌کننده</span><span>امضای مدیر ساختمان</span></div>'
        open_report(self.building_name(), "رسید دریافت وجه", body, f"رسید شماره {p['id']}")

    # ================================================================ هزینه‌ها
    def build_expenses(self, tab):
        box = ctk.CTkFrame(tab)
        box.pack(fill="x", padx=6, pady=6)
        self.ex_date = self.entry(box, 200, today())
        self.ex_cat = self.combo(box, EXPENSE_CATEGORIES, editable=True)
        self.ex_amount = self.entry(box)
        self.ex_desc = self.entry(box, 200)
        self.form(box, [("تاریخ", self.ex_date), ("نوع هزینه", self.ex_cat),
                        ("مبلغ (تومان)", self.ex_amount), ("شرح", self.ex_desc)])
        self.buttons_row(tab, [
            ("ثبت هزینه", lambda: self.run(self.add_expense)),
            ("حذف ردیف انتخابی", lambda: self.run(self.delete_expense), "#dc2626"),
        ]).pack(fill="x", padx=6, pady=4)
        self.ex_total = ctk.CTkLabel(tab, text="", font=self.fb, anchor="e")
        self.ex_total.pack(fill="x", padx=12)
        self.ex_table = Table(tab, [("تاریخ", 100), ("نوع هزینه", 150), ("مبلغ", 130),
                                    ("شرح", 400)], height=15)
        self.ex_table.pack(fill="both", expand=True, padx=6, pady=6)

    def add_expense(self):
        date = parse_date(self.ex_date.get())
        amount = parse_amount(self.ex_amount.get())
        if amount == 0:
            raise ValueError("مبلغ نمی‌تواند صفر باشد.")
        cat = self.ex_cat.get().strip() or "سایر"
        self.db.x("INSERT INTO expenses(date, category, amount, description) VALUES(?,?,?,?)",
                  (date, cat, amount, self.ex_desc.get().strip()))
        self.ex_amount.delete(0, "end")
        self.ex_desc.delete(0, "end")
        self.refresh_all()

    def delete_expense(self):
        eid = self.ex_table.selected_id()
        if not eid:
            raise ValueError("ابتدا یک ردیف را انتخاب کنید.")
        if messagebox.askyesno("حذف", "این هزینه حذف شود؟"):
            self.db.x("DELETE FROM expenses WHERE id=?", (eid,))
            self.refresh_all()

    def refresh_expenses(self):
        rows = self.db.q("SELECT * FROM expenses ORDER BY date DESC, id DESC")
        self.ex_table.set_rows([(r["id"], (r["date"], r["category"] or "", fmt(r["amount"]),
                                           r["description"] or "")) for r in rows])
        self.ex_total.configure(text=f"جمع کل هزینه‌ها: {fmt(sum(r['amount'] for r in rows))} تومان")

    # ================================================================ گزارش‌ها
    def build_reports(self, tab):
        s = ctk.CTkFrame(tab)
        s.pack(fill="x", padx=6, pady=6)
        self.rep_building = self.entry(s, 260, self.db.get_setting("building_name", "ساختمان"))
        self.form(s, [("نام ساختمان (در سربرگ گزارش)", self.rep_building)], cols=1)
        self.button(s, "ذخیره نام", self.save_building_name).grid(row=0, column=2, padx=10)

        self.summary = ctk.CTkLabel(tab, text="", font=self.fb, justify="right", anchor="e")
        self.summary.pack(fill="x", padx=14, pady=8)

        f = ctk.CTkFrame(tab)
        f.pack(fill="x", padx=6, pady=6)
        y = today()[:4]
        self.rep_from = self.entry(f, 140, f"{y}/01/01")
        self.rep_to = self.entry(f, 140, today())
        self.rep_unit = self.combo(f, [], 240)
        self.form(f, [("از تاریخ", self.rep_from), ("تا تاریخ", self.rep_to),
                      ("واحد (برای صورتحساب)", self.rep_unit)])

        g = ctk.CTkFrame(tab, fg_color="transparent")
        g.pack(fill="x", padx=6, pady=10)
        reports = [
            ("خلاصه درآمد و هزینه (بازه)", self.rep_summary),
            ("ریز دریافتی‌ها (بازه)", self.rep_payments),
            ("ریز هزینه‌ها (بازه)", self.rep_expenses),
            ("وضعیت حساب همه واحدها", self.rep_balances),
            ("لیست بدهکاران", self.rep_debtors),
            ("صورتحساب کامل یک واحد", self.rep_statement),
            ("لیست واحدها و ساکنین", self.rep_units),
            ("پشتیبان‌گیری از اطلاعات", self.backup),
        ]
        for i, (text, cmd) in enumerate(reports):
            r, c = divmod(i, 4)
            ctk.CTkButton(g, text=text, font=self.f, height=44, width=240,
                          command=lambda c=cmd: self.run(c)).grid(row=r, column=3 - c, padx=8, pady=8)
        ctk.CTkLabel(tab, text="گزارش‌ها در مرورگر باز می‌شوند؛ با دکمه «چاپ» یا Ctrl+P چاپ کنید "
                               "(یا Save as PDF). یک نسخه هم در پوشه reports ذخیره می‌شود.",
                     font=self.f, text_color="gray40", anchor="e").pack(fill="x", padx=14, pady=6)

    def building_name(self):
        return self.db.get_setting("building_name", "ساختمان") or "ساختمان"

    def save_building_name(self):
        self.db.set_setting("building_name", self.rep_building.get().strip())
        messagebox.showinfo("ذخیره شد", "نام ساختمان ذخیره شد.")

    def refresh_summary(self):
        income = self.db.one("SELECT COALESCE(SUM(amount),0) s FROM payments")["s"]
        cost = self.db.one("SELECT COALESCE(SUM(amount),0) s FROM expenses")["s"]
        debt = sum(max(u["charged"] - u["paid"], 0) for u in self.db.units_with_balance())
        self.summary.configure(
            text=f"موجودی صندوق: {fmt(income - cost)} تومان     |     "
                 f"کل دریافتی‌ها: {fmt(income)}     |     کل هزینه‌ها: {fmt(cost)}     |     "
                 f"مجموع طلب از واحدها: {fmt(debt)}")

    def date_range(self):
        a, b = parse_date(self.rep_from.get()), parse_date(self.rep_to.get())
        if a > b:
            raise ValueError("«از تاریخ» نباید بعد از «تا تاریخ» باشد.")
        return a, b

    def rep_summary(self):
        a, b = self.date_range()
        income = self.db.one("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE date BETWEEN ? AND ?",
                             (a, b))["s"]
        cost = self.db.one("SELECT COALESCE(SUM(amount),0) s FROM expenses WHERE date BETWEEN ? AND ?",
                           (a, b))["s"]
        net = income - cost
        cats = self.db.q("""SELECT category, SUM(amount) s, COUNT(*) n FROM expenses
                            WHERE date BETWEEN ? AND ? GROUP BY category ORDER BY s DESC""", (a, b))
        months = self.db.q("""
            SELECT m, SUM(i) inc, SUM(e) exp FROM (
              SELECT substr(date,1,7) m, amount i, 0 e FROM payments WHERE date BETWEEN ? AND ?
              UNION ALL
              SELECT substr(date,1,7), 0, amount FROM expenses WHERE date BETWEEN ? AND ?)
            GROUP BY m ORDER BY m""", (a, b, a, b))
        body = cards_html([("جمع دریافتی‌ها", fmt(income), "pos"),
                           ("جمع هزینه‌ها", fmt(cost), "neg"),
                           ("مانده بازه", fmt(net), "pos" if net >= 0 else "neg")])
        body += "<h2>هزینه‌ها به تفکیک نوع</h2>" + table_html(
            ["نوع هزینه", "تعداد", "مبلغ (تومان)", "درصد"],
            [(c["category"], c["n"], fmt(c["s"]), f"{c['s'] * 100 / cost:.1f}٪" if cost else "-")
             for c in cats], ["جمع", sum(c["n"] for c in cats), fmt(cost), ""])
        body += "<h2>گردش ماهانه</h2>" + table_html(
            ["ماه", "دریافتی", "هزینه", "مانده"],
            [(m["m"], fmt(m["inc"]), fmt(m["exp"]), fmt(m["inc"] - m["exp"])) for m in months],
            ["جمع", fmt(income), fmt(cost), fmt(net)])
        open_report(self.building_name(), "خلاصه درآمد و هزینه", body, f"از {a} تا {b}")

    def rep_payments(self):
        a, b = self.date_range()
        rows = self.db.q("""SELECT p.*, u.number, u.owner FROM payments p JOIN units u ON u.id=p.unit_id
                            WHERE p.date BETWEEN ? AND ? ORDER BY p.date, p.id""", (a, b))
        total = sum(r["amount"] for r in rows)
        body = table_html(["ردیف", "تاریخ", "واحد", "نام", "مبلغ (تومان)", "روش", "شرح"],
                          [(i, r["date"], r["number"], r["owner"] or "", fmt(r["amount"]),
                            r["method"] or "", r["description"] or "") for i, r in enumerate(rows, 1)],
                          ["", "", "", "جمع", fmt(total), "", ""])
        open_report(self.building_name(), "ریز دریافتی‌ها", body, f"از {a} تا {b}")

    def rep_expenses(self):
        a, b = self.date_range()
        rows = self.db.q("SELECT * FROM expenses WHERE date BETWEEN ? AND ? ORDER BY date, id", (a, b))
        total = sum(r["amount"] for r in rows)
        body = table_html(["ردیف", "تاریخ", "نوع هزینه", "مبلغ (تومان)", "شرح"],
                          [(i, r["date"], r["category"] or "", fmt(r["amount"]), r["description"] or "")
                           for i, r in enumerate(rows, 1)], ["", "", "جمع", fmt(total), ""])
        open_report(self.building_name(), "ریز هزینه‌ها", body, f"از {a} تا {b}")

    def _balance_rows(self, only_debtors=False):
        out = []
        for u in self.db.units_with_balance():
            bal = u["charged"] - u["paid"]
            if only_debtors and bal <= 0:
                continue
            out.append((u, bal))
        return out

    def rep_balances(self, only_debtors=False):
        data = self._balance_rows(only_debtors)
        rows = [(u["number"], u["owner"] or "", u["phone"] or "", fmt(u["monthly_charge"]),
                 fmt(u["charged"]), fmt(u["paid"]), balance_text(bal)) for u, bal in data]
        total_debt = sum(max(bal, 0) for _, bal in data)
        body = table_html(["واحد", "نام", "تلفن", "شارژ ماهانه", "جمع بدهی صادره", "جمع پرداختی", "وضعیت"],
                          rows, ["", "", "", "", fmt(sum(u["charged"] for u, _ in data)),
                                 fmt(sum(u["paid"] for u, _ in data)), f"طلب کل: {fmt(total_debt)}"])
        title = "لیست بدهکاران" if only_debtors else "وضعیت حساب واحدها"
        open_report(self.building_name(), title, body, "تا امروز")

    def rep_debtors(self):
        self.rep_balances(only_debtors=True)

    def rep_statement(self):
        uid = self.selected_unit_id(self.rep_unit)
        u = self.db.one("SELECT * FROM units WHERE id=?", (uid,))
        items = [(c["period"] + "/01", 0, c["period"], c["description"] or "", c["amount"], 0)
                 for c in self.db.q("SELECT * FROM charges WHERE unit_id=?", (uid,))]
        items += [(p["date"], 1, p["date"], f"پرداخت - {p['method'] or ''} {p['description'] or ''}".strip(),
                   0, p["amount"])
                  for p in self.db.q("SELECT * FROM payments WHERE unit_id=?", (uid,))]
        items.sort(key=lambda t: (t[0], t[1]))
        bal, rows = 0, []
        for _, _, when, desc, debit, credit in items:
            bal += debit - credit
            rows.append((when, desc, fmt(debit) if debit else "", fmt(credit) if credit else "",
                         balance_text(bal)))
        td, tc = sum(t[4] for t in items), sum(t[5] for t in items)
        body = cards_html([("واحد", u["number"], ""), ("نام", u["owner"] or "-", ""),
                           ("شارژ ماهانه", fmt(u["monthly_charge"]), ""),
                           ("وضعیت حساب", balance_text(td - tc), "neg" if td > tc else "pos")])
        body += "<h2>گردش حساب</h2>" + table_html(
            ["تاریخ / دوره", "شرح", "بدهکار", "بستانکار", "مانده"], rows,
            ["", "جمع", fmt(td), fmt(tc), balance_text(td - tc)])
        open_report(self.building_name(), f"صورتحساب واحد {u['number']}", body, u["owner"] or "")

    def rep_units(self):
        rows = self.db.units_with_balance()
        body = table_html(["واحد", "نام مالک / ساکن", "تلفن", "متراژ", "نفرات", "شارژ ماهانه", "توضیحات"],
                          [(u["number"], u["owner"] or "", u["phone"] or "",
                            f"{u['area']:g}" if u["area"] else "", u["residents"] or "",
                            fmt(u["monthly_charge"]), u["note"] or "") for u in rows],
                          ["", f"{len(rows)} واحد", "", "", sum(u["residents"] or 0 for u in rows),
                           fmt(sum(u["monthly_charge"] for u in rows)), ""])
        open_report(self.building_name(), "لیست واحدها و ساکنین", body)

    def backup(self):
        path = filedialog.asksaveasfilename(
            title="ذخیره نسخه پشتیبان", defaultextension=".db",
            initialfile=f"building_backup_{datetime.now():%Y%m%d}.db",
            filetypes=[("Database", "*.db")])
        if not path:
            return
        dest = sqlite3.connect(path)
        with dest:
            self.db.conn.backup(dest)
        dest.close()
        messagebox.showinfo("پشتیبان", f"نسخه پشتیبان ذخیره شد:\n{path}")


if __name__ == "__main__":
    App().mainloop()
