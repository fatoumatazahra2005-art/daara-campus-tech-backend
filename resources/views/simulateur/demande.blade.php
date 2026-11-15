@extends('simulateur.layout')

@section('contenu')
    <div class="entete" style="background: {{ $operateur['couleur'] }};">
        <h1>{{ $operateur['nom'] }}</h1>
        <p>Demande de paiement</p>
    </div>

    <div class="contenu">
        <p class="gris" style="margin-top:0">Dara Campus Tech vous demande :</p>
        <div class="montant">{{ number_format((float) $paiement->montant, 0, ',', ' ') }} F</div>
        <p class="gris">Échéance n° {{ $paiement->numero_echeance }} · réf. {{ $paiement->reference }}</p>

        <form method="POST" action="{{ route('simulateur.repondre', $paiement->reference) }}" style="margin-top:22px">
            @csrf
            <label for="code">Code secret</label>
            <input id="code" class="code" name="code" type="password" inputmode="numeric" maxlength="4" placeholder="••••" autofocus>
            @error('code') <div class="erreur">{{ $message }}</div> @enderror

            <button class="btn btn-principal" name="action" value="valider" style="background: {{ $operateur['couleur'] }}">
                Confirmer le paiement
            </button>
            <button class="btn btn-secondaire" name="action" value="refuser">Refuser</button>
        </form>

        <p style="text-align:center; margin-top:16px">
            <a class="gris" href="{{ route('simulateur.index') }}">← Retour</a>
        </p>
    </div>
@endsection
