import pandas as pd
from pathlib import Path

def merge_files_by_prefix(folder: str, prefix: str) -> pd.DataFrame:
    """Объединяет все файлы из папки, начинающиеся с заданного префикса"""
    dfs = []
    folder_path = Path(folder)
    for file in folder_path.glob(f"{prefix}*.xlsx"):
        df = pd.read_excel(file)
        df["Маркетплейс"] = file.stem.split("_")[0]
        dfs.append(df)
    if dfs:
        return pd.concat(dfs, ignore_index=True)
    else:
        return pd.DataFrame()