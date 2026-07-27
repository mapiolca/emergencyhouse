# Emergency House — règles de développement

Ce dépôt contient directement la racine du module externe Dolibarr
`emergencyhouse`. Il ne faut jamais créer une seconde racine imbriquée.

Règles permanentes :

- compatibilité Dolibarr 20+ et PHP 8.0+ ;
- aucune modification du cœur Dolibarr ;
- toutes les données métier sont filtrées par `entity` ;
- les comptes publics ne sont jamais des utilisateurs Dolibarr ;
- les données personnelles critiques sont chiffrées et absentes des logs ;
- seuls les triggers CRUD `CREATE`, `UPDATE` et `DELETE` sont autorisés ;
- les réglages sont conservés à la désactivation ;
- les interfaces back-office utilisent les composants natifs Dolibarr ;
- le portail public est isolé visuellement et techniquement du back-office ;
- chaque texte visible est traduit en `fr_FR` et `en_US` ;
- aucun secret ou identifiant fournisseur n'est stocké dans ce dépôt.

