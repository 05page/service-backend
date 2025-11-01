<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Achats;
use App\Models\Permissions;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
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

    public function addStock(Request $request): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action."
                ], 403);
            }

            $validate = $request->validate([
                'achat_id' => 'required|exists:achats,id',
                'categorie' => 'nullable|string|max:300',
                'quantite' => 'required|integer|min:1',
                'quantite_min' => 'required|integer|min:1',
                'prix_vente' => 'required|numeric|min:0',
                'description' => 'nullable|string|max:300',
            ]);

            // Récupérer l'achat
            $achat = Achats::with('fournisseur')
                ->where('id', $validate['achat_id'])
                ->where('active', 1)
                ->whereIn('statut', [Achats::ACHAT_PAYE, Achats::ACHAT_REÇU])
                ->doesntHave('stock')
                ->first();

            if (!$achat) {
                return response()->json([
                    'success' => false,
                    'message' => "Cet achat n'est pas disponible (pas payé ou déjà lié à un stock).",
                ], 404);
            }

            DB::beginTransaction();

            $stock = Stock::create([
                'achat_id' => $validate['achat_id'],
                'categorie' => $validate['categorie'] ?? null,
                'quantite' => $validate['quantite'],
                'quantite_min' => $validate['quantite_min'],
                'entre_stock' => $validate['quantite'],
                'sortie_stock' => 0,
                'prix_vente' => $validate['prix_vente'],
                'description' => $validate['description'] ?? null,
                'actif' => true,
                'created_by' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Le stock a été ajouté avec succès",
                'data' => $stock->load('achat')
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur de validation",
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la création du stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ NOUVEAU : Obtenir les stocks en rupture ou faibles pour renouvellement
    public function stocksARenouveler(): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé."
                ], 403);
            }

            // Récupérer les stocks en rupture ou faibles
            $stocks = Stock::with(['achat:id,nom_service,fournisseur_id', 'achat.fournisseur:id,nom_fournisseurs'])
                ->where(function ($query) {
                    $query->where('quantite', '<=', Stock::STOCK_FAIBLE)
                          ->orWhere('quantite', '=', Stock::STOCK_RUPTURE);
                })
                ->where('actif', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stocks,
                'message' => "Stocks à renouveler récupérés avec succès"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showStocks(Request $request): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action."
                ], 403);
            }

            $query = Stock::with(['creePar:id,fullname,email,role', 'achat:id,nom_service,prix_unitaire'])
                ->select(
                    'id',
                    'achat_id',
                    'code_produit',
                    'categorie',
                    'quantite',
                    'quantite_min',
                    'entre_stock',
                    'sortie_stock',
                    'prix_vente',
                    'statut',
                    'actif',
                    'created_by',
                    'created_at'
                );

            // Filtrage par statut si fourni
            if ($request->filled('statut')) {
                switch ($request->statut) {
                    case 'disponible':
                        $query->stockDisponible();
                        break;
                    case 'faible':
                        $query->stockFaible();
                        break;
                    case 'rupture':
                        $query->rupture();
                        break;
                }
            }

            $stocks = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $stocks,
                'message' => 'Stocks récupérés avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération des stocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStock(Request $request, $id): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action."
                ], 403);
            }

            $validated = $request->validate([
                'achat_id' => 'sometimes|required|exists:achats,id',
                'categorie' => 'sometimes|nullable|string|max:300',
                'quantite' => 'sometimes|required|integer|min:0',
                'quantite_min' => 'sometimes|required|integer|min:0',
                'prix_vente' => 'sometimes|required|numeric|min:0',
                'description' => 'sometimes|nullable|string|max:300',
            ]);

            DB::beginTransaction();

            $stock = Stock::findOrFail($id);
            $achat = $stock->achat;

            // ✅ Vérifier que la quantité ≤ quantité de l'achat
            if (isset($validated['quantite']) && $achat) {
                if ($validated['quantite'] > $achat->quantite) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Impossible de définir une quantité de stock supérieure à la quantité achetée ({$achat->quantite})."
                    ], 400);
                }
            }

            // Mise à jour du stock
            $stock->update($validated);

            // Mettre à jour le statut automatiquement
            $stock->updateStatut();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock mis à jour avec succès',
                'data' => $stock->fresh()->load('achat')
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la mise à jour du stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action."
                ], 403);
            }

            DB::beginTransaction();
            
            $stock = Stock::findOrFail($id);
            
            // Vérifier si le stock est utilisé dans des ventes
            if ($stock->sortie_stock > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer ce stock car des articles ont déjà été vendus.'
                ], 400);
            }

            $stock->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Stock supprimé avec succès."
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteAll(): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action."
                ], 403);
            }

            DB::beginTransaction();
            
            // Supprimer uniquement les stocks non utilisés (pas de ventes)
            $stocksNonUtilises = Stock::where('sortie_stock', '=', 0)->get();
            
            foreach ($stocksNonUtilises as $stock) {
                $stock->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Stocks non utilisés supprimés avec succès."
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la suppression des stocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function statStock(): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            $statStock = [
                'total_produits_stock' => Stock::where('created_by', $userId)->count(),
                'total_stock_disponible' => Stock::where('created_by', $userId)->stockDisponible()->count(),
                'total_stock_faible' => Stock::where('created_by', $userId)->stockFaible()->count(),
                'total_stock_rupture' => Stock::where('created_by', $userId)->rupture()->count(),
                'total_valeur_stock' => Stock::where('created_by', $userId)
                    ->stockDisponible()
                    ->sum(DB::raw('prix_vente * quantite')),
            ];

            return response()->json([
                'success' => true,
                'data' => $statStock,
                'message' => 'Vos statistiques ont été récupérées avec succès'
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