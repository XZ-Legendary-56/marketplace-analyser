import pandas as pd
import sys
import os
import json
from datetime import datetime
from dateutil.relativedelta import relativedelta

from parsers.ozon import parse_ozon_report
from parsers.wildberries import parse_wb_report
from parsers.yandex import parse_yandex_report
from utils.final_report import save_final_report
from utils.io import load_files, log, filter_by_date

PARSERS = {
    "ozon": parse_ozon_report,
    "wb": parse_wb_report,
    "ym": parse_yandex_report
}

def process_data(data_path, report_date_str):
    try:
        start_date = datetime.strptime(report_date_str, "%Y-%m").date()
        end_date = (start_date + relativedelta(months=1)) - relativedelta(days=1)
        
        start_date_str = start_date.strftime("%Y-%m-%d")
        end_date_str = end_date.strftime("%Y-%m-%d")

        grouped_data = {
            "ozon": {"sales": [], "services": [], "ads": []},
            "wb": {"sales": [], "services": [], "ads": []},
            "ym": {"sales": [], "services": [], "ads": []},
        }
        
        for source, filepath in load_files(data_path):
            parser = PARSERS.get(source)
            if parser is None:
                log(f"Нет парсера для {source}")
                continue

            try:
                sales, services, ads = parser(filepath)
                
                grouped_data[source]["sales"].append(sales)
                grouped_data[source]["services"].append(services)
                grouped_data[source]["ads"].append(ads)

            except Exception as e:
                log(f"Ошибка обработки {os.path.basename(filepath)}: {str(e)}")
        
        processed_files = []
        for source, parts in grouped_data.items():
            sales_nonempty = [df for df in parts["sales"] if not df.empty]
            services_nonempty = [df for df in parts["services"] if not df.empty]
            ads_nonempty = [df for df in parts["ads"] if not df.empty]

            if not any([sales_nonempty, services_nonempty, ads_nonempty]):
                continue

            df_sales = pd.concat(sales_nonempty, ignore_index=True) if sales_nonempty else pd.DataFrame()
            df_services = pd.concat(services_nonempty, ignore_index=True) if services_nonempty else pd.DataFrame()
            df_ads = pd.concat(ads_nonempty, ignore_index=True) if ads_nonempty else pd.DataFrame()

            # Сохраняем обработанный файл в ту же папку, что и исходники
            filename = os.path.join(data_path, f"{source}_processed_report.xlsx")
            save_final_report(df_sales, df_services, df_ads, filename)
            processed_files.append(filename)
        
        return {"status": "success", "processed_files": processed_files}

    except Exception as e:
        log(f"Критическая ошибка в process_data: {e}")
        return {"status": "error", "message": str(e)}


if __name__ == "__main__":
    if len(sys.argv) != 3:
        error_result = {
            "status": "error",
            "message": f"Неверное количество аргументов. Ожидается 2, получено {len(sys.argv) - 1}"
        }
        print(json.dumps(error_result))
        sys.exit(1)

    path_to_data = sys.argv[1]
    report_date = sys.argv[2]
    
    result = process_data(path_to_data, report_date)
    print(json.dumps(result, ensure_ascii=False))