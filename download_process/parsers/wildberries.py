# parsers/wildberries.py
import pandas as pd

def parse_wb_report(filepath: str):
    df = pd.read_excel(filepath)
    df["Дата"] = pd.to_datetime(df["Дата"], errors='coerce').dt.date

    # Продажи (убираем "Скидка итого")
    sales_df = df.rename(columns={
        "ID заказа": "ID продажи",
        "Название товара": "Название товара",
        "Итого выручка": "Чистая выручка",
        "Дата": "Дата операции"
    })
    sales_columns = ["ID продажи", "Название товара", "Чистая выручка", "Дата операции"]
    sales_df = sales_df[sales_columns]

    # Услуги
    service_rows = []
    for _, row in df.iterrows():
        for услуга, значение in [("Комиссия", row.get("Комиссия")), ("Эквайринг", row.get("Эквайринг"))]:
            if pd.notnull(значение):
                service_rows.append({
                    "ID": row.get("ID заказа"),
                    "Услуга": услуга,
                    "Название товара": row.get("Название товара"),
                    "Цена": значение,
                    "Дата операции": row.get("Дата")
                })
    services_df = pd.DataFrame(service_rows)

    # Пустая таблица рекламы
    ads_df = pd.DataFrame(columns=["ID", "Тип рекламы", "Название товара", "Цена", "Дата операции"])

    return sales_df, services_df, ads_df