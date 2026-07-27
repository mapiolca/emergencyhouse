# ChangeLog

## 1.0.0 — 2026-07-26

- Livraison initiale du module Emergency House pour Dolibarr v20+ et PHP 8.0+.
- Ajout du portail public autonome : comptes, authentification, consentements,
  offres, demandes, sollicitations, allocations et signalements.
- Ajout du back-office opérateur : campagnes, modération, vérifications,
  correspondances, capacité, statistiques et audit des accès sensibles.
- Ajout de 43 tables MySQL/MariaDB isolées par entité, avec liaisons normalisées
  et sans suppression cascade métier.
- Ajout du chiffrement Sodium, des empreintes HMAC, des limitations de débit et
  des règles de conservation.
- Ajout des triggers CRUD, des événements Agenda/Notifications, des
  substitutions, des modèles de numérotation et du document PDF de convention.
- Ajout d’une file transactionnelle distincte pour les comptes du portail
  public, non représentés par des utilisateurs ou contacts Dolibarr ; les
  notifications back-office restent configurées dans le module natif.
- Ajout de neuf travaux planifiés natifs pour les files, expirations,
  confirmations, séjours, statistiques, rétention et fournisseurs.
- Ajout de l’intégration Multicompany, des permissions granulaires et de
  l’élévation administrateur centralisée.
- Ajout des traductions françaises et anglaises et de la documentation
  d’installation, de sécurité et de recette.
- Réservation du numéro de module `450201`, contrôlé dans les dépôts publics
  `mapiolca` et dans la liste officielle Dolibarr ; une vérification des modules
  privés du parc reste requise avant la première activation.
