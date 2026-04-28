from flask import Flask, request, jsonify
import joblib
import pandas as pd
import os

app = Flask(__name__)


MODEL_PATH = os.path.join(os.path.dirname(__file__), 'random_forest_fraud_model.pkl')


try:
    model = joblib.load(MODEL_PATH)
    print("Modèle chargé avec succès.")
except Exception as e:
    print(f"Erreur lors du chargement du modèle: {e}")
    model = None


@app.route('/predict', methods=['POST'])
def predict():
    if model is None:
        return jsonify({'error': 'Modèle non chargé'}), 500

    try:
        
        data = request.get_json()

        
        df = pd.DataFrame([data])

        
        prediction = model.predict(df)
        prediction_proba = model.predict_proba(df)

        is_fraud = bool(prediction[0] == 1)
        
        percentage = float(prediction_proba[0][1]) * 100

        
        return jsonify({
            'fraud_alert': is_fraud,
            'percentage': round(percentage, 2)
        })

    except Exception as e:
        return jsonify({'error': str(e)}), 400

if __name__ == '__main__':
    
    app.run(port=5000, debug=False)