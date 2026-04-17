import os
import pandas as pd
from sqlalchemy import create_engine
from dotenv import load_dotenv

load_dotenv()

DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}"
engine = create_engine(DATABASE_URL)


def normalize_text(value):
    if pd.isna(value) or value is None:
        return ""
    return str(value).strip().lower()


def clean_nan_records(records):
    cleaned = []
    for record in records:
        new_record = {}
        for key, value in record.items():
            if pd.isna(value):
                new_record[key] = None
            else:
                new_record[key] = value
        cleaned.append(new_record)
    return cleaned


def load_etablissements():
    query = """
        SELECT 
            idEtablissement AS id,
            nom,
            description,
            adresse,
            ville,
            gammePrix AS budget,
            type,
            image_name,
            latitude,
            longitude
        FROM etablissement
    """
    df = pd.read_sql(query, engine)

    df["ville"] = df["ville"].apply(normalize_text)
    df["budget"] = df["budget"].apply(normalize_text)
    df["type"] = df["type"].apply(normalize_text)

    return df


def load_activites():
    query = """
        SELECT 
            a.idActivite AS id,
            a.nomActivite AS nom,
            a.description,
            a.categorie,
            a.prix,
            a.image_name,
            a.idEtablissement,
            e.ville,
            e.nom AS etablissement_nom
        FROM activite a
        INNER JOIN etablissement e ON a.idEtablissement = e.idEtablissement
    """
    df = pd.read_sql(query, engine)

    def map_budget(prix):
        if pd.isna(prix):
            return "moyen"
        if prix < 50:
            return "faible"
        elif prix < 150:
            return "moyen"
        return "eleve"

    df["budget"] = df["prix"].apply(map_budget)
    df["ville"] = df["ville"].apply(normalize_text)
    df["budget"] = df["budget"].apply(normalize_text)
    df["categorie"] = df["categorie"].apply(normalize_text)

    return df


def recommend_etablissements(ville=None, budget=None, type_etab=None, top_n=5):
    df = load_etablissements().copy()

    ville = normalize_text(ville)
    type_etab = normalize_text(type_etab)

    # 1) filtre exact
    if ville or type_etab:
        filtered = df.copy()

        if ville:
            filtered = filtered[filtered["ville"] == ville]

        if type_etab:
            filtered = filtered[filtered["type"] == type_etab]

        if not filtered.empty:
            filtered["score"] = 100
            records = filtered[["id", "nom", "ville", "type", "budget", "score", "image_name"]] \
                .head(top_n).to_dict(orient="records")
            return clean_nan_records(records)

    # 2) fallback : garder d'abord seulement la ville si elle existe
    if ville:
        city_df = df[df["ville"] == ville].copy()
        if not city_df.empty:
            df = city_df

    df["score"] = 0

    if ville:
        df.loc[df["ville"] == ville, "score"] += 10

    if type_etab:
        df.loc[df["type"] == type_etab, "score"] += 8

    records = df[["id", "nom", "ville", "type", "budget", "score", "image_name"]] \
        .sort_values(by="score", ascending=False) \
        .head(top_n).to_dict(orient="records")

    return clean_nan_records(records)


def recommend_activites(ville=None, budget=None, categorie=None, top_n=5):
    df = load_activites().copy()

    ville = normalize_text(ville)
    budget = normalize_text(budget)
    categorie = normalize_text(categorie)

    # 1) filtre exact
    if ville or categorie or budget:
        filtered = df.copy()

        if ville:
            filtered = filtered[filtered["ville"] == ville]

        if categorie:
            filtered = filtered[filtered["categorie"] == categorie]

        if budget:
            filtered = filtered[filtered["budget"] == budget]

        if not filtered.empty:
            filtered["score"] = 100
            records = filtered[["id", "nom", "ville", "categorie", "budget", "score", "image_name", "etablissement_nom"]] \
                .head(top_n).to_dict(orient="records")
            return clean_nan_records(records)

    # 2) fallback : garder d'abord seulement la ville si elle existe
    if ville:
        city_df = df[df["ville"] == ville].copy()
        if not city_df.empty:
            df = city_df

    df["score"] = 0

    if ville:
        df.loc[df["ville"] == ville, "score"] += 10

    if categorie:
        df.loc[df["categorie"] == categorie, "score"] += 8

    if budget:
        df.loc[df["budget"] == budget, "score"] += 4

    records = df[["id", "nom", "ville", "categorie", "budget", "score", "image_name", "etablissement_nom"]] \
        .sort_values(by="score", ascending=False) \
        .head(top_n).to_dict(orient="records")

    return clean_nan_records(records)