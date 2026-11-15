@extends('simulateur.layout')

{{-- Quand un numéro est saisi, la page se rafraîchit pour afficher les nouvelles demandes. --}}
@section('rafraichir')
    @if ($numero)
        <meta http-equiv="refresh" content="3">
    @endif
@endsection

@section('contenu')
    <div class="entete" style="background: linear-gradient(135deg, #1B1F2A, #3A4256);">
        <h1>Mobile money</h1>
        <p>{{ $numero ? 'Téléphone : '.$numero : 'Quel téléphone simuler ?' }}</p>
    </div>

    <div class="contenu">
        @if (session('message'))
            <div class="message">{{ session('message') }}</div>
        @endif

        @if (! $numero)
            <form method="POST" action="{{ route('simulateur.numero') }}">
                @csrf
                <label for="numero">Numéro du payeur</label>
                <input id="numero" name="numero" type="tel" placeholder="77 123 45 67" value="{{ old('numero') }}" autofocus>
                @error('numero') <div class="erreur">{{ $message }}</div> @enderror
                <button class="btn btn-principal" style="background:#1B1F2A">Ouvrir ce téléphone</button>
            </form>
            <p class="gris" style="margin-top:16px">
                Saisissez le numéro utilisé dans l’application pour payer. Les demandes envoyées à ce numéro s’afficheront ici.
            </p>
        @else
            @forelse ($demandes as $p)
                @php($op = $operateurs[$p->methode?->value] ?? ['nom' => 'Opérateur', 'couleur' => '#333'])
                <a class="carte" href="{{ route('simulateur.demande', $p->reference) }}">
                    <span class="pastille" style="background: {{ $op['couleur'] }}">{{ $op['nom'] }}</span>
                    <div class="montant">{{ number_format((float) $p->montant, 0, ',', ' ') }} F</div>
                    <div class="gris">Dara Campus Tech · abonnement</div>
                    <div class="gris">Reçue {{ $p->updated_at->diffForHumans() }} — appuyez pour répondre</div>
                </a>
            @empty
                <div class="vide">
                    <div style="font-size:40px">📭</div>
                    <p>Aucune demande de paiement en attente.</p>
                    <p class="gris">Lancez un paiement depuis l’application : il apparaîtra ici.</p>
                </div>
            @endforelse

            <form method="POST" action="{{ route('simulateur.numero') }}" style="text-align:center; margin-top:10px">
                @csrf
                <input type="hidden" name="numero" value="">
                <button class="lien">Changer de téléphone</button>
            </form>
        @endif
    </div>
@endsection
