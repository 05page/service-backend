/**  <?php

// namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\BelongsTo;
// use Illuminate\Database\Eloquent\Relations\HasMany;

// class Stock extends Model
// {
//     use HasFactory;

//     protected $table = 'stock';

//     protected $fillable = [
//         'service_id',
//         'fournisseur_id',
//         'quantite', // Note: Corrigé la faute de frappe de la migration
//         'nom_produit',
//         'actif',
//         'created_by'
//     ];

//     protected $casts = [
//         'actif' => 'boolean',
//         'quantite' => 'integer',
//     ];

//     // Constantes pour les seuils
//     const STOCK_CRITIQUE = 5;
//     const STOCK_FAIBLE = 10;

//     /**
//      * Relations
//      */

//     /**
//      * Relation avec l'utilisateur qui a créé l'entrée stock
//      */
//     public function creePar(): BelongsTo
//     {
//         return $this->belongsTo(User::class, 'created_by');
//     }

//     /**
//      * Relation avec le service lié à ce produit
//      */
//     public function service(): BelongsTo
//     {
//         return $this->belongsTo(Services::class, 'service_id');
//     }

//     /**
//      * Relation avec le fournisseur de ce produit
//      */
//     public function fournisseur(): BelongsTo
//     {
//         return $this->belongsTo(Fournisseurs::class, 'fournisseur_id');
//     }

//     /**
//      * Relation avec les achats (qui alimentent ce stock)
//      */
//     public function achats(): HasMany
//     {
//         return $this->hasMany(Achat::class, 'stock_id');
//     }

//     /**
//      * Scopes
//      */

//     /**
//      * Filtrer les produits actifs
//      */
//     public function scopeActif($query)
//     {
//         return $query->where('actif', true);
//     }

//     /**
//      * Filtrer par service
//      */
//     public function scopeParService($query, $serviceId)
//     {
//         return $query->where('service_id', $serviceId);
//     }

//     /**
//      * Filtrer par fournisseur
//      */
//     public function scopeParFournisseur($query, $fournisseurId)
//     {
//         return $query->where('fournisseur_id', $fournisseurId);
//     }

//     /**
//      * Produits avec stock critique
//      */
//     public function scopeStockCritique($query)
//     {
//         return $query->where('quantite', '<=', self::STOCK_CRITIQUE);
//     }

    /**
     * Produits avec stock faible
     */
    // public function scopeStockFaible($query)
    // {
    //     return $query->where('quantite', '<=', self::STOCK_FAIBLE)
    //                 ->where('quantite', '>', self::STOCK_CRITIQUE);
    // }

    // /**
    //  * Produits disponibles (actifs avec quantité > 0)
    //  */
    // public function scopeDisponible($query)
    // {
    //     return $query->where('actif', true)
    //                 ->where('quantite', '>', 0);
    // }

    // /**
    //  * Méthodes helper
    //  */

    // /**
    //  * Vérifier si le produit est actif
    //  */
    // public function isActif(): bool
    // {
    //     return $this->actif;
    // }

    // /**
    //  * Vérifier si le stock est disponible
    //  */
    // public function isDisponible(): bool
    // {
    //     return $this->actif && $this->quantite > 0;
    // }

    // /**
    //  * Vérifier si le stock est critique
    //  */
    // public function isStockCritique(): bool
    // {
    //     return $this->quantite <= self::STOCK_CRITIQUE;
    // }

    // /**
    //  * Vérifier si le stock est faible
    //  */
    // public function isStockFaible(): bool
    // {
    //     return $this->quantite <= self::STOCK_FAIBLE && $this->quantite > self::STOCK_CRITIQUE;
    // }

    // /**
    //  * Désactiver le produit
    //  */
    // public function desactiver(): bool
    // {
    //     $this->actif = false;
    //     return $this->save();
    // }

    // /**
    //  * Réactiver le produit
    //  */
    // public function reactiver(): bool
    // {
    //     $this->actif = true;
    //     return $this->save();
    // }

    // /**
    //  * Ajouter du stock (lors d'un achat)
    //  */
    // public function ajouterStock(int $quantite): bool
    // {
    //     if ($quantite <= 0) {
    //         return false;
    //     }

    //     $this->quantite += $quantite;
    //     return $this->save();
    // }

    // /**
    //  * Retirer du stock (lors d'une vente)
    //  */
    // public function retirerStock(int $quantite): bool
    // {
    //     if ($quantite <= 0 || $this->quantite < $quantite) {
    //         return false;
    //     }

    //     $this->quantite -= $quantite;
    //     return $this->save();
    // }

    // /**
    //  * Obtenir le statut du stock
    //  */
    // public function getStatutStock(): string
    // {
    //     if (!$this->actif) {
    //         return 'inactif';
    //     }

    //     if ($this->quantite == 0) {
    //         return 'épuisé';
    //     }

    //     if ($this->isStockCritique()) {
    //         return 'critique';
    //     }

    //     if ($this->isStockFaible()) {
    //         return 'faible';
    //     }

    //     return 'normal';
    // }

    /**
     * Obtenir un résumé du produit en stock
     */
    // public function getResume(): array
    // {
    //     return [
    //         'id' => $this->id,
    //         'nom_produit' => $this->nom_produit,
    //         'quantite' => $this->quantite,
    //         'statut_stock' => $this->getStatutStock(),
    //         'service' => $this->service?->nom_service,
    //         'fournisseur' => $this->fournisseur?->nom_fournisseurs,
    //         'cree_par' => $this->creePar?->fullname,
    //         'actif' => $this->actif,
    //         'created_at' => $this->created_at?->format('d/m/Y H:i')
    //     ];
    // }
//} 
