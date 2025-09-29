<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Achats;
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
    //
    // private function verifierPermissions()
    // {
    //     $user = Auth::user();
    //     if ($user->role !== User::ROLE_ADMIN) {
    //         /** @var User $user */
    //         $hasPermission = $user->permissions()
    //             ->where('module', Permissions::MODULE_VENTES)
    //             ->where('active', true)->exists();
    //         if (!$hasPermission) {
    //             return false;
    //         }
    //     }
    //     return true;
    // }

    public function allStats(): JsonResponse
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
                'total_ventes' => Ventes::count(),
                'total_client' => Ventes::distinct("nom_client")->count("nom_client"),
                'ventes_en_attente' => Ventes::EnAttente()->count(),
                'ventes_paye' => Ventes::Paye()->count(),
                'ventes_annule' => Ventes::Annule()->count(),
                'chiffres_affaire_total' => Ventes::Paye()->sum('prix_total'),
                'chiffres_affaire_mois' => Ventes::Paye()
                    ->whereMonth('created_at', Carbon::now('UTC')->month)
                    ->whereYear('created_at', Carbon::now('UTC')->year)
                    ->sum('prix_total'),
                'chiffre_affaires_jour' => Ventes::Paye()->whereDate('created_at', today())->sum('prix_total'),

                //Achat
                'total_achats' => Achats::count(),
                'total_achat_commande' => Achats::where('statut', Achats::ACHAT_COMMANDE)->count(),
                'total_achats_recu' => Achats::where('statut', Achats::ACHAT_REÇU)->count(),
                'total_prix_achats' => Achats::Reçu()->sum('prix_total'),

                //Stock
                'total_produits_stock' => Stock::where('actif', true)->count(),
                'total_stock_disponible' => Stock::StockDisponible()->count(),
                'total_stock_faible' => Stock::StockFaible()->count(),
                'total_valeur_stock' => Stock::StockDisponible()->sum('prix_vente')
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
                'error'=> $e->getMessage()
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
                'total_employe' => User::where('role', User::ROLE_EMPLOYE)->count(),
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
                'total_produits_stock' => DB::table('stock')->where('actif', true)->count(),
                'total_stock_faible' => Stock::StockFaible()->count(),

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
}
