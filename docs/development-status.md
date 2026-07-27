# État du développement

## Version 1.0.0

| Domaine | État source | Validation d’intégration |
|---|---|---|
| Descripteur, droits, menus et réglages | Implémenté | À valider dans Dolibarr |
| Schéma, dictionnaires et données initiales | Implémenté | À valider sur MySQL/MariaDB |
| Campagnes et back-office opérateur | Implémenté | À valider dans le navigateur |
| Portail public et comptes autonomes | Implémenté, URL racine et ressources autonomes | À valider sur le domaine HTTPS dédié |
| Aperçu privé du portail | Implémenté | Rendu à valider dans Dolibarr |
| Consentements, chiffrement et audit | Implémenté | Revue de sécurité externe requise |
| Offres, demandes et critères | Implémenté | Parcours fonctionnels à valider |
| Correspondances et file asynchrone | Implémenté | Charge et concurrence à mesurer |
| Sollicitations et messagerie | Implémenté | Parcours multi-comptes à valider |
| Allocations, capacités et séjours | Implémenté | Concurrence et transitions à valider |
| Vérification, modération et incidents | Implémenté | Matrice de droits à valider |
| Notifications publiques et natives | Implémenté | Envoi réel à valider |
| Travaux planifiés | Implémenté | Exécution native à valider |
| OpenStreetMap et géocodage Géoplateforme | Implémenté, géocodage désactivé par défaut | Fournisseur et confidentialité à homologuer |
| SMS Emergency House | Non implémenté, activation bloquée | Utiliser la page native Dolibarr pour préparer/tester un moteur |
| Numérotation propre aux six objets | Implémenté | Migration et concurrence à valider sur MySQL/MariaDB |
| Statistiques, API et document PDF | Implémenté | Sorties et droits à valider |
| Multicompany | Implémenté | Recette à deux entités requise |
| Traductions et documentation | Implémenté | Relecture métier recommandée |
| Contrats statiques et syntaxe PHP | Implémenté | Voir `test-report.md` |

## Limite de l’environnement de développement

Le dépôt ne contient pas d’instance Dolibarr, de base MySQL/MariaDB, de
configuration PHPStan ni de navigateur connecté à une instance du module. Les
contrôles autonomes peuvent donc valider la syntaxe et les invariants du dépôt,
mais pas l’activation réelle, les migrations, les hooks, les logs, les emails,
le rendu PDF ou les parcours navigateur.

La version 1.0.0 doit être considérée comme candidate à recette jusqu’à
validation de la matrice décrite dans `test-report.md`.
