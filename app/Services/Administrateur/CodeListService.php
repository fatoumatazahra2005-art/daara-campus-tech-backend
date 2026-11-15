<?php

namespace App\Services\Administrateur;

use App\Exceptions\RegleMetierException;
use App\Models\CodeList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Référentiel CodeList, administrable sans redéploiement (dictionnaire de données) :
 * - couple (type, value) unique ; l'administrateur ajoute des valeurs aux types existants ;
 * - une valeur est_systeme n'est ni modifiable, ni désactivable, ni supprimable ;
 * - une valeur déjà utilisée n'est jamais supprimée : elle est désactivée (active = false).
 */
class CodeListService
{
    /** @var list<array{table:string, colonne:string}>|null colonnes ayant une clé étrangère vers code_lists */
    private ?array $references = null;

    /** Les types existants, avec leur nombre de valeurs (actives et au total). */
    public function types(): Collection
    {
        return CodeList::query()
            ->select('type')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when active then 1 else 0 end) as actives')
            ->groupBy('type')
            ->orderBy('type')
            ->get()
            ->map(fn ($l) => [
                'type' => $l->type,
                'total' => (int) $l->total,
                'actives' => (int) $l->actives,
            ]);
    }

    /** Les valeurs d'un type (ou recherche dans tous les types), avec leur nombre d'utilisations. */
    public function lister(?string $type, ?string $recherche): Collection
    {
        $valeurs = CodeList::query()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($recherche, function ($q) use ($recherche) {
                $motif = '%'.trim($recherche).'%';
                $q->where(fn ($s) => $s->where('value', 'ilike', $motif)->orWhere('description', 'ilike', $motif));
            })
            ->orderBy('type')
            ->orderBy('ordre')
            ->orderBy('id')
            ->get();

        $utilisations = $this->utilisations($valeurs->pluck('id')->all());

        return $valeurs->map(fn (CodeList $c) => $this->presenter($c, $utilisations[$c->id] ?? 0));
    }

    /** Ajouter une valeur à un type existant. Une valeur créée par l'admin n'est jamais système. */
    public function ajouter(array $d): array
    {
        $type = $d['type'];
        $value = strtoupper(trim($d['value']));

        if (! CodeList::where('type', $type)->exists()) {
            throw new RegleMetierException('Type inconnu : seuls les types existants peuvent recevoir de nouvelles valeurs.', 422);
        }

        if (CodeList::where('type', $type)->where('value', $value)->exists()) {
            throw new RegleMetierException("La valeur {$value} existe déjà pour le type {$type}.", 409);
        }

        $code = new CodeList();
        $code->type = $type;
        $code->value = $value;
        $code->description = $d['description'] ?? null;
        $code->ordre = $d['ordre'] ?? ((int) CodeList::where('type', $type)->max('ordre') + 1);
        $code->active = true;
        $code->est_systeme = false;
        $code->date_creation = today();
        $code->save();

        return $this->presenter($code->fresh(), 0);
    }

    /** Modifier la description et l'ordre. La value ne change jamais : elle sert de clé dans le code et les données. */
    public function modifier(CodeList $code, array $d): array
    {
        $this->exigerNonSysteme($code, 'modifiée');

        $code->description = array_key_exists('description', $d) ? $d['description'] : $code->description;
        $code->ordre = $d['ordre'] ?? $code->ordre;
        $code->save();

        return $this->presenter($code->fresh(), $this->utilisations([$code->id])[$code->id] ?? 0);
    }

    /** CodeList.desactiver() : active = false, refusé si est_systeme. */
    public function desactiver(CodeList $code): array
    {
        $this->exigerNonSysteme($code, 'désactivée');

        $code->active = false;
        $code->save();

        return $this->presenter($code->fresh(), $this->utilisations([$code->id])[$code->id] ?? 0);
    }

    public function activer(CodeList $code): array
    {
        $this->exigerNonSysteme($code, 'modifiée');

        $code->active = true;
        $code->save();

        return $this->presenter($code->fresh(), $this->utilisations([$code->id])[$code->id] ?? 0);
    }

    /** Suppression : seulement une valeur non système et jamais utilisée. */
    public function supprimer(CodeList $code): void
    {
        $this->exigerNonSysteme($code, 'supprimée');

        $nb = $this->utilisations([$code->id])[$code->id] ?? 0;

        if ($nb > 0) {
            throw new RegleMetierException(
                "Cette valeur est utilisée {$nb} fois : elle ne peut pas être supprimée. Désactivez-la plutôt.",
                409
            );
        }

        $code->delete();
    }

    // ============== Outils internes ==============

    private function exigerNonSysteme(CodeList $code, string $action): void
    {
        if ($code->est_systeme) {
            throw new RegleMetierException(
                "La valeur {$code->value} est utilisée par les règles de gestion : elle ne peut pas être {$action}.",
                422
            );
        }
    }

    private function presenter(CodeList $c, int $utilisations): array
    {
        return [
            'id' => $c->id,
            'type' => $c->type,
            'value' => $c->value,
            'description' => $c->description,
            'ordre' => (int) $c->ordre,
            'active' => (bool) $c->active,
            'est_systeme' => (bool) $c->est_systeme,
            'date_creation' => $c->date_creation ? \Illuminate\Support\Carbon::parse($c->date_creation)->toDateString() : null,
            'utilisations' => $utilisations,
            'supprimable' => ! $c->est_systeme && $utilisations === 0,
        ];
    }

    /**
     * Nombre d'utilisations de chaque valeur, toutes tables confondues.
     *
     * @param  list<int>  $ids
     * @return array<int,int> id => nombre
     */
    private function utilisations(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $totaux = [];

        foreach ($this->references() as $ref) {
            $lignes = DB::table($ref['table'])
                ->select($ref['colonne'].' as code_id')
                ->selectRaw('count(*) as nb')
                ->whereIn($ref['colonne'], $ids)
                ->groupBy($ref['colonne'])
                ->get();

            foreach ($lignes as $l) {
                $totaux[(int) $l->code_id] = ($totaux[(int) $l->code_id] ?? 0) + (int) $l->nb;
            }
        }

        return $totaux;
    }

    /**
     * Colonnes ayant une clé étrangère vers code_lists (lues dans le catalogue PostgreSQL).
     *
     * @return list<array{table:string, colonne:string}>
     */
    private function references(): array
    {
        if ($this->references !== null) {
            return $this->references;
        }

        $table = (new CodeList())->getTable();

        $lignes = DB::select(<<<'SQL'
            SELECT kcu.table_name AS table_name, kcu.column_name AS column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
              ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = current_schema()
              AND ccu.table_name = ?
        SQL, [$table]);

        return $this->references = collect($lignes)
            ->map(fn ($l) => ['table' => $l->table_name, 'colonne' => $l->column_name])
            ->unique(fn ($r) => $r['table'].'.'.$r['colonne'])
            ->values()
            ->all();
    }
}
