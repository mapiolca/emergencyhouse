# ChangeLog

## Non publié

- Refonte de la page publique des offres : affichage global par défaut,
  aperçu de toutes les campagnes ouvertes avec compteurs, regroupement des
  résultats par campagne, recherche sur la commune ou la zone publique et
  pagination conservant les filtres.
- Affichage compact côte à côte de l’aperçu des campagnes avec repli sur une
  colonne sur mobile, titre de section **Campagnes** aligné sur la hiérarchie
  de l’aperçu et versionnement de la feuille de style publique.
- Alignement de la page publique des demandes sur celle des offres : vue
  globale par défaut, compteurs par campagne, filtre de zone, regroupement,
  pagination et conservation stricte du mode de visibilité des demandes.
- Ajout sur l’accueil d’une bannière bilingue indiquant que le service évolue
  en permanence grâce aux retours utilisateurs et que ses améliorations sont
  accélérées face à l’urgence dans le Sud-Ouest.
- Ajout des traductions propres au module pour toutes les actions de changement
  d’état du back-office, notamment **Suspendre** et **Expirer**, afin d’éviter
  l’affichage de clés anglaises brutes selon la version de Dolibarr.
- Correction de l’édition publique d’une offre : la campagne cible est
  désormais validée comme publiée, ouverte et rattachée à la même entité avant
  d’être enregistrée dans la transaction de mise à jour.
- Correction de l’exposition des demandes publiques pour les campagnes
  configurées en mode **Offres et demandes** ; la publication reste
  conditionnée à la vérification opérateur, désormais explicitée dans le
  formulaire.
- Remplacement de la sélection manuelle de vérification par une file FIFO
  commune aux comptes confirmés, offres soumises et demandes actives, avec
  cible imposée par `queue_id` et registre historique conservé dans une vue
  distincte.
- Ajout d’une attribution tournante, persistante et verrouillée par entité aux
  utilisateurs internes actifs disposant explicitement du droit de
  vérification, directement ou par groupe, avec réaffectation automatique sans
  perte d’ancienneté et supervision administrateur.
- Ajout du compteur temps réel **À vérifier depuis HH:MM:SS**, des alertes
  orange et rouge configurables par entité à 10 et 30 minutes par défaut, et
  de la migration idempotente des objets existants encore éligibles.
- Ajout de `verification_status` aux comptes publics et clôture
  transactionnelle de la file, de l’objet et du registre ; les objets vérifiés
  ou refusés sont refusés côté serveur, y compris lors d’une décision
  concurrente.
- Traduction des valeurs techniques de visibilité, de vérification, de sens de
  sollicitation et de gravité dans les fiches et listes du back-office ; ajout
  du lien natif **Retour à la liste** dans les bannières et navigation
  précédent/suivant désormais fondée sur les identifiants `rowid`.
- Création et validation immédiates d’un adhérent natif lors de chaque
  inscription publique, avec type configuré par entité, rapprochement strict
  par e-mail et transaction empêchant tout compte ou adhérent orphelin.
- Ajout de la liaison unique `fk_member`, de sa migration idempotente, d’une
  reprise CSRF par lots des comptes actifs vérifiés, du lien back-office et des
  métadonnées d’adhésion dans l’export personnel ; l’anonymisation du compte
  détache la liaison sans supprimer l’adhérent.
- Correction de la traduction bilingue de la durée maximale facultative et
  regroupement compact des sélecteurs natifs jour, mois et année sur les
  formulaires publics d’offre et de demande.
- Correction du contrat des neuf travaux planifiés Emergency House : un
  traitement réussi retourne désormais `0` à Dolibarr, tandis que le nombre
  d’éléments traités est conservé dans la sortie du travail ; les échecs réels
  et les échecs partiels de correspondance restent signalés comme erreurs.
- Ajout du contrôle de l’adresse e-mail expéditrice au diagnostic et au travail
  de santé des fournisseurs, avec lien vers la configuration native des
  e-mails lorsque l’adresse est absente ou invalide.
- Ajout de cinq photos maximum par offre d’hébergement, avec contrôle réel du
  type JPG/PNG/WebP, limites natives d’envoi, stockage documentaire
  Multicompany, suppression protégée par token et diffusion limitée aux photos
  approuvées. Chaque image est réencodée avant stockage afin de supprimer ses
  métadonnées, notamment EXIF et GPS, et de préserver la confidentialité de
  l’adresse.
- Reprise de l’onglet **Fichiers joints** sur le contrôleur et le modèle
  documentaires natifs Dolibarr : résumé des fichiers, ajout classique ou par
  glisser-déposer natif, liens, aperçu, téléchargement, renommage et suppression
  selon les droits, dans le répertoire Multicompany de l’objet.
- Remplacement de l’identifiant technique du compte public sur la fiche d’offre
  par le libellé **Déposée par** : le nom est résolu depuis le compte lié et
  affiché uniquement avec le droit sur les coordonnées sensibles, sans
  déchiffrer l’e-mail ni le téléphone ; sinon, l’identité reste protégée.
- Remplacement du formulaire spécifique des notes d’objet par les actions et le
  modèle natifs Dolibarr, avec édition séparée des notes publique et privée et
  conservation des triggers CRUD du module.
- Chargement des domaines de traduction natifs `companies` et `other` sur le
  portail public afin de traduire les libellés standards d’adresse et de
  sécurité, avec correction des libellés bilingues de sélection, navigation,
  sollicitation et contact.
- Alignement des états du back-office sur les badges natifs Dolibarr et
  repositionnement des actions de fiche dans leur barre native, sous le
  contenu principal.
- Correction de l’affichage des dates de début et de fin sur les cartes et
  fiches du portail public, notamment pour les campagnes actives.
- Amélioration du tableau **État opérationnel** avec des en-têtes de colonnes,
  des badges de statut natifs Dolibarr et la traduction de **Travaux planifiés**.
- Masquage de l’onglet de réglages **Multicompany** tant que le module
  Multicompany est désactivé ou qu’aucun objet Emergency House ne possède de
  partage multi-entité effectif ; les filtres et colonnes **Environnement**
  restent propres aux écrans dont le périmètre contient plusieurs entités.
- Alignement des tuiles d’indicateurs du tableau de bord sur le composant
  statistique natif Dolibarr, avec libellés lisibles et compteurs hiérarchisés.
- Ajout du référencement contrôlé du portail : accueil et pages de confiance
  indexables, campagnes actives indexables sur opt-in, offres, demandes et
  espaces personnels maintenus hors index.
- Ajout des descriptions, URL canoniques, métadonnées Open Graph/Twitter et
  données structurées JSON-LD pour le site, l’organisme et les campagnes.
- Ajout des points de découverte dynamiques `robots.txt`, `sitemap.xml` et
  `llms.txt`, limités aux contenus publics autorisés et sans données
  personnelles.
- Autorisation explicite de `OAI-SearchBot` et `ChatGPT-User`, avec blocage de
  `GPTBot` par défaut et interrupteur administrateur distinct pour consentir à
  l’entraînement.
- Ajout d’une image sociale configurable par entité et validation HTTPS dans
  les réglages du portail.
- Correction de la découverte des 18 événements CRUD Emergency House par le
  module Notifications natif : `c_action_trigger.elementtype` utilise désormais
  la clé de module `emergencyhouse`, compatible avec le filtrage Dolibarr
  v20 à v23, avec migration idempotente des installations existantes lors de
  la réactivation du module.

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
- Ajout d’une page publique « Nous contacter », visible dans l’en-tête, le
  pied de page et l’aperçu privé, avec e-mail et téléphone du support
  configurables par entité.
- Ajout de l’envoi immédiat des demandes de contact par le transport de
  courriel natif, sans file ni travail planifié, avec jusqu’à cinq photos ou
  captures d’écran contrôlées et attachées sans stockage permanent.
- Protection du formulaire de contact par le captcha natif, le token CSRF et
  une limitation de débit ; l’envoi reste fermé si cette protection ou
  l’adresse du support n’est pas configurée.
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
