# parsers/ozon.py
import pandas as pd

def parse_ozon_report(filepath: str):
    df = pd.read_excel(filepath)
    df["Дата"] = pd.to_datetime(df.get("Дата"), errors='coerce').dt.date

    sales_types = ["Выручка", "Баллы за скидки", "Программы партнёров"]
    ad_types = ["Трафареты", "Продвижение в поиске"]

    # Продажи
    sales_df = df[df["Тип начисления"].isin(sales_types)].copy()

    # Сохраняем количество до разворота
    if "Количество" in sales_df.columns:
        sales_df["Кол-во"] = pd.to_numeric(sales_df["Количество"], errors="coerce").fillna(1).astype(int)
    else:
        sales_df["Кол-во"] = 1

    sales_df = sales_df.rename(columns={
        "ID начисления": "ID продажи",
        "Название товара": "Название товара",
        "Сумма итого, руб": "Чистая выручка",
        "Дата": "Дата операции"
    })

    sales_columns = ["ID продажи", "Название товара", "Чистая выручка", "Кол-во", "Дата операции"]
    sales_df = sales_df[[col for col in sales_columns if col in sales_df.columns]]

    # Услуги — всё, что не продажи и не реклама
    excluded_types = sales_types + ad_types
    services_df = df[~df["Тип начисления"].isin(excluded_types)].copy()
    services_df = services_df.rename(columns={
        "ID начисления": "ID",
        "Тип начисления": "Услуга",
        "Название товара": "Название товара",
        "Сумма итого, руб": "Цена",
        "Дата": "Дата операция"
    })
    services_columns = ["ID", "Услуга", "Название товара", "Цена", "Дата операция"]
    services_df = services_df[[col for col in services_columns if col in services_df.columns]]

    # Реклама
    ads_df = df[df["Тип начисления"].isin(ad_types)].copy()
    ads_df = ads_df.rename(columns={
        "ID начисления": "ID",
        "Тип начисления": "Тип рекламы",
        "Сумма итого, руб": "Цена",
        "Дата": "Дата операция",
        "Название товара": "Название товара"
    })
    ads_columns = ["ID", "Тип рекламы", "Название товара", "Цена", "Дата операция"]
    ads_df = ads_df[[col for col in ads_columns if col in ads_df.columns]]

    return sales_df, services_df, ads_df