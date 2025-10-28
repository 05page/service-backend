<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Paiement;
use App\Models\User;
use Dom\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    //
    public function PayeCommission(Request $request, $commissionId): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seuls les admins peuvent utiliser cette fonctionnalité'
                ], 403);
            }
            $validated = $request->validate([
                'montant_verse' => 'required|numeric|min:1|exists:commissions,commission_due'
            ]);
            $commission = Commission::find($commissionId);
            DB::beginTransaction();
            $paiement = Paiement::create([
                'payable_id'   => $commission->id,
                'payable_type' => Commission::class,
                'montant_verse' => $validated['montant_verse'],
                'created_by'   => Auth::id(),
            ]);
            $commission->update([
                'etat_commission' => true
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Commission payé avec succès",
                'data' => [
                    'commission' => $commission->load('paiements'),
                    'paiement' => $paiement
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du règlement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function showCommission(): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seuls les admins peuvent utiliser cette fonctionnalité'
                ], 403);
            }

            $commission = Commission::with([
                'user:id,fullname,taux_commission',
                'vente:id,prix_total'
            ])->select('id', 'user_id', 'ventes_id', 'commission_due', 'etat_commission')->get();
            return response()->json([
                'success' => true,
                'data' => $commission,
                'message' => "Commission récupérée"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la récupération des ventes",
                'errors'  => $e->getMessage()
            ], 500);
        }
    }

    public function mesCommissions(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Vérifier que l'utilisateur est bien un employé (pas un admin)
            if ($user->role === User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les administrateurs ne possèdent pas de commissions.'
                ], 403);
            }

            // Charger les commissions du user connecté avec les infos de la vente liée
            $commissions = Commission::with([
                'vente:id,reference,nom_client,prix_total,created_at'
            ])
                ->where('user_id', $user->id)
                ->select('id', 'ventes_id', 'commission_due', 'etat_commission', 'created_at')
                ->get();

            // Calcul des totaux
            $totalCommission = Commission::where('user_id', $user->id)->sum('commission_due');
            $commissionPayee = Commission::payees()->where('user_id', $user->id)->sum('commission_due');
            $commissionEnAttente = Commission::attente()->where('user_id', $user->id)->sum('commission_due');

            // Structure de réponse
            return response()->json([
                'success' => true,
                'resume' => [
                    'total_commission' => $totalCommission,
                    'commission_payee' => $commissionPayee,
                    'commission_en_attente' => $commissionEnAttente,
                ],
                'liste' => $commissions->map(function ($commission) {
                    return [
                        'reference_vente' => $commission->vente->reference ?? null,
                        'nom_client' => $commission->vente->nom_client ?? null,
                        'montant_commission' => $commission->commission_due,
                        'etat_commission' => $commission->etat_commission == 1 ? 'Payée' : 'En attente',
                        'date_versement' => $commission->created_at->format('Y-m-d H:i'),
                    ];
                }),
                'message' => 'Vos commissions ont été récupérées avec succès.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commissions.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
