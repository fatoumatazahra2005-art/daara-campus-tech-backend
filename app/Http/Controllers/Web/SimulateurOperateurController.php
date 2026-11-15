<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Services\Apprenant\PaiementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Simulateur d'opérateur mobile money (Wave, Orange Money, Free Money).
 * Imite le téléphone du payeur : il voit les demandes de paiement envoyées à son numéro
 * et les valide avec son code secret, ou les refuse. La réponse suit le même chemin
 * que le callback d'un vrai opérateur (PaiementService::confirmer).
 * Disponible uniquement si PAIEMENT_SIMULATION=true.
 */
class SimulateurOperateurController extends Controller
{
    public const OPERATEURS = [
        'WAVE' => ['nom' => 'Wave', 'couleur' => '#1DC8FF'],
        'ORANGE_MONEY' => ['nom' => 'Orange Money', 'couleur' => '#FF7900'],
        'FREE_MONEY' => ['nom' => 'Free Money', 'couleur' => '#CD1E25'],
    ];

    public function __construct(private PaiementService $paiements)
    {
    }

    /** Écran d'accueil du téléphone : saisie du numéro, puis liste des demandes reçues. */
    public function index(Request $request): View
    {
        $this->verifierActif();

        $numero = $request->session()->get('simulateur_numero');

        return view('simulateur.liste', [
            'numero' => $numero,
            'demandes' => $numero ? $this->demandesPour($numero) : collect(),
            'operateurs' => self::OPERATEURS,
        ]);
    }

    /** « Allumer » le téléphone d'un numéro, ou l'éteindre (numéro vide). */
    public function changerNumero(Request $request): RedirectResponse
    {
        $this->verifierActif();

        $saisi = trim((string) $request->input('numero'));

        if ($saisi === '') {
            $request->session()->forget('simulateur_numero');

            return redirect()->route('simulateur.index');
        }

        $numero = self::normaliser($saisi);

        if (! $numero) {
            return back()
                ->withErrors(['numero' => 'Numéro sénégalais invalide (ex. 77 123 45 67).'])
                ->withInput();
        }

        $request->session()->put('simulateur_numero', $numero);

        return redirect()->route('simulateur.index');
    }

    /** Écran de confirmation d'une demande (code secret). */
    public function demande(Request $request, string $reference): View|RedirectResponse
    {
        $this->verifierActif();

        $paiement = $this->demandeDuTelephone($request, $reference);

        if (! $paiement) {
            return redirect()
                ->route('simulateur.index')
                ->with('message', 'Cette demande n’est plus en attente.');
        }

        return view('simulateur.demande', [
            'paiement' => $paiement,
            'operateur' => self::OPERATEURS[$paiement->methode?->value] ?? ['nom' => 'Opérateur', 'couleur' => '#333'],
        ]);
    }

    /** Réponse du payeur : même traitement que le callback d'un vrai opérateur. */
    public function repondre(Request $request, string $reference): RedirectResponse
    {
        $this->verifierActif();

        $data = $request->validate([
            'action' => ['required', 'in:valider,refuser'],
            'code' => ['required_if:action,valider', 'nullable', 'digits:4'],
        ], [
            'code.required_if' => 'Saisissez votre code secret.',
            'code.digits' => 'Le code secret contient 4 chiffres.',
        ]);

        $paiement = $this->demandeDuTelephone($request, $reference);

        if (! $paiement) {
            return redirect()
                ->route('simulateur.index')
                ->with('message', 'Cette demande n’est plus en attente.');
        }

        $succes = $data['action'] === 'valider';
        $this->paiements->confirmer($paiement->reference, $succes);

        return redirect()->route('simulateur.index')->with(
            'message',
            $succes
                ? 'Paiement de '.number_format((float) $paiement->montant, 0, ',', ' ').' F effectué.'
                : 'Demande de paiement refusée.'
        );
    }

    // ============== Outils internes ==============

    private function verifierActif(): void
    {
        abort_unless(config('paiement.simulation'), 404);
    }

    /** Demandes EN_ATTENTE envoyées à ce numéro (enregistré en +221… ou en 9 chiffres). */
    private function demandesPour(string $numero): Collection
    {
        $local = substr($numero, -9);

        return Paiement::with('methode')
            ->whereHas('statut', fn ($q) => $q->where('value', 'EN_ATTENTE'))
            ->whereNotNull('reference')
            ->whereIn('numero_payeur', [$numero, $local, '221'.$local, '00221'.$local])
            ->orderByDesc('id')
            ->get();
    }

    /** Une demande ne peut être traitée que depuis le téléphone du numéro payeur. */
    private function demandeDuTelephone(Request $request, string $reference): ?Paiement
    {
        $numero = $request->session()->get('simulateur_numero');

        return $numero
            ? $this->demandesPour($numero)->firstWhere('reference', $reference)
            : null;
    }

    /** « 77 123 45 67 », « 771234567 » ou « +221 77… » → « +221771234567 » ; null si invalide. */
    private static function normaliser(string $saisi): ?string
    {
        $n = preg_replace('/[\s.\-()]/', '', $saisi);
        $n = preg_replace('/^(\+221|00221|221)/', '', $n);

        if (! preg_match('/^7[05678]\d{7}$/', $n)) {
            return null;
        }

        return '+221'.$n;
    }
}
