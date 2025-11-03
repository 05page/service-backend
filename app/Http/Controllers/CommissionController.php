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
use App\Mail\CommissionPayeeMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


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

            // ✅ CORRECTION : utiliser 'user' au lieu de 'user_id'
            $commission = Commission::with('user')->findOrFail($commissionId);

            // ✅ Validation corrigée
            $validated = $request->validate([
                'montant_verse' => 'required|numeric|min:1|max:' . $commission->commission_due
            ]);

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

            // ✅ Recharger la commission avec toutes les relations
            $commission->load('paiements', 'user');

            DB::commit();

            // ✅ CORRECTION : utiliser 'user' au lieu de 'personnel'
            if ($commission->user && $commission->user->email) {
                try {
                    Mail::to($commission->user->email)
                        ->send(new CommissionPayeeMail($commission));

                    Log::info('Mail de commission envoyé à : ' . $commission->user->email);
                } catch (\Exception $mailException) {
                    Log::error('Erreur envoi mail commission : ' . $mailException->getMessage());
                }
            } else {
                Log::warning('Impossible d\'envoyer le mail : user ou email manquant pour la commission #' . $commissionId);
            }

            return response()->json([
                'success' => true,
                'message' => "Commission payée avec succès. Un mail a été envoyé au personnel concerné.",
                'data' => [
                    'commission' => $commission,
                    'paiement' => $paiement
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur paiement commission : ' . $e->getMessage());

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

            // Récupérer toutes les commissions avec leurs relations
            $commissions = Commission::with([
                'user:id,fullname,taux_commission,email',
                'vente:id,reference,prix_total'
            ])
                ->select('id', 'user_id', 'ventes_id', 'commission_due', 'etat_commission', 'created_at', 'updated_at')
                ->orderBy('created_at', 'desc')
                ->get();

            // ✅ IMPORTANT : Vérifier que $commissions n'est pas vide
            Log::info('Commissions récupérées:', ['count' => $commissions->count()]);

            // Calculs statistiques
            $totalCommission = Commission::sum('commission_due');
            $commissionPayee = Commission::where('etat_commission', 1)->sum('commission_due');
            $commissionEnAttente = Commission::where('etat_commission', 0)->sum('commission_due');

            // Nombre de commissions par statut
            $nombreCommissionsPayees = Commission::where('etat_commission', 1)->count();
            $nombreCommissionsEnAttente = Commission::where('etat_commission', 0)->count();

            return response()->json([
                'success' => true,
                'data' => $commissions, // ✅ Les commissions sont ici
                'resume' => [
                    'total_commission' => (float) $totalCommission,
                    'commission_payee' => (float) $commissionPayee,
                    'commission_en_attente' => (float) $commissionEnAttente,
                    'nombre_commissions_payees' => $nombreCommissionsPayees,
                    'nombre_commissions_en_attente' => $nombreCommissionsEnAttente,
                    'nombre_total_commissions' => $commissions->count(),
                ],
                'message' => "Commissions récupérées avec succès"
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur récupération commissions : ' . $e->getMessage());
            Log::error('Stack trace : ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la récupération des commissions",
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
