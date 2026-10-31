# TP2 - Gestion d'une ligne de commande

L'application Symfony se trouve dans `symfony-app/`. Elle utilise PHP 8.2+, Symfony 7.4, Twig, Doctrine DBAL et MySQL/MariaDB.

Le rendu modifiable du projet est disponible dans [`Rapport_TP2_Groupe_21.docx`](../Rapport_TP2_Groupe_21.docx).

## Configuration

Dans `symfony-app/`, créez un fichier `.env.local` contenant le DSN MySQL d'exemple de `.env`, puis renseignez l'hôte, le nom de la base et les identifiants fournis pour votre serveur. Encodez les caractères spéciaux du mot de passe dans l'URL (par exemple `@` en `%40`). Ne partagez pas `.env.local` : il est exclu de Git.

Sur une installation XAMPP locale, démarrez MySQL puis créez la base `gestion_commande` dans phpMyAdmin. Créez également un compte MySQL autorisé à se connecter depuis `localhost` et à gérer cette base, ou utilisez un compte local existant. Le compte fourni par le sujet doit être créé séparément dans MariaDB s'il n'y existe pas encore ; définissez vous-même son mot de passe et gardez-le uniquement dans `.env.local`.

Pour le faire depuis l'onglet SQL de phpMyAdmin, adaptez puis exécutez ces commandes en remplaçant le mot de passe d'exemple par un mot de passe local choisi par vous :

```sql
CREATE DATABASE gestion_commande CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'MasTMound'@'localhost' IDENTIFIED BY 'CHOISIR_UN_MOT_DE_PASSE_LOCAL';
GRANT ALL PRIVILEGES ON gestion_commande.* TO 'MasTMound'@'localhost';
FLUSH PRIVILEGES;
```

Configurez ensuite `.env.local` avec le même compte local. Ne mettez pas le mot de passe dans ce guide ni dans `.env`.

Le nom des tables et des champs suit le sujet : `Client`, `Commande`, `ligneCommande`, `Produit`, `Region` et `Departement`. La migration `Version20261012000000` crée ce schéma dans une base vide. Ne l'exécutez pas sur une base contenant déjà ces tables.

Depuis `symfony-app/`, créez la base vide puis exécutez :

```powershell
C:\xampp\php\php.exe bin/console doctrine:migrations:migrate
```

La migration crée la structure, pas les données métier. Il faut disposer de clients et de commandes avant de pouvoir ajouter des lignes de commande.

Pour tester avec des données fictives, créez une base de test vide, configurez `.env.local` avec cette base, exécutez la migration, puis importez `symfony-app/fixtures/demo-data.sql` depuis phpMyAdmin. Ce jeu d'essai crée trois clients, trois commandes et quatre produits. Il ne faut pas l'importer dans une base qui contient déjà ces identifiants.

## Lancement

Depuis `symfony-app/`, démarrez le serveur local avec :

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public
```

Ouvrez ensuite `http://127.0.0.1:8000`.

## Parcours de test

1. Ouvrez `/` et vérifiez le chiffre d'affaires mensuel, la tendance sur six mois, les meilleurs produits et clients, les commandes récentes et l'article en stock bas.
2. Ouvrez `/produits`, modifiez un prix puis créez une référence supplémentaire.
3. Ouvrez `/clients`, créez un client, modifiez-le, puis vérifiez le blocage de suppression pour un client lié à une commande.
4. Ouvrez `/commandes`, créez une commande pour le client de test, modifiez sa date, puis consultez son détail.
5. Ajoutez une unité disponible à la commande et vérifiez que le stock baisse de 1. Tentez aussi une quantité supérieure au stock disponible : elle doit être refusée.
6. Retirez la ligne de commande et vérifiez que le stock revient à sa valeur précédente.
7. Supprimez une commande contenant des articles et vérifiez que les quantités sont restituées au stock.
8. Vérifiez les régions et le nombre de commandes sur la page clients.

Pour arrêter le serveur local, utilisez `Ctrl+C` dans le terminal où il tourne.

## Fonctionnalités

- Tableau de bord : chiffre d'affaires, commandes, unités vendues et meilleures ventes.
- Catalogue : liste, création et modification des produits.
- Commandes : création, modification, suppression avec restitution du stock, détail des articles, ajout avec contrôle du stock et retrait d'une ligne avec restitution.
- Clients : liste, création, modification, suppression (bloquée si des commandes leur sont liées), région et nombre de commandes.
- Statistiques : chiffre d'affaires total et mensuel, évolution sur six mois, meilleurs produits et clients, alertes de stock sous cinq unités.