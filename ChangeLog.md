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
- Génération automatique à l’activation de deux clés globales distinctes avec
  le générateur natif Dolibarr, puis stockage sous forme de constantes
  sensibles chiffrées par la clé unique de l’instance.
- Simplification de l’onglet Sécurité : aucun secret à copier ou saisir,
  diagnostic non sensible et bouton de récupération uniquement si nécessaire.
- Masquage de la politique de confidentialité dans l’inscription et le pied de
  page publics lorsque le module natif Data Policy/RGPD est désactivé, sans
  exiger ni enregistrer le consentement correspondant.
- Ajout des conditions générales d’utilisation administrables en HTML avec
  l’éditeur WYSIWYG natif, page publique dédiée et masquage complet du lien et
  du consentement lorsque le contenu est vide.
- Correction des confirmations publiques manquantes, notamment après la
  création et la vérification d’un compte, qui affichaient à tort une erreur
  interne alors que l’opération avait réussi.
- Chargement explicite de la bibliothèque de dates Dolibarr dans les services
  utilisant `dol_time_plus_duree()`, afin que la vérification d’un compte et la
  création de sa session publique ne provoquent plus d’erreur fatale.
- Suppression de toute mention de Dolibarr dans les textes destinés aux
  utilisateurs du portail public, qui n’accèdent qu’aux services Emergency
  House.
- Les URL de confidentialité et de conditions d’utilisation d’une campagne
  deviennent facultatives : une valeur vide hérite des documents juridiques de
  la plateforme et ne bloque plus la publication.
- La connexion publique distingue désormais des identifiants incorrects d’une
  panne technique ; les échecs internes de recherche de compte, de limitation
  de débit et de création de session sont journalisés sans exposer de secret.
- La demande de connexion par lien temporaire affiche désormais sa confirmation
  générique bilingue au lieu d’être remplacée par une erreur interne.
- Les courriels de vérification, réinitialisation et connexion temporaire sont
  désormais envoyés directement par `CMailFile`, avec le transport,
  l’expéditeur et la copie cachée permanente configurés dans Dolibarr, sans
  insertion dans la file et sans dépendance à un travail planifié.
- La file transactionnelle et son travail planifié sont maintenant réservés
  exclusivement aux notifications métier différées.
- Les anciennes entrées d’accès encore en attente sont invalidées sans envoi,
  et toute nouvelle tentative de les mettre en file est refusée.
- Les erreurs du transport de messagerie sont maintenant visibles dans le
  résultat du travail planifié pour les notifications métier différées et
  journalisées sans adresse ni contenu sensible.
- Remplacement des helpers de permissions dans les déclarations de menus par
  `$user->hasRight()` afin que Dolibarr puisse évaluer nativement leur
  visibilité, avec rattachement du tableau de bord au droit de lecture des
  campagnes.
- Fixation des noms `EMERGENCYHOUSE_ENCRYPTION_KEY` et
  `EMERGENCYHOUSE_HMAC_KEY`, avec compatibilité conservée pour les anciennes
  installations qui les fournissent par l’environnement du serveur.
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
- Synchronisation automatique de ces travaux planifiés avec l’état du module :
  activation immédiate lors de l’activation ou de la réactivation, puis
  désactivation conservatrice du seul statut lors de la désactivation.
- Ajout de l’intégration Multicompany, des permissions granulaires et de
  l’élévation administrateur centralisée.
- Ajout des traductions françaises et anglaises et de la documentation
  d’installation, de sécurité et de recette.
- Réservation du numéro de module `450201`, contrôlé dans les dépôts publics
  `mapiolca` et dans la liste officielle Dolibarr ; une vérification des modules
  privés du parc reste requise avant la première activation.
