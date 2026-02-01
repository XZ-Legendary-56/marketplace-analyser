# utils/io.py
import os
import pandas as pd
import logging
from datetime import datetime

LOG_DIR = "logs"
os.makedirs(LOG_DIR, exist_ok=True)
log_filename = datetime.now().strftime("report_%Y-%m-%d.log")
log_path = os.path.join(LOG_DIR, log_filename)
logging.basicConfig(
    filename=log_path,
    filemode="w",
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)

def log(msg: str):
    print(msg)
    logging.info(msg)

def load_files(raw_data_path: str):
    files = []
    if not os.path.isdir(raw_data_path):
        log(f"Директория не найдена: {raw_data_path}")
        return []
        
    for file in os.listdir(raw_data_path):
        if file.endswith(".xlsx") and not file.startswith("~$"):
            lower = file.lower()
            if "ozon_report" in lower:
                files.append(("ozon", os.path.join(raw_data_path, file)))
            elif "wb_report" in lower or "wildberries" in lower:
                files.append(("wb", os.path.join(raw_data_path, file)))
            elif "ym_report" in lower or "yandex" in lower:
                files.append(("ym", os.path.join(raw_data_path, file)))
    return files

def filter_by_date(df: pd.DataFrame, date_column: str, start_date: str, end_date: str):
    if date_column not in df.columns:
        log(f"Нет колонки даты: {date_column}, фильтрация невозможна")
        return df
    df[date_column] = pd.to_datetime(df[date_column], errors="coerce")
    return df[(df[date_column] >= pd.to_datetime(start_date)) & (df[date_column] <= pd.to_datetime(end_date))]

def validate_columns(df: pd.DataFrame, required: list, label: str):
    missing = [col for col in required if col not in df.columns]
    if missing:
        log(f"Отсутствуют колонки в {label}: {missing}")