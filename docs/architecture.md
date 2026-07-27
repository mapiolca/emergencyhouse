# Architecture Emergency House

## Principes

Emergency House sépare strictement deux surfaces :

- un portail public autonome, sans compte utilisateur Dolibarr ;
- un back-office Dolibarr réservé aux opérateurs habilités.

Les contrôleurs restent minces. Les règles métier sont portées par les objets
et services du module. Les données opérationnelles sont isolées par entité et
les partages inter-entités sont explicites.

## Frontières de sécurité

- Une session publique dédiée authentifie les particuliers.
- Chaque écriture publique impose méthode HTTP, CSRF, propriété, limitation de
  débit et validation métier.
- Les identités, coordonnées, adresses, positions exactes et messages sont
  chiffrés avec Sodium.
- Les recherches exactes utilisent des empreintes HMAC normalisées.
- Les réponses publiques ne contiennent jamais de données exactes masquées.
- Les révélations de coordonnées sont explicites, autorisées et auditées.

## Composants

1. Campagnes et territoires.
2. Comptes publics, sessions, jetons et consentements.
3. Offres, demandes, équipements et critères.
4. Géolocalisation protégée.
5. Correspondances et travaux de recalcul.
6. Sollicitations et messagerie bornée.
7. Allocations, capacités et séjours.
8. Vérification, modération et incidents.
9. Notifications et travaux planifiés.
10. Statistiques, exports et documents.
11. API, webhooks et adaptateurs optionnels.
12. Audit, conservation et purge.

## Intégrations Dolibarr

- objets `CommonObject` lorsque le contrat natif est pertinent ;
- permissions et menus déclarés dans le descripteur ;
- cron déclaré avec `$this->cronjobs` ;
- triggers limités à `CREATE`, `UPDATE` et `DELETE` ;
- Agenda et Notifications natifs alimentés par `c_action_trigger` ;
- modèles de numérotation et de documents natifs ;
- fichiers déterminés avec `getMultidirOutput()` ;
- hooks Multicompany et partage explicitement configurable ;
- adaptateurs Data Policy, Adhérents et Ressources optionnels.

## Notifications

Les événements back-office configurables utilisent les mécanismes natifs
Dolibarr. Une file transactionnelle propre au module est réservée aux comptes
publics, qui ne sont pas des utilisateurs Dolibarr et ne peuvent donc pas être
traités comme tels par le module Notifications.

## Cartographie

Les tuiles OpenStreetMap peuvent être utilisées avec attribution et cache
conformes. Le service Nominatim public ne reçoit jamais une adresse exacte.
Le géocodage exact nécessite un fournisseur contractuel ou auto-hébergé.

