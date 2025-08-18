<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'fullname',
        'email',
        'role',
        'active',
        'telephone',
        'adresse',
        'password'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'active' => 'boolean',
        'two_factor_recovery_codes' => 'array',
    ];

    const ROLE_ADMIN = 'admin';
    const ROLE_EMPLOYE = 'employe';
    const ROLE_CLIENT = 'client';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (!empty($user->password)) {
                $user->password = bcrypt($user->password);
            }
        });

        static::updating(function ($user) {
            if ($user->isDirty('password') && !empty($user->password)) {
                $user->password = bcrypt($user->password);
            }
        });
    }

    // ✅ AJOUTER CETTE MÉTHODE
    /**
     * Enregistrer la connexion
     */
    public function recordLogin()
    {
        $this->update([
            'last_login_at' => now(),
        ]);
    }

    // ✅ AJOUTER CES MÉTHODES HELPER AUSSI
    /**
     * Vérifier si l'utilisateur est admin
     */
    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Vérifier si l'utilisateur est employé
     */
    // public function isEmploye()
    // {
    //     return $this->role === self::ROLE_EMPLOYE;
    // }

    /**
     * Vérifier si l'utilisateur est client
     */
    // public function isClient()
    // {
    //     return $this->role === self::ROLE_CLIENT;
    // }

    /**
     * Vérifier si l'utilisateur est actif
     */
    public function isActif()
    {
        return $this->active;
    }
}