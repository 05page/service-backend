<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Mail\CommissionPayeeMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CommissionController extends Controller
{
    /**
     * Payer une commission individuelle
     */
    public function PayeCommission(Request $request, $commissionId): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seuls les admins peuvent utiliser cette fonctionnalité'
                ], 403);
            }

            $commission = Commission::with('user')->findOrFail($commissionId);

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

            $commission->load('paiements', 'user');

            DB::commit();

            if ($commission->user && $commission->user->email) {
                $this->sendCommissionEmail($commission);
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
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Commission introuvable'
            ], 404);
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

    /**
     * ✅ NOUVEAU : Payer plusieurs commissions groupées
     */
    public function payerCommissionsGroupees(Request $request): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seuls les admins peuvent utiliser cette fonctionnalité'
                ], 403);
            }

            $validated = $request->validate([
                'commission_ids' => 'required|array|min:1',
                'commission_ids.*' => 'required|exists:commissions,id',
            ]);

            DB::beginTransaction();

            // Récupérer toutes les commissions
            $commissions = Commission::with('user')
                ->whereIn('id', $validated['commission_ids'])
                ->where('etat_commission', 0) // Seulement les non payées
                ->get();

            if ($commissions->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune commission en attente trouvée parmi les IDs fournis'
                ], 404);
            }

            // Vérifier que toutes les commissions appartiennent au même utilisateur
            $userIds = $commissions->pluck('user_id')->unique();
            if ($userIds->count() > 1) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Les commissions doivent appartenir au même commissionnaire'
                ], 400);
            }

            $totalMontant = 0;
            $paiements = [];

            // Créer un paiement pour chaque commission
            foreach ($commissions as $commission) {
                $paiement = Paiement::create([
                    'payable_id'   => $commission->id,
                    'payable_type' => Commission::class,
                    'montant_verse' => $commission->commission_due,
                    'created_by'   => Auth::id(),
                ]);

                $commission->update([
                    'etat_commission' => true
                ]);

                $totalMontant += $commission->commission_due;
                $paiements[] = $paiement;
            }

            DB::commit();

            // Envoyer un email récapitulatif
            $user = $commissions->first()->user;
            if ($user && $user->email) {
                $this->sendCommissionGroupeeEmail($user, $commissions, $totalMontant);
            }

            return response()->json([
                'success' => true,
                'message' => "Paiement groupé effectué avec succès pour {$commissions->count()} commission(s)",
                'data' => [
                    'total_paye' => $totalMontant,
                    'nombre_commissions' => $commissions->count(),
                    'commissionnaire' => [
                        'id' => $user->id,
                        'fullname' => $user->fullname,
                        'email' => $user->email
                    ],
                    'commissions' => $commissions->map(function ($c) {
                        return [
                            'id' => $c->id,
                            'vente_reference' => $c->vente->reference ?? null,
                            'montant' => $c->commission_due
                        ];
                    })
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur paiement groupé commissions : ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du règlement groupé',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ NOUVEAU : Obtenir les commissions groupées par commissionnaire
     */
    public function getCommissionsParCommissionnaire(): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé'
                ], 403);
            }

            // Grouper les commissions non payées par commissionnaire
            $commissionsGroupees = Commission::with([
                'user:id,fullname,taux_commission,email',
                'vente:id,reference,prix_total'
            ])
                ->where('etat_commission', 0)
                ->get()
                ->groupBy('user_id')
                ->map(function ($commissions, $userId) {
                    $user = $commissions->first()->user;
                    return [
                        'commissionnaire' => [
                            'id' => $user->id,
                            'fullname' => $user->fullname,
                            'email' => $user->email,
                            'taux_commission' => $user->taux_commission
                        ],
                        'nombre_commissions' => $commissions->count(),
                        'total_du' => $commissions->sum('commission_due'),
                        'commissions' => $commissions->map(function ($c) {
                            return [
                                'id' => $c->id,
                                'vente_id' => $c->ventes_id,
                                'vente_reference' => $c->vente->reference ?? null,
                                'montant' => $c->commission_due,
                                'created_at' => $c->created_at->format('d/m/Y H:i')
                            ];
                        })
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $commissionsGroupees,
                'message' => 'Commissions groupées récupérées avec succès'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur récupération commissions groupées : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoyer l'email pour une commission individuelle
     */
    private function sendCommissionEmail(Commission $commission): void
    {
        try {
            Mail::to($commission->user->email)->send(new CommissionPayeeMail($commission));
            Log::info("Email de commission envoyé avec succès à {$commission->user->email}");
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi de l'email de commission à {$commission->user->email}: " . $e->getMessage());
        }
    }

    /**
     * ✅ NOUVEAU : Envoyer l'email pour paiement groupé
     */

    private function sendCommissionGroupeeEmail($user, $commissions, $totalMontant): void
    {
        try {
            // ✅ Réutilisation du même Mailable avec les commissions groupées
            Mail::to($user->email)->send(new CommissionPayeeMail($commissions, $totalMontant));

            Log::info("Email de commission groupée envoyé avec succès à {$user->email}", [
                'nombre_commissions' => $commissions->count(),
                'total_montant' => $totalMontant
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi de l'email groupé à {$user->email}: " . $e->getMessage());
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

            $commissions = Commission::with([
                'user:id,fullname,taux_commission,email',
                'vente:id,reference,prix_total'
            ])
                ->select('id', 'user_id', 'ventes_id', 'commission_due', 'etat_commission', 'created_at', 'updated_at')
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('Commissions récupérées:', ['count' => $commissions->count()]);

            $totalCommission = Commission::sum('commission_due');
            $commissionPayee = Commission::where('etat_commission', 1)->sum('commission_due');
            $commissionEnAttente = Commission::where('etat_commission', 0)->sum('commission_due');

            $nombreCommissionsPayees = Commission::where('etat_commission', 1)->count();
            $nombreCommissionsEnAttente = Commission::where('etat_commission', 0)->count();

            return response()->json([
                'success' => true,
                'data' => $commissions,
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

            if ($user->role === User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les administrateurs ne possèdent pas de commissions.'
                ], 403);
            }

            $commissions = Commission::with([
                'vente:id,reference,nom_client,prix_total,created_at'
            ])
                ->where('user_id', $user->id)
                ->select('id', 'ventes_id', 'commission_due', 'etat_commission', 'created_at')
                ->get();

            $totalCommission = Commission::where('user_id', $user->id)->sum('commission_due');
            $commissionPayee = Commission::payees()->where('user_id', $user->id)->sum('commission_due');
            $commissionEnAttente = Commission::attente()->where('user_id', $user->id)->sum('commission_due');

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
