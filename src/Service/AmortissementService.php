<?php
namespace App\Service;

class AmortissementService {
    /**
 * @return array<int, array{
 *     numero: int,
 *     date: \DateTime,
 *     mensualite: float,
 *     interets: float,
 *     principal: float,
 *     solde: float
 * }>
 */
    public function calculerTableau( float $montant,float $tauxAnnuel,int $dureeMois): array {
        $tauxMensuel = ($tauxAnnuel / 100) / 12;
        $mensualite = ($montant * $tauxMensuel) / (1 - pow(1 + $tauxMensuel, -$dureeMois));
        
        $tableau = [];
        $capitalRestant = $montant;

        for ($i = 1; $i <= $dureeMois; $i++) {
            $interets = $capitalRestant * $tauxMensuel;
            $principal = $mensualite - $interets;
            $capitalRestant -= $principal;

            $tableau[] = [
                'numero' => $i,
                'date' => (new \DateTime())->modify("+$i month"),
                'mensualite' => round($mensualite, 2),
                'interets' => round($interets, 2),
                'principal' => round($principal, 2),
                'solde' => max(0, round($capitalRestant, 2))
            ];
        }
        return $tableau;
    }
}