import joblib
import pandas as pd
import sys
import json
import os

def main():
    # Vérification du nombre d'arguments attendus (6 features)
    if len(sys.argv) != 7:
        print(json.dumps({"error": "Nombre d'arguments invalide. Attendu: 6"}))
        sys.exit(1)

    try:
        # Chemin absolu du modèle
        script_dir = os.path.dirname(os.path.abspath(__file__))
        model_filename = os.path.join(script_dir, 'random_forest_fraud_model.pkl')

        # Charger le modèle
        loaded_model = joblib.load(model_filename)

        # Récupération des arguments
        is_same_card_repeat = int(sys.argv[1])
        montant_category = int(sys.argv[2])
        consecutive_card_recharges = int(sys.argv[3])
        trading_flow_signal = int(sys.argv[4])
        daily_internal_transfers = int(sys.argv[5])
        daily_conversion_count = int(sys.argv[6])

        # Créer le DataFrame
        input_data = pd.DataFrame({
            'is_same_card_repeat': [is_same_card_repeat],
            'montant_category': [montant_category],
            'consecutive_card_recharges': [consecutive_card_recharges],
            'trading_flow_signal': [trading_flow_signal],
            'daily_internal_transfers': [daily_internal_transfers],
            'daily_conversion_count': [daily_conversion_count]
        })

        # Prédiction (classe) et Probabilités
        prediction = loaded_model.predict(input_data)
        prediction_proba = loaded_model.predict_proba(input_data)

        # Résultat : Probabilité de fraude (classe 1) en pourcentage
        proba_fraude = float(prediction_proba[0][1]) * 100

        result = {
            "prediction": int(prediction[0]),
            "probability": round(proba_fraude, 2)
        }

        # Afficher le JSON résultant
        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({"error": f"Erreur lors de la prédiction: {str(e)}"}))
        sys.exit(1)

if __name__ == "__main__":
    main()