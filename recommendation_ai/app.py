from flask import Flask, jsonify, request
from recommender import recommend_etablissements, recommend_activites

app = Flask(__name__)

@app.route("/recommend", methods=["GET"])
def recommend():
    ville = request.args.get("ville")
    budget = request.args.get("budget")
    type_etab = request.args.get("type")
    categorie = request.args.get("categorie")
    top_n = request.args.get("top_n", default=5, type=int)

    try:
        etabs = recommend_etablissements(
            ville=ville,
            budget=budget,
            type_etab=type_etab,
            top_n=top_n
        )

        acts = recommend_activites(
            ville=ville,
            budget=budget,
            categorie=categorie,
            top_n=top_n
        )

        return jsonify({
            "filters": {
                "ville": ville,
                "budget": budget,
                "type": type_etab,
                "categorie": categorie
            },
            "recommended_etablissements": etabs,
            "recommended_activites": acts
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500


if __name__ == "__main__":
    app.run(port=5001, debug=True)