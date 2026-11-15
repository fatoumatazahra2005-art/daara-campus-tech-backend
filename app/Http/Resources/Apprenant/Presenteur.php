<?php

namespace App\Http\Resources\Apprenant;

use App\Models\Abonnement;
use App\Models\Certificat;
use App\Models\Chapitre;
use App\Models\FormuleAbonnement;
use App\Models\InscriptionCours;
use App\Models\InscriptionParcours;
use App\Models\Paiement;
use App\Models\Parcours;
use App\Models\PeriodeAbonnement;
use App\Models\RenduProjet;
use App\Models\TentativeQuiz;
use App\Services\Apprenant\AccesService;

/**
 * Met en forme les modèles pour l'API : les valeurs de CodeList sortent en texte (« ACTIF »),
 * jamais en identifiant. Les bonnes réponses des quiz n'apparaissent qu'après la tentative.
 */
class Presenteur
{
    public static function formule(FormuleAbonnement $f): array
    {
        return [
            'id' => $f->id,
            'libelle' => $f->libelle,
            'description' => $f->description,
            'periodicite' => $f->periodicite?->value,
            'duree_mois' => $f->dureeMois(),
            'prix' => (int) $f->prix,
            'nb_echeances_max' => (int) $f->nb_echeances_max,
            'quota_hors_ligne' => (int) $f->quota_hors_ligne,
            'jours_essai' => (int) $f->jours_essai,
            'limite_chapitres_essai' => $f->limite_chapitres_essai,
        ];
    }

    public static function periode(PeriodeAbonnement $p): array
    {
        return [
            'id' => $p->id,
            'numero' => (int) $p->numero,
            'date_debut' => $p->date_debut?->toDateString(),
            'date_fin' => $p->date_fin?->toDateString(),
            'prix_paye' => (int) $p->prix_paye,
            'nb_echeances' => (int) $p->nb_echeances,
            'est_essai' => (bool) $p->est_essai,
        ];
    }

    public static function abonnement(Abonnement $a): array
    {
        $acces = app(AccesService::class);
        $a->loadMissing('statut', 'formule.periodicite', 'periodes');
        $courante = $acces->periodeEnCours($a);

        return [
            'id' => $a->id,
            'statut' => $a->statut?->value,
            'donne_acces' => $acces->donneAcces($a),
            'credits_hors_ligne' => (int) $a->credits_hors_ligne,
            'date_resiliation' => $a->date_resiliation?->toDateString(),
            'formule' => $a->formule ? self::formule($a->formule) : null,
            'periode_en_cours' => $courante ? self::periode($courante) : null,
            'periodes' => $a->periodes->map(fn ($p) => self::periode($p))->values()->all(),
        ];
    }

    public static function paiement(Paiement $p): array
    {
        $p->loadMissing('statut', 'methode');

        return [
            'id' => $p->id,
            'montant' => (int) $p->montant,
            'numero_echeance' => (int) $p->numero_echeance,
            'date_echeance' => $p->date_echeance?->toDateString(),
            'date_paiement' => $p->date_paiement?->toIso8601String(),
            'methode' => $p->methode?->value,
            'statut' => $p->statut?->value,
            'reference' => $p->reference,
            'periode_id' => $p->periode_id,
        ];
    }

    public static function inscriptionCours(InscriptionCours $i): array
    {
        $i->loadMissing('statut', 'cours', 'certificat');

        return [
            'id' => $i->id,
            'cours' => ['id' => $i->cours_id, 'titre' => $i->cours?->titre],
            'statut' => $i->statut?->value,
            'progression' => (float) $i->progression,
            'date_inscription' => $i->date_inscription?->toDateString(),
            'certificat' => $i->certificat ? self::certificat($i->certificat) : null,
        ];
    }

    public static function inscriptionParcours(InscriptionParcours $i): array
    {
        $i->loadMissing('statut', 'parcours');

        return [
            'id' => $i->id,
            'parcours' => ['id' => $i->parcours_id, 'titre' => $i->parcours?->titre],
            'statut' => $i->statut?->value,
            'progression' => (float) $i->progression,
            'date_inscription' => $i->date_inscription?->toDateString(),
        ];
    }

    public static function parcours(Parcours $p): array
    {
        $cours = $p->cours
            ->filter(fn ($c) => ! $c->relationLoaded('statut') || $c->statut?->value === 'PUBLIE')
            ->sortBy(fn ($c) => (int) $c->pivot->ordre);

        $techno = fn ($c) => $c->technologie
            ? ['id' => $c->technologie->id, 'nom' => $c->technologie->nom, 'icone' => $c->technologie->icone]
            : null;

        return [
            'id' => $p->id,
            'titre' => $p->titre,
            'description' => $p->description,
            'image' => $p->image ? \Illuminate\Support\Facades\Storage::disk('public')->url($p->image) : null,
            'cours' => $cours->map(fn ($c) => [
                'id' => $c->id,
                'titre' => $c->titre,
                'ordre' => (int) $c->pivot->ordre,
                'obligatoire' => (bool) $c->pivot->obligatoire,
                'technologie' => $techno($c),
            ])->values()->all(),
            // Technologies du parcours, sans doublon, dans l'ordre des cours
            'technologies' => $cours->map($techno)->filter()->unique('id')->values()->all(),
            'projets' => $p->relationLoaded('projets')
                ? $p->projets->map(fn ($pr) => [
                    'id' => $pr->id,
                    'titre' => $pr->titre,
                    'delai_jours' => (int) $pr->delai_jours,
                ])->values()->all()
                : [],
        ];
    }

    /** Chapitre avec ses blocs (TEXTE, IMAGE, CODE). */
    public static function chapitre(Chapitre $c): array
    {
        return [
            'id' => $c->id,
            'titre' => $c->titre,
            'objectif' => $c->objectif,
            'prerequis' => $c->prerequis,
            'duree_estimee' => $c->duree_estimee,
            'xp_recompense' => $c->xp_recompense,
            'blocs' => $c->blocs->map(fn ($b) => self::bloc($b))->values()->all(),
        ];
    }

    public static function bloc($b): array
    {
        $base = ['id' => $b->id, 'ordre' => (int) $b->ordre, 'type' => $b->type_bloc];

        return $base + match ($b->type_bloc) {
            'TEXTE' => ['contenu' => $b->texte?->contenu],
            'IMAGE' => ['url_image' => $b->image?->url_image, 'alt' => $b->image?->alt],
            'CODE' => ['code' => $b->code?->code, 'langage' => $b->code?->langage],
            default => [],
        };
    }

    public static function certificat(Certificat $c): array
    {
        return [
            'id' => $c->id,
            'date_obtention' => $c->date_obtention?->toDateString(),
            'code_verification' => $c->code_verification,
            'nom_apprenant' => $c->nom_apprenant,
            'titre_cours' => $c->titre_cours,
            'url_pdf' => $c->url_pdf,
        ];
    }

    public static function tentative(TentativeQuiz $t): array
    {
        $t->loadMissing('statut');

        return [
            'id' => $t->id,
            'quiz_id' => $t->quiz_id,
            'statut' => $t->statut?->value,
            'score' => $t->score !== null ? (float) $t->score : null,
            'date_debut' => $t->date_debut?->toIso8601String(),
            'date_fin' => $t->date_fin?->toIso8601String(),
        ];
    }

    public static function rendu(RenduProjet $r): array
    {
        $r->loadMissing('statut', 'projet');

        return [
            'id' => $r->id,
            'projet' => ['id' => $r->projet_id, 'titre' => $r->projet?->titre, 'consigne' => $r->projet?->consigne],
            'statut' => $r->statut?->value,
            'date_attribution' => $r->date_attribution?->toDateString(),
            'date_limite' => $r->date_limite?->toDateString(),
            'date_rendu' => $r->date_rendu?->toIso8601String(),
            'lien_github' => $r->lien_github,
            'note' => $r->note !== null ? (float) $r->note : null,
            'commentaire' => $r->commentaire,
            'note_minimale' => $r->projet ? (float) $r->projet->note_minimale : null,
        ];
    }
}
