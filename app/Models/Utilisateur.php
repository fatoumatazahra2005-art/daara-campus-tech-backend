<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/** Compte de connexion. Apprenant, Encadreur et Administrateur partagent son identifiant. */
class Utilisateur extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'utilisateurs';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'mot_de_passe_hash',
        'role_id',
        'statut_compte_id',
    ];

    protected $hidden = [
        'mot_de_passe_hash',
    ];

    /** Le mot de passe haché est stocké dans mot_de_passe_hash (et non dans « password »). */
    public function getAuthPassword(): string
    {
        return $this->mot_de_passe_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'mot_de_passe_hash';
    }

    public function aLeRole(string $role): bool
    {
        return $this->role?->value === $role;
    }

    public function estActif(): bool
    {
        return $this->statutCompte?->value === 'ACTIF';
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'role_id');
    }

    public function statutCompte(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'statut_compte_id');
    }

    public function apprenant(): HasOne
    {
        return $this->hasOne(Apprenant::class, 'id');
    }

    public function encadreur(): HasOne
    {
        return $this->hasOne(Encadreur::class, 'id');
    }

    public function administrateur(): HasOne
    {
        return $this->hasOne(Administrateur::class, 'id');
    }

    public function sujets(): HasMany
    {
        return $this->hasMany(SujetDiscussion::class, 'auteur_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessageForum::class, 'auteur_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'utilisateur_id');
    }

    public function signalements(): HasMany
    {
        return $this->hasMany(Signalement::class, 'utilisateur_id');
    }
}
