import pandas as pd

def parse_yandex_report(filepath: str):
    df = pd.read_excel(filepath)
    df["Дата"] = pd.to_datetime(df["Дата"], errors='coerce').dt.date

    # Продажи
    sales_df = df[df["Тип транзакции"] == "Начисление"].copy()
    sales_df = sales_df.rename(columns={
        "Название": "Название товара",
        "Сумма": "Чистая выручка",
        "Дата": "Дата операции"
    })
    sales_df["ID продажи"] = [f"YANDEX-{i+1}" for i in sales_df.index]
    sales_columns = ["ID продажи", "Название товара", "Чистая выручка", "Дата операции"]
    sales_df = sales_df[[col for col in sales_columns if col in sales_df.columns]]

    # Услуги
    services_df = df[df["Тип транзакции"] == "Удержание"].copy()
    services_df = services_df.rename(columns={
        "Название": "Услуга",
        "Сумма": "Цена",
        "Дата": "Дата операции"
    })
    services_df["Название товара"] = "-"
    services_df["ID"] = [f"YANDEX-{i+1}" for i in services_df.index]
    service_columns = ["ID", "Услуга", "Название товара", "Цена", "Дата операции"]
    services_df = services_df[[col for col in service_columns if col in services_df.columns]]

    # Реклама (пустая)
    ads_df = pd.DataFrame(columns=["ID", "Тип рекламы", "Название товара", "Цена", "Дата операции"])

    return sales_df, services_df, ads_df