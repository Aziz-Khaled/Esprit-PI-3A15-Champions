from flask import Flask, request, jsonify
import joblib
import pandas as pd
import os

app = Flask(__name__)

# --- CONFIGURATION ---
# Chemin vers le modèle .pkl dans le même dossier que app.py
MODEL_PATH = os.path.join(os.path.dirname(__file__), 'random_forest_fraud_model.pkl')

# --- CHARGEMENT DU MODÈLE ---
try:
    model = joblib.load(MODEL_PATH)
    print("Modèle chargé avec succès.")
except Exception as e:
    print(f"Erreur lors du chargement du modèle: {e}")
    model = None

# --- ROUTE POUR LA PRÉDICTION ---
@app.route('/predict', methods=['POST'])
def predict():
    if model is None:
        return jsonify({'error': 'Modèle non chargé'}), 500

    try:
        # Récupérer les données JSON envoyées par Java
        data = request.get_json()

        # Transformer en DataFrame
        df = pd.DataFrame([data])

        # Faire la prédiction (classe) et obtenir la probabilité
        prediction = model.predict(df)
        prediction_proba = model.predict_proba(df)

        is_fraud = bool(prediction[0] == 1)
        # Calculer le pourcentage de probabilité de fraude
        percentage = float(prediction_proba[0][1]) * 100

        # 🚀 Retourner le résultat complet à Java
        return jsonify({
            'fraud_alert': is_fraud,
            'percentage': round(percentage, 2)
        })

    except Exception as e:
        return jsonify({'error': str(e)}), 400

if __name__ == '__main__':
    # Lance le serveur sur http://127.0.0.1:5000
    app.run(port=5000, debug=False)