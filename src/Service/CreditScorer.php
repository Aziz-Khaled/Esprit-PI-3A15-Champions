<?php

namespace App\Service;

class CreditScorer
{
    /**
     * Calcule l'analyse complète du risque financier
     */

    /**
 * @return array{
 *   note: string,
 *   fiabilite: float,
 *   rentabilite: float,
 *   risque: float,
 *   solde: float,
 *   recommandation: string
 * }
 */
    public function getRiskAnalysis(float $montantNegocie, float $tauxPropose): array
    {
        // Simulation du solde de l'investisseur (comme sur ta capture)
        $soldeInvestisseur = 100.20; 

        // 1. Algorithme de Fiabilité (Ratio Solde/Engagement)
        $ratio = ($soldeInvestisseur / $montantNegocie);
        $fiabilite = min(10, ($ratio * 5) + 5);

        // 2. Calcul du Risque de Défaut (Complexifié avec pondération)
        $risqueBase = 100 - ($fiabilite * 10);
        $facteurTaux = ($tauxPropose > 5) ? 1.2 : 1.0; // Un taux trop haut augmente le risque
        $risqueFinal = max(2, min(98, $risqueBase * $facteurTaux));

        // 3. Calcul de la Rentabilité (Score sur 10)
        $rentabilite = min(10, ($tauxPropose * 0.8) + ($montantNegocie / 500));

        // 4. Détermination de la Note (A, B ou C)
        $note = $this->determineGrade($fiabilite, $risqueFinal);

        return [
            'note' => $note,
            'fiabilite' => round($fiabilite, 1),
            'rentabilite' => round($rentabilite, 1),
            'risque' => round($risqueFinal, 0),
            'solde' => $soldeInvestisseur,
            'recommandation' => $this->getRecommendation($note, $risqueFinal)
        ];
    }

    private function determineGrade(float $fiabilite, float $risque): string
    {
        if ($fiabilite > 8 && $risque < 20) return 'A';
        if ($fiabilite > 5 && $risque < 50) return 'B';
        return 'C';
    }

    private function getRecommendation(string $note, float $risque): string
    {
        if ($note === 'A') return "Excellent profil. L'investissement est fortement recommandé.";
        if ($note === 'B') return "Profil équilibré. Risque modéré mais acceptable.";
        return "Risque élevé. Une garantie supplémentaire est conseillée.";
    }
}