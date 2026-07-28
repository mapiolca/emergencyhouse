# Rapport de tests

## Environnement disponible

- macOS ;
- PHP CLI 8.5.7 ;
- aucune instance Dolibarr locale ;
- aucune base MySQL/MariaDB reliée au module ;
- aucun exécutable ou fichier de configuration PHPStan ;
- aucun serveur web du module disponible pour une recette navigateur.

## Contrôles autonomes

Les contrôles suivants sont exécutables depuis la racine du module :

```bash
php test/static-contracts.php
php test/cron-contract.php
php test/agenda-element-contract.php
php test/numbering-model-contract.php
php test/security-key-contract.php
php test/public-url-contract.php
php test/multicompany-visibility-contract.php
php test/analytics-contract.php
php test/verification-queue-contract.php
```

Le script vérifie notamment :

- le numéro, la famille, la version et l’unique page de configuration ;
- la formule de numérotation des permissions ;
- l’absence de superglobales brutes et de contournement CSRF ;
- l’absence de triggers custom non CRUD ;
- l’absence d’arrondis financiers codés en dur ;
- l’absence de préfixe SQL `llx_` dans le PHP métier ;
- la présence des fichiers racine obligatoires ;
- la protection de l’aperçu privé et l’absence de donnée métier ;
- les six modèles de numérotation dédiés ;
- les garde-fous des clés et du connecteur Géoplateforme ;
- le chargement explicite de la bibliothèque native de dates dans chaque classe
  utilisant `dol_time_plus_duree()` ;
- l’absence de toute mention de Dolibarr dans les traductions réellement
  utilisées par l’interface publique ;
- l’héritage des documents juridiques de la plateforme lorsque les URL propres
  à une campagne sont laissées vides ;
- la distinction entre identifiants publics incorrects, limitation de débit et
  panne technique, avec journalisation des erreurs internes de session ;
- l’activation et la désactivation synchronisées des neuf travaux planifiés,
  sans suppression de leur configuration ;
- la dépendance au module natif Adhérents, la migration idempotente de
  `fk_member` et l’unicité de la liaison par entité ;
- la création et la validation exclusivement via l’objet natif `Adherent`,
  sans utilisateur, tiers, mot de passe membre, cotisation ni trigger
  EmergencyHouse supplémentaire ;
- le rapprochement strict des adhérents par e-mail normalisé et entité, le
  refus des correspondances ambiguës et des statuts incompatibles ;
- la transaction de l’inscription, la configuration obligatoire du type
  d’adhérent, la reprise administrateur protégée par CSRF et le détachement
  lors de l’anonymisation ;
- le retour natif `0` des neuf travaux planifiés en cas de succès, avec les
  compteurs métier conservés dans la sortie du travail ;
- la résolution native des six types d’objets Emergency House qualifiés dans
  l’Agenda, sans capture des types non qualifiés, et les libellés bilingues des
  événements CRUD ;
- le contrat d’URL racine du répertoire public et l’absence de liens de
  notification contournant le constructeur commun ;
- la présence des guides Sécurité et fournisseurs ;
- la publication conditionnelle des CGU et l’utilisation de l’éditeur WYSIWYG
  natif ;
- la traduction bilingue de chaque message littéral affiché par les alertes
  publiques ;
- l’unicité et la parité des traductions `fr_FR` et `en_US`.
- le caractère désactivé par défaut de la collecte, les trois tables
  multientités, le cookie fixe, l’opposition, le verrouillage concurrent, les
  codes contrôlés, l’absence de données personnelles dans le schéma et le
  raccordement du cron et de la rétention.

Une passe de lint PHP doit également être exécutée sur tous les fichiers
`*.php`.

Résultats de la passe finale du 28 juillet 2026 :

- lint PHP 8.5.7 : `130` fichiers PHP valides ;
- contrats statiques : `716 contrats validés` ;
- mesure d’audience : tables sans donnée personnelle, collecte désactivée,
  cookie fixe, opposition, engagement, verrouillage, agrégation, rétention,
  filtres exacts, parcours ordonné, menu et conversions contrôlés validés ;
- travaux planifiés : contrats de retour validés ;
- Agenda : résolution des six objets qualifiés et libellés CRUD bilingues
  validés ;
- visibilité Multicompany : `8 contrats` validés ;
- file de vérification : `8 contrats` validés ;
- modèles de numérotation : six noms, descriptions, préfixes et périmètres
  d’activation distincts validés ;
- URL publique : racine configurée, liens de pages, lien de notification,
  redirection interne et fallback Dolibarr validés ;
- ressources publiques autonomes : sorties CSS, JavaScript et SVG identiques
  aux sources canoniques du module ;
- catalogues `fr_FR` et `en_US` : parité et unicité validées ;
- clés : deux valeurs de 32 octets acceptées, valeurs identiques et valeur
  courte refusées ; chargement prioritaire par les constantes sensibles
  Dolibarr, repli historique sur l’environnement, chiffrement authentifié et
  empreinte déterministe validés ;
- Géoplateforme : requête non personnelle `Paris`, réponse GeoJSON et
  coordonnées validées ;
- PHPStan : non exécuté, outil et bootstrap Dolibarr absents de
  l’environnement.

## Recette d’intégration encore requise

- activation, désactivation et réactivation sur Dolibarr v20 ;
- migration et idempotence SQL sur MySQL/MariaDB ;
- PHPStan avec le bootstrap Dolibarr du parc ;
- matrice complète des droits et comptes administrateurs ;
- deux entités Multicompany et documents dans l’entité propriétaire ;
- parcours public avec deux comptes et consentements distincts ;
- création d’un adhérent validé à l’inscription et maintien du compte portail
  en attente jusqu’à la vérification de l’adresse e-mail ;
- rapprochement d’un adhérent validé, validation d’un brouillon, refus des
  doublons, résiliés, exclus et personnes morales, avec rollback complet ;
- reprise par lots de comptes actifs et vérifiés, rejouabilité, compteurs et
  anonymisation détachant la liaison sans supprimer l’adhérent ;
- types d’adhérents distincts sur deux entités, sans fuite ni liaison croisée ;
- confirmation qu’aucun utilisateur, tiers, mot de passe membre ou paiement de
  cotisation n’est créé ;
- domaine public dont la racine documentaire pointe sur `public/` : accueil,
  ressources `/assets/`, navigation, formulaires, redirections et liens
  reçus par courriel ;
- correspondances et réservations concurrentes ;
- Agenda, Notifications et travaux planifiés natifs ;
- génération, aperçu et téléchargement du PDF multipage ;
- envoi SMTP réel et fournisseurs optionnels homologués ;
- clavier, lecteur d’écran, zoom 200 % et largeurs mobiles ;
- vérification des logs Dolibarr après chaque parcours.

Les contrôles non exécutés ne doivent pas être présentés comme validés.
