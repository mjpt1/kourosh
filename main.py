import os
import html
import webbrowser
from pathlib import Path

import customtkinter as ctk
from persiantools.jdatetime import JalaliDate

# تنظیم ظاهر کلی برنامه
ctk.set_appearance_mode("System")
ctk.set_default_color_theme("blue")

BASE_DIR = Path(os.path.dirname(os.path.abspath(__file__)))


class MainApp(ctk.CTk):
    def __init__(self):
        super().__init__()

        # تنظیمات پنجره اصلی
        self.title("نرم‌افزار مدیریت")
        self.geometry("800x600")

        # فونت سراسری برنامه (Vazirmatn باید روی سیستم نصب باشد)
        self.vazir_font = ctk.CTkFont(family="Vazirmatn", size=14)
        self.vazir_font_bold = ctk.CTkFont(family="Vazirmatn", size=16, weight="bold")

        self.create_widgets()

    def create_widgets(self):
        # فریم اصلی
        self.main_frame = ctk.CTkFrame(self)
        self.main_frame.pack(fill="both", expand=True, padx=20, pady=20)

        # عنوان
        self.title_label = ctk.CTkLabel(
            self.main_frame,
            text="فرم ثبت اطلاعات و گزارش‌گیری",
            font=self.vazir_font_bold,
        )
        self.title_label.pack(pady=(10, 20))

        # ورودی نام (راست‌چین)
        self.name_entry = ctk.CTkEntry(
            self.main_frame,
            placeholder_text="نام و نام خانوادگی",
            font=self.vazir_font,
            width=300,
            justify="right",
        )
        self.name_entry.pack(pady=10)

        # ورودی توضیحات (راست‌چین)
        self.desc_entry = ctk.CTkEntry(
            self.main_frame,
            placeholder_text="توضیحات",
            font=self.vazir_font,
            width=300,
            justify="right",
        )
        self.desc_entry.pack(pady=10)

        # دکمه تولید گزارش HTML
        self.report_btn = ctk.CTkButton(
            self.main_frame,
            text="ایجاد گزارش HTML",
            font=self.vazir_font,
            command=self.generate_report,
        )
        self.report_btn.pack(pady=40)

        # پیام وضعیت
        self.status_label = ctk.CTkLabel(self.main_frame, text="", font=self.vazir_font)
        self.status_label.pack()

    def generate_report(self):
        # دریافت مقادیر و escape برای جلوگیری از خراب شدن HTML
        name = html.escape(self.name_entry.get().strip() or "بدون نام")
        desc = html.escape(self.desc_entry.get().strip() or "بدون توضیحات")

        # تاریخ امروز شمسی
        today = JalaliDate.today().strftime("%Y/%m/%d")

        html_content = f"""<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>گزارش سیستم</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {{
            font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
            margin: 40px;
            background-color: #f4f6f9;
            color: #333;
        }}
        .container {{
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: auto;
        }}
        h1 {{
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }}
        .date {{
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 30px;
        }}
        .info-box {{
            background-color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }}
    </style>
</head>
<body>
    <div class="container">
        <h1>گزارش ثبت اطلاعات</h1>
        <div class="date">تاریخ گزارش: {today}</div>
        <div class="info-box">
            <p><strong>نام:</strong> {name}</p>
        </div>
        <div class="info-box">
            <p><strong>توضیحات:</strong> {desc}</p>
        </div>
    </div>
</body>
</html>
"""

        # ذخیره فایل کنار اسکریپت
        report_path = BASE_DIR / "report.html"
        try:
            report_path.write_text(html_content, encoding="utf-8")
        except OSError as e:
            self.status_label.configure(text=f"خطا در ذخیره گزارش: {e}", text_color="red")
            return

        # باز کردن گزارش در مرورگر (روی همه سیستم‌عامل‌ها)
        webbrowser.open(report_path.as_uri())
        self.status_label.configure(text="گزارش ساخته شد.", text_color="green")


if __name__ == "__main__":
    app = MainApp()
    app.mainloop()
