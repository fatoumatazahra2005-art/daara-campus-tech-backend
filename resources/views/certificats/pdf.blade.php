<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #1F1535; }
        .cadre { position: absolute; top: 22px; left: 22px; right: 22px; bottom: 22px; border: 7px solid #4B2A8C; }
        .interieur { position: absolute; top: 10px; left: 10px; right: 10px; bottom: 10px;
            border: 2px solid #F7881F; text-align: center; }
        .marque { margin-top: 42px; font-size: 15px; letter-spacing: 6px; color: #4B2A8C; font-weight: bold; }
        .titre { margin-top: 26px; font-size: 40px; font-weight: bold; color: #4B2A8C; letter-spacing: 2px; }
        .trait { width: 120px; height: 4px; background: #F7881F; margin: 16px auto 0; }
        .texte { margin-top: 28px; font-size: 15px; color: #6B6280; }
        .nom { margin-top: 12px; font-size: 34px; font-weight: bold; }
        .cours { margin-top: 12px; font-size: 22px; font-weight: bold; color: #4B2A8C; }
        .pied { position: absolute; bottom: 36px; left: 60px; right: 60px; width: auto; }
        .pied td { width: 50%; font-size: 12px; color: #6B6280; vertical-align: top; }
        .pied b { font-size: 14px; color: #1F1535; }
        .gauche { text-align: left; }
        .droite { text-align: right; }
    </style>
</head>
<body>
<div class="cadre">
    <div class="interieur">
        <div class="marque">DARA CAMPUS TECH</div>
        <div class="titre">CERTIFICAT DE RÉUSSITE</div>
        <div class="trait"></div>

        <div class="texte">Ce certificat atteste que</div>
        <div class="nom">{{ $c->nom_apprenant }}</div>
        <div class="texte">a suivi et terminé avec succès le cours</div>
        <div class="cours">« {{ $c->titre_cours }} »</div>

        <table class="pied">
            <tr>
                <td class="gauche">
                    Délivré le<br>
                    <b>{{ \Carbon\Carbon::parse($c->date_obtention)->locale('fr')->isoFormat('D MMMM YYYY') }}</b>
                </td>
                <td class="droite">
                    Code de vérification<br>
                    <b>{{ $c->code_verification }}</b><br>
                    {{ $urlVerification }}
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
