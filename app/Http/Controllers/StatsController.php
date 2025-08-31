<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Permissions;
use App\Models\User;
use App\Models\Ventes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StatsController extends Controller
{
    //
    private function verifierPermissions()
    {
        $user = Auth::user();
        if ($user->role !== User::ROLE_ADMIN) {
            /** @var User $user */
            $hasPermission = $user->permissions()
                ->where('module', Permissions::MODULE_VENTES)
                ->where('active', true)->exists();
            if (!$hasPermission) {
                return false;
            }
        }
        return true;
    }

    // public function statistiquesEmploye(): JsonResponse
    // {

    //     try {
    //         if (!$this->verifierPermissions()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'accès réfusé'
    //             ], 403);
    //         }
    //         $stats = [
    //             'total_ventes' => Ventes::count(),
    //             'ventes_en_attente' => Ventes::EnAttente()->count(),
    //             'ventes_paye' => Ventes::Paye()->count(),
    //             'ventes_annule' => Ventes::Annule()->count()
    //         ];

    //         return response()->json([
    //             'success' => true,
    //             'data' => $stats,
    //             'message' => 'Statistiques récupérées avec succès'
    //         ], 201);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur survenue lors de la récupération des statistiques',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function myStats(int $userId): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => 'accès réfusé'
                ], 403);
            }
            $myStats = [
                'total_ventes' => Ventes::where('created_by', $userId)->count(),
                'ventes_en_attente' => Ventes::where('created_by', $userId)->EnAttente()->count(),
                'ventes_paye' => Ventes::where('created_by', $userId)->Paye()->count(),
                'ventes_annule' => Ventes::where('created_by', $userId)->Annule()->count(),
                'chiffres_affaire_total' => Ventes::where('created_by', $userId)->Paye()->sum('prix_total'),
            ];

            return response()->json([
                'success' => true,
                'data' => $myStats,
                'message' => 'Vos statistiques ont été récupérées avec succès'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
                'ventes_en_attente' => Ventes::EnAttente()->count(),
                'ventes_paye' => Ventes::Paye()->count(),
                'ventes_annule' => Ventes::Annule()->count(),
                'chiffres_affaire_total' => Ventes::Paye()->sum('prix_total'),
                'chiffres_affaire_mois' => Ventes::Paye()->whereMonth('created_at', now()->month())
                    ->whereYear('created_at', now()->year())->sum('prix_total'),
                'chiffre_affaires_jour' => Ventes::Paye()->whereDate('created_at', today())->sum('prix_total')
            ];
            return response()->json([
                'success' => true,
                'data' => $allStats,
                'message' => 'Statistiques récupérées avec succès'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération des statistiques'
            ], 500);
        }
    }
}
