-- Import only into a fresh test database after running the Doctrine migration.
INSERT INTO Departement (idDep, nomDep) VALUES
    (1, 'Littoral'),
    (2, 'Centre');

INSERT INTO Region (idRegion, nomRegion, idDep) VALUES
    (1, 'Cotonou', 1),
    (2, 'Porto-Novo', 1),
    (3, 'Abomey-Calavi', 1);

INSERT INTO Client (idCli, nomCli, prenomCli, email, photo, idRegion) VALUES
    (1, 'Adjovi', 'Mireille', 'mireille@example.test', NULL, 1),
    (2, 'Hountondji', 'David', 'david@example.test', NULL, 2),
    (3, 'Soglo', 'Ariane', 'ariane@example.test', NULL, 3);

INSERT INTO Produit (idProd, designProd, qteStock, prixUProd, photoProd) VALUES
    (1, 'Clavier sans fil', 17, 12500.00, NULL),
    (2, 'Souris optique', 8, 4500.00, NULL),
    (3, 'Support ordinateur', 24, 9000.00, NULL),
    (4, 'Casque audio', 4, 18000.00, NULL);

INSERT INTO Commande (idCmd, dateCmd, idCli) VALUES
    (1, NOW(), 1),
    (2, DATE_SUB(NOW(), INTERVAL 1 DAY), 2),
    (3, DATE_SUB(NOW(), INTERVAL 3 DAY), 3);

INSERT INTO ligneCommande (idCmd, idProd, qteCmd) VALUES
    (1, 1, 3),
    (1, 2, 2),
    (2, 3, 1),
    (3, 4, 2);