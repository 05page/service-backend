<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Achats;
use App\Models\Commission;
use App\Models\Fournisseurs;
use App\Models\Permissions;
use App\Models\Stock;
use App\Models\User;
use App\Models\Ventes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{

    public function allStats(): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($user->role === User::ROLE_ADMIN) {
                // ✅ Stats globales pour l'admin
                $totalVente = Ventes::Paye()->sum('prix_total');
                $totalAchat = Achats::Reçu()->sum('prix_total');
                $totalCommission = Commission::CommissionsPayees()->sum('commission_due');
                $allStats = [
                    // Ventes
                    'total_ventes' => Ventes::Paye()->count(), // ventes payées
                    'ventes_en_attente' => Ventes::EnAttente()->count(),
                    'ventes_annule' => Ventes::Annule()->count(),
                    'ventes_regles' => Ventes::Regle()->count(),
                    'chiffres_affaire_total' => $totalVente,
                    'chiffres_affaire_mois' => Ventes::Paye()
                        ->whereMonth('created_at', Carbon::now()->month)
                        ->whereYear('created_at', Carbon::now()->year)
                        ->sum('prix_total'),
                    'chiffre_affaires_jour' => Ventes::Paye()->whereDate('created_at', today())->sum('prix_total'),
                    //Bénéfice total
                    'benefices_total' => $totalVente - $totalAchat - $totalCommission,
                    'benefice_mois' =>
                    Ventes::Paye()
                        ->whereMonth('created_at', Carbon::now()->month)
                        ->whereYear('created_at', Carbon::now()->year)
                        ->sum('prix_total')
                        - Achats::Reçu()
                        ->whereMonth('created_at', Carbon::now()->month)
                        ->whereYear('created_at', Carbon::now()->year)
                        ->sum('prix_total')
                        - Commission::CommissionsPayees()
                        ->whereMonth('created_at', Carbon::now()->month)
                        ->whereYear('created_at', Carbon::now()->year)
                        ->sum('commission_due'),

                    // Clients
                    'total_client' => Ventes::distinct('nom_client')->count('nom_client'),

                    // Achats
                    'total_achats' => Achats::count(),
                    'total_achat_commande' => Achats::where('statut', Achats::ACHAT_COMMANDE)->count(),
                    'total_achats_recu' => Achats::where('statut', Achats::ACHAT_REÇU)->count(),
                    'total_prix_achats' => $totalAchat,

                    // Stock
                    'total_entrees_stock' => Stock::Entre()->sum('entre_stock'),
                    'total_sorties_stock' => Stock::Sortie()->sum('sortie_stock'),
                    'total_produits_stock' => Stock::where('actif', true)->count(),
                    'total_stock_disponible' => Stock::StockDisponible()->count(),
                    'total_stock_faible' => Stock::StockFaible()->count(),
                    'total_valeur_stock' => Stock::StockDisponible()->sum('prix_vente'),
                ];
            } else {
                // ✅ Stats limitées pour employé
                $allStats = [
                    'total_ventes' => Ventes::Paye()->where('created_by', $user->id)->count(),
                    'ventes_regles' => Ventes::Regle()->where('created_by', $user->id)->count(),
                    'ventes_en_attente' => Ventes::EnAttente()->where('created_by', $user->id)->count(),
                    'ventes_annule' => Ventes::Annule()->where('created_by', $user->id)->count(),
                    'chiffres_affaire_total' => Ventes::Paye()->where('created_by', $user->id)->sum('prix_total'),
                    'total_client' => Ventes::where('created_by', $user->id)->select('nom_client')->distinct()->count(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $allStats,
                'message' => 'Statistiques récupérées avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function statsDashboard(): JsonResponse
    {
        try {
            $user = Auth::user();
            if ($user->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => "Seul un administrateur à l'accès"
                ], 403);
            }

            $allStats = [
                //Total des clients basé sur les ventes
                'total_client' => Ventes::distinct("nom_client")->count("nom_client"),
                'nouveau_cient' => Ventes::whereMonth('created_at', now()->month())->whereYear('created_at', now()->year())
                    ->distinct("nom_client")
                    ->count("nom_client"),
                'total_ventes' => Ventes::count(),

                //Total personnels
                'total_employe' => User::whereIn('role', [User::ROLE_EMPLOYE, User::ROLE_INTERMEDIAIRE])->count(),
                'total_employe_inactifs' => User::where('role', User::ROLE_EMPLOYE)->where('active', false)->count(),
                'total_employe_actif' => User::where('role', User::ROLE_EMPLOYE)->where('active', true)->count(),
                'total_ventes_employes' => Ventes::whereHas('creePar', function ($query) {
                    $query->where('role', User::ROLE_EMPLOYE);
                })->count(),
                'total_personnels' => User::where('role', User::ROLE_EMPLOYE)->where('active', true)->count(),

                //Total Fournisseurs
                'total_fournisseurs' => Fournisseurs::where('actif', true)->count(),
                'total_fournisseurs_inactif' => Fournisseurs::where('actif', false)->count(),
                'nouveaux_fournisseurs_mois' => Fournisseurs::whereMonth('created_at', now()->month())->whereYear('created_at', now()->year())->count(),

                //total stock
                'total_entrees_stock' => Stock::Entre()->sum('entre_stock'),
                'total_sorties_stock' => Stock::Sortie()->sum('sortie_stock'),
                'total_produits_stock' => DB::table('stock')->where('actif', true)->count(),
                'total_stock_faible' => Stock::StockFaible()->count(),

                //Commission
                'total_commissions_dues' => Commission::CommissionsDues()->sum('commission_due'),
                'total_commissions_reversees' => Commission::CommissionsPayees()->sum('commission_due'),
                //Revenu
                'chiffres_affaire_total' => Ventes::Paye()->sum('prix_total'),
                'chiffres_affaire_mois' => Ventes::Paye()
                    ->whereMonth('created_at', now()->month())
                    ->whereYear('created_at', now()->year())
                    ->count(),
            ];
            return response()->json([
                'success' => true,
                'data' => $allStats,
                'message' => 'Statistiques récupérées avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération des statistiques',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function monthlyStats(): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($user->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => "Seul un administrateur a l'accès"
                ], 403);
            }

            // Récupérer les 12 derniers mois
            $monthlyData = [];

            for ($i = 11; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $month = $date->month;
                $year = $date->year;

                // Ventes du mois
                $ventesMonth = Ventes::Paye()
                    ->whereMonth('created_at', $month)
                    ->whereYear('created_at', $year)
                    ->sum('prix_total');

                // Achats du mois
                $achatsMonth = Achats::Reçu()
                    ->whereMonth('created_at', $month)
                    ->whereYear('created_at', $year)
                    ->sum('prix_total');

                // Commissions du mois
                $commissionsMonth = Commission::CommissionsPayees()
                    ->whereMonth('created_at', $month)
                    ->whereYear('created_at', $year)
                    ->sum('commission_due');

                // Bénéfices = Ventes - Achats - Commissions
                $beneficesMonth = $ventesMonth - $achatsMonth - $commissionsMonth;

                $monthlyData[] = [
                    'mois' => $date->locale('fr')->isoFormat('MMM'),
                    'mois_complet' => $date->locale('fr')->isoFormat('MMMM YYYY'),
                    'ventes' => (float) $ventesMonth,
                    'achats' => (float) $achatsMonth,
                    'commissions' => (float) $commissionsMonth,
                    'benefices' => (float) $beneficesMonth
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $monthlyData,
                'message' => 'Statistiques mensuelles récupérées avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques mensuelles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
