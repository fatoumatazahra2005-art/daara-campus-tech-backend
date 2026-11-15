<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @yield('rafraichir')
    <title>Simulateur opérateur — Dara Campus Tech</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #E9EDF3; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1B1F2A; }
        .telephone { width: 360px; max-width: 100%; min-height: 680px; background: #fff; border-radius: 42px;
            border: 10px solid #1B1F2A; box-shadow: 0 30px 60px rgba(0,0,0,.18); overflow: hidden;
            display: flex; flex-direction: column; }
        .barre { display: flex; justify-content: space-between; padding: 10px 22px; font-size: 12px; font-weight: 700; }
        .entete { padding: 18px 22px 22px; color: #fff; }
        .entete h1 { margin: 0; font-size: 20px; }
        .entete p { margin: 4px 0 0; opacity: .85; font-size: 13px; }
        .contenu { flex: 1; padding: 18px 18px 24px; }
        .carte { border: 1px solid #E3E7EE; border-radius: 18px; padding: 14px; margin-bottom: 12px; display: block;
            text-decoration: none; color: inherit; }
        .carte:hover { border-color: #9AA4B5; }
        .pastille { display: inline-block; padding: 3px 10px; border-radius: 99px; color: #fff; font-size: 11px; font-weight: 800; }
        .montant { font-size: 26px; font-weight: 900; margin: 6px 0 2px; }
        .gris { color: #6B7385; font-size: 13px; }
        label { display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; }
        input { width: 100%; padding: 12px 14px; border: 1px solid #CBD2DD; border-radius: 14px; font-size: 16px; }
        .code { letter-spacing: 12px; text-align: center; font-size: 24px; font-weight: 800; }
        .btn { width: 100%; padding: 13px; border: 0; border-radius: 14px; font-size: 15px; font-weight: 800; cursor: pointer; margin-top: 10px; }
        .btn-principal { color: #fff; }
        .btn-secondaire { background: #F1F3F7; color: #C62828; }
        .lien { background: none; border: 0; color: #6B7385; text-decoration: underline; cursor: pointer; font-size: 13px; padding: 0; }
        .message { background: #E8F7EF; color: #146C43; border-radius: 14px; padding: 10px 12px; font-size: 13px; font-weight: 700; margin-bottom: 14px; }
        .erreur { color: #C62828; font-size: 13px; font-weight: 700; margin-top: 6px; }
        .vide { text-align: center; color: #6B7385; padding: 40px 10px; }
        .note { text-align: center; font-size: 11px; color: #8A93A5; padding: 0 18px 16px; }
    </style>
</head>
<body>
<div class="telephone">
    <div class="barre"><span>{{ now()->format('H:i') }}</span><span>▮▮▮ 4G</span></div>
    @yield('contenu')
    <p class="note">Simulateur d’opérateur — environnement de démonstration</p>
</div>
</body>
</html>
