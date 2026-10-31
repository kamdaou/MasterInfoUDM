# TP3 — Commande Studio Mobile

Application mobile Flutter de gestion de commandes. La version Android consomme l’API JSON Symfony du TP2 et ne se connecte jamais directement à MariaDB.

## Fonctionnalités

- Tableau de bord : CA total et mensuel, commandes, unités, ventes sur six mois, meilleurs produits et clients, stock bas.
- Produits : catalogue, création et modification.
- Clients : liste, région, création, modification et suppression avec respect des commandes associées.
- Commandes : liste, création, modification, détail, ajout/retrait d’articles et suppression avec restitution du stock.
- Actualisation par glissement vers le bas, états de chargement, erreurs réseau et reprise.

## Architecture

- `lib/src/api_client.dart` : client HTTP versionné pour `/api/v1`.
- `lib/src/dashboard_screen.dart` : statistiques.
- `lib/src/products_screen.dart` : catalogue et formulaire produit.
- `lib/src/customers_screen.dart` : clients et formulaire.
- `lib/src/orders_screen.dart` : commandes, formulaires et lignes.
- `TP2 Exam/symfony-app/src/Controller/ApiController.php` : API REST Symfony.

## Préparer Android

Les fichiers natifs Android sont déjà générés. Installez Flutter et Android Studio, puis vérifiez la configuration et récupérez les dépendances depuis ce dossier :

```powershell
flutter doctor
flutter pub get
```

L’accès Internet est déclaré dans le manifeste Android. Le trafic HTTP en clair est autorisé uniquement pour les builds de débogage locaux; les versions distribuées doivent utiliser HTTPS.

## Démarrer l’API

MySQL/MariaDB XAMPP doit être actif et le `.env.local` du TP2 doit pointer vers la base configurée. Depuis PowerShell, à la racine de `TP3 Exam` :

```powershell
C:\xampp\php\php.exe -S 0.0.0.0:8002 -t "..\TP2 Exam\symfony-app\public"
```

Vérifiez l’API sur `http://127.0.0.1:8002/api/v1/health`. Elle doit répondre `{"status":"ok","api":"v1"}`.

## Lancer l’application

### Émulateur Android

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8002/api/v1
```

`10.0.2.2` est l’adresse spéciale de l’hôte vue depuis l’émulateur Android.

### Appareil Android réel

Connectez l’appareil au même réseau que le PC, puis remplacez `ADRESSE_IPV4_DU_PC` par son adresse locale :

```powershell
flutter run --dart-define=API_BASE_URL=http://ADRESSE_IPV4_DU_PC:8002/api/v1
```

Autorisez le port 8002 sur le réseau privé dans le pare-feu Windows si nécessaire. Ne publiez pas ce serveur de développement sur Internet.

### iOS

La génération et l’exécution de la plateforme iOS requièrent macOS et Xcode. L’usage de HTTP en développement peut être bloqué par App Transport Security; en production, servez l’API en HTTPS.

```shell
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8002/api/v1
```

La compilation et l’exécution iOS requièrent macOS et Xcode.

## Vérifier

```powershell
flutter analyze
flutter test
```

Tester d’abord `/api/v1/health`, puis ouvrir les quatre onglets dans l’application. Vérifier en particulier qu’ajouter un article décrémente le stock, que le retirer le restitue et que la suppression d’une commande restitue toutes ses lignes.