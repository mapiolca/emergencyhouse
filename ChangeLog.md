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
- Ajout dans l’onglet Sécurité d’un générateur natif Dolibarr pour produire,
  sans persistance, les deux clés d’environnement distinctes encodées en base64.
- Ajout d’un guide Sécurité en quatre étapes et d’un diagnostic non sensible
  contrôlant Sodium, la longueur minimale et la séparation des deux clés.
- Ajout d’un aperçu privé du portail, protégé par la session et le droit de
  configuration Dolibarr, utilisable avant toute configuration publique.
- Utilisation du pictogramme natif Dolibarr `fontawesome_house-user` pour
  représenter l’hébergement d’urgence dans le back-office.
- Définition de l’URL publique comme racine web directe du répertoire
  `public/` : navigation, formulaires, ressources et liens de notification
  n’ajoutent plus le chemin `/custom/emergencyhouse/public`.
- Remplacement du modèle de numérotation générique par six modèles propres aux
  campagnes, offres, demandes, sollicitations, allocations et signalements,
  avec migration conservatrice du choix historique, repli immédiat des anciennes
  constantes et descriptions distinctes dans les réglages.
- Ajout du connecteur de géocodage Géoplateforme inspiré de `lmdbzoning`, sans
  journalisation de l’adresse exacte, et d’un guide OpenStreetMap/géocodage.
- Ajout d’une aide SMS indiquant explicitement l’indisponibilité du transport
  dans cette version et empêchant toute activation trompeuse.
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
