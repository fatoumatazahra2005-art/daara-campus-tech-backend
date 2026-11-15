<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vérification de certificat · Dara Campus Tech</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #F5F3FA; color: #1F1535;
            display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 16px; }
        .carte { background: #fff; border-radius: 20px; padding: 32px; max-width: 460px; width: 100%;
            box-shadow: 0 10px 30px rgba(75, 42, 140, .12); text-align: center; }
        .ok { color: #1FA971; } .ko { color: #E5484D; }
        h1 { font-size: 22px; margin: 12px 0 20px; }
        dl { text-align: left; margin: 0; }
        dt { font-size: 13px; color: #6B6280; margin-top: 12px; }
        dd { margin: 2px 0 0; font-weight: 700; }
        .marque { font-size: 12px; letter-spacing: 4px; color: #4B2A8C; font-weight: 700; }
    </style>
</head>
<body>
<div class="carte">
    <div class="marque">DARA CAMPUS TECH</div>
    @if ($c)
        <h1 class="ok">✔ Certificat authentique</h1>
        <dl>
            <dt>Titulaire</dt><dd>{{ $c->nom_apprenant }}</dd>
            <dt>Cours</dt><dd>{{ $c->titre_cours }}</dd>
            <dt>Date d'obtention</dt>
            <dd>{{ \Carbon\Carbon::parse($c->date_obtention)->locale('fr')->isoFormat('D MMMM YYYY') }}</dd>
            <dt>Code</dt><dd>{{ $c->code_verification }}</dd>
        </dl>
    @else
        <h1 class="ko">✘ Certificat introuvable</h1>
        <p>Aucun certificat ne correspond au code « {{ $code }} ».</p>
    @endif
</div>
</body>
</html>
