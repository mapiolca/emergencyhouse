# Emergency House

Emergency House est un module externe Dolibarr destiné à coordonner
l’hébergement solidaire de personnes déplacées ou sinistrées. Il fournit un
back-office opérateur et un portail public autonome, sans créer de comptes
utilisateurs Dolibarr pour les particuliers.

- Version : `1.0.0`
- Identifiant Dolibarr : `450201`
- Famille : `Les Métiers du Bâtiment`
- Dépôt : <https://github.com/mapiolca/emergencyhouse>
- Licence : GPL-3.0-or-later

## Fonctionnalités principales

- campagnes territorialisées et consignes officielles ;
- comptes publics, vérification d’adresse électronique, sessions et
  réinitialisation de mot de passe, avec création immédiate d’un adhérent
  Dolibarr validé ;
- offres d’hébergement et demandes privées par défaut ;
- vérification opérateur avant publication des offres ;
- moteur de correspondance pondéré et file de recalcul ;
- sollicitations, consentement mutuel et messagerie chiffrée ;
- allocations, capacité, séjours, incidents et signalements ;
- révélation contrôlée et auditée des coordonnées et adresses exactes ;
- statistiques agrégées, API REST optionnelle et convention PDF ;
- intégration native Dolibarr des droits, menus, triggers CRUD, Notifications,
  Agenda, travaux planifiés, documents, numérotation et Multicompany.

## Compatibilité

- Dolibarr 20 et versions ultérieures ;
- PHP 8.0 et versions ultérieures ;
- MySQL ou MariaDB ;
- extension PHP Sodium obligatoire pour ouvrir le portail public ;
- HTTPS obligatoire en production.

Le module natif **Adhérents** est une dépendance obligatoire. Chaque entité
doit sélectionner un type d’adhérent actif acceptant les personnes physiques
avant l’ouverture des inscriptions. Les intégrations Data Policy et Ressources
restent optionnelles.

L’onglet de réglages **Multicompany** est affiché uniquement lorsque le module
Multicompany est actif et qu’un partage multi-entité est effectivement
configuré pour au moins un objet. Les filtres et colonnes **Environnement**
restent limités aux écrans dont le périmètre effectif contient plusieurs
entités.

## Installation

Le contenu du dépôt constitue directement la racine du module
`emergencyhouse`. Il doit être placé dans le répertoire des modules externes
Dolibarr sans créer de répertoire imbriqué
`emergencyhouse/emergencyhouse`.

Après copie ou clonage :

1. activer Emergency House dans la liste des modules ;
2. dans l’onglet **Intégrations**, sélectionner le type d’adhérent créé à
   chaque inscription et vérifier l’état de l’intégration ;
3. ouvrir les réglages et vérifier les onglets **Compatibilité** et
   **Diagnostic** ;
4. vérifier dans l’onglet **Sécurité** que les clés gérées automatiquement sont
   disponibles ;
5. renseigner les textes juridiques, les coordonnées officielles et l’URL
   racine qui expose directement le répertoire `public/`, ainsi que l’e-mail
   et le téléphone du support ;
6. vérifier les travaux planifiés Emergency House, automatiquement activés en
   même temps que le module ;
7. configurer les notifications back-office dans la page native
   `/admin/notification.php` ;
8. activer le captcha natif dans les réglages de sécurité et vérifier que
   l’extension PHP GD est disponible ;
9. lancer, si nécessaire, la reprise des comptes publics actifs et vérifiés
   depuis l’onglet **Intégrations** ;
10. réaliser la recette de sécurité, de droits et Multicompany avant
   l’ouverture publique.

La désactivation est non destructive : les travaux planifiés Emergency House
sont désactivés, mais leurs fréquences, paramètres et historiques ainsi que les
données, constantes, modèles, réglages Agenda/Notifications et réglages
Multicompany sont conservés. Ils sont tous réactivés avec le module.

### URL du portail public

`EMERGENCYHOUSE_PUBLIC_BASE_URL` désigne la racine web du répertoire
`public/`, et non la racine de Dolibarr. Pour une valeur
`https://emergencyhouse.example.org/`, les liens produits sont par exemple :

- `https://emergencyhouse.example.org/` pour l’accueil ;
- `https://emergencyhouse.example.org/offer/index.php` pour les offres ;
- `https://emergencyhouse.example.org/contact.php` pour contacter le support ;
- `https://emergencyhouse.example.org/account/index.php` pour l’espace
  personnel.

Le module n’ajoute jamais `/custom/emergencyhouse/public` à cette valeur. Les
feuilles de style, le script public et le logo sont également servis sous
`/assets/` depuis cette même racine. Si la constante est vide, le portail
conserve son URL interne Dolibarr pour faciliter une recette locale.

### Référencement public et robots IA

L’accueil, la page de contact, la déclaration d’accessibilité et les conditions
d’utilisation publiées sont indexables. Une campagne est indexable uniquement
si l’option **Autoriser l’indexation par les moteurs de recherche** est active,
si la campagne est publiée et en cours, et si sa description publique et ses
consignes officielles sont renseignées.

Les offres, demandes, formulaires, comptes, sollicitations et allocations
restent exclus de l’index. Les moteurs peuvent suivre les liens des offres et
demandes publiques, mais ne doivent pas conserver ces fiches temporaires dans
leurs résultats. Les données privées ne sont jamais ajoutées aux métadonnées,
au sitemap ou à l’index destiné aux LLM.

Le répertoire `public/` fournit les points de découverte suivants :

- `/robots.php`, exposé aussi comme `/robots.txt` avec la réécriture Apache
  fournie ;
- `/sitemap.php`, exposé aussi comme `/sitemap.xml` ;
- `/llms.php`, exposé aussi comme `/llms.txt`.

Sur Nginx ou lorsque les règles `.htaccess` sont désactivées, le serveur doit
faire pointer les trois URL usuelles vers les scripts PHP correspondants. Le
sitemap peut aussi être soumis directement avec son URL `/sitemap.php` dans les
outils pour webmasters.

`OAI-SearchBot` et `ChatGPT-User` sont autorisés afin que les pages publiques
puissent être découvertes et citées. `GPTBot`, qui concerne l’entraînement et
non la recherche ChatGPT, reste bloqué par défaut. L’administrateur peut
l’autoriser avec l’interrupteur `EMERGENCYHOUSE_PUBLIC_GPTBOT_ALLOWED` dans
l’onglet **Portail**.

Les pages indexables publient une URL canonique, une description, les
métadonnées Open Graph/Twitter et des données structurées `WebSite`,
`Organization`, `Service`, `WebPage` et `BreadcrumbList` selon le contexte.
`EMERGENCYHOUSE_PUBLIC_SOCIAL_IMAGE_URL` permet de fournir une image HTTPS de
partage, idéalement au format 1 200 × 630 pixels et sans donnée personnelle.

Les conditions générales d’utilisation sont saisies dans
`EMERGENCYHOUSE_PUBLIC_TERMS_HTML` depuis l’onglet **Portail**. Dolibarr utilise
son éditeur WYSIWYG natif lorsque ce module est actif, avec repli sur une zone
de texte standard. Le contenu est publié sur `/terms.php` ; si la constante est
vide, le lien, la page et le consentement d’inscription correspondants sont
masqués.

### Contact public

La page `/contact.php` est accessible depuis la navigation principale et le
pied de page. Les constantes `EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL` et
`EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE`, configurées dans l’onglet **Portail**,
alimentent les coordonnées affichées. L’e-mail est obligatoire pour ouvrir le
formulaire ; le téléphone reste facultatif.

Les messages sont envoyés immédiatement avec `CMailFile` et héritent du
transport, de l’expéditeur et de la copie cachée permanente configurés pour
l’instance. Ils ne passent jamais par la file ni par un travail planifié. Le
visiteur peut joindre jusqu’à cinq images JPG, PNG ou WebP de 5 Mo maximum
chacune, sous réserve de la limite native d’envoi de fichiers. Les images sont
contrôlées puis attachées depuis le répertoire temporaire PHP, sans copie dans
les documents du module.

Le formulaire exige le captcha natif activé par
`MAIN_SECURITY_ENABLECAPTCHA`, l’extension PHP GD, le token CSRF et une
limitation de débit par adresse réseau, pilotée par le réglage général du
module avec un minimum de dix envois par heure. Si le captcha ou l’e-mail de
support n’est pas prêt, les coordonnées restent visibles mais l’envoi est fermé.

### Photos des offres d’hébergement

L’interrupteur `EMERGENCYHOUSE_PHOTOS_ENABLED`, configuré dans l’onglet
**Portail**, autorise un propriétaire à joindre jusqu’à cinq photos JPG, PNG ou
WebP de 5 Mo maximum chacune, dans la limite native d’envoi de fichiers. Les
photos sont enregistrées dans le répertoire documentaire Multicompany de
l’entité propriétaire de l’offre.

Pour protéger la confidentialité, chaque image est décodée puis réencodée avant
son stockage. Les métadonnées intégrées au fichier d’origine, notamment EXIF et
la position GPS, sont ainsi supprimées. La disponibilité de ce traitement est
indiquée dans l’onglet **Compatibilité** et nécessite la prise en charge JPG,
PNG et WebP par l’extension PHP GD.

Les photos restent privées pour le propriétaire et les opérateurs tant que
l’offre n’a pas été vérifiée. La validation opérateur approuve les photos avec
l’offre ; un refus les maintient hors du portail public. Tout ajout ou toute
suppression remet l’offre dans le circuit de vérification. Les fichiers sont
servis par des contrôleurs qui revérifient l’offre, l’entité, le propriétaire
et le statut de chaque photo, jamais directement depuis le répertoire
documentaire.

## Aperçu privé du portail

Un utilisateur Dolibarr disposant du droit de configuration peut ouvrir
`/emergencyhouse/admin/public-preview.php` depuis le tableau de bord ou
l’onglet **Portail**. Cette URL reste protégée par la session et les droits
Dolibarr. Elle affiche uniquement des exemples traduits : elle ne nécessite ni
l’activation du portail public, ni campagne configurée, ni compte public.

## Numérotation

Chaque objet dispose de son propre modèle natif et de son propre compteur
mensuel atomique :

| Objet | Modèle par défaut | Exemple |
|---|---|---|
| Campagne | `emergencyhouse_campaign_standard` | `EHC-2607-00001` |
| Offre | `emergencyhouse_offer_standard` | `EHO-2607-00001` |
| Demande | `emergencyhouse_request_standard` | `EHR-2607-00001` |
| Sollicitation | `emergencyhouse_solicitation_standard` | `EHS-2607-00001` |
| Allocation | `emergencyhouse_allocation_standard` | `EHA-2607-00001` |
| Signalement | `emergencyhouse_report_standard` | `EHI-2607-00001` |

Une ancienne constante `emergencyhouse_standard` est immédiatement routée vers
le modèle propre à l’objet, puis migrée lors de la réactivation du module. Un
modèle personnalisé et les références déjà générées sont conservés. Le compteur
suit aussi le partage de numérotation Multicompany de l’objet.

## Notifications et messages transactionnels

Les événements CRUD destinés aux opérateurs sont exposés au module natif
**Notifications** et se configurent dans `/admin/notification.php`.

La file et les modèles locaux du module sont réservés aux messages
transactionnels du portail public (vérification d’adresse électronique,
réinitialisation, lien de connexion, sollicitations, messages et séjours).
Cette exception est nécessaire parce que leurs destinataires sont des comptes
publics Emergency House, et non des utilisateurs ou contacts Dolibarr
abonnables dans le module Notifications. Aucun écran local ne remplace la
configuration native des notifications back-office.

Le transport des courriels reste entièrement celui de Dolibarr via
`CMailFile` : mode d’envoi, serveur SMTP, expéditeur, hooks de messagerie,
destinataires forcés et copie cachée permanente. Les courriels d’inscription,
de vérification, de réinitialisation et de connexion temporaire sont envoyés
directement et ne sont jamais enregistrés dans la file ni confiés à un travail
planifié. La file transactionnelle est réservée aux notifications métier
différées. Après mise à jour, les anciennes entrées d’accès encore en attente
sont invalidées sans être envoyées.

## Secrets

Deux clés techniques distinctes sont gérées sous les noms fixes :

- `EMERGENCYHOUSE_ENCRYPTION_KEY` pour le chiffrement authentifié ;
- `EMERGENCYHOUSE_HMAC_KEY` pour les empreintes de recherche.

À la première activation, le module produit automatiquement deux valeurs
aléatoires différentes de 48 octets avec le générateur natif
`getRandomPassword()` de Dolibarr, puis les encode en base64. Elles sont
enregistrées dans l’entité globale sous forme de constantes sensibles.

Comme leurs noms se terminent par `_KEY`, `dolibarr_set_const()` chiffre
automatiquement les valeurs avec la clé unique de l’instance avant leur écriture
en base. Le secret en clair n’est jamais affiché, n’entre jamais dans Git et ne
figure pas tel quel dans un export SQL. L’onglet **Sécurité** se limite donc à
un diagnostic lisible et, si nécessaire, à un bouton de récupération
« Générer et enregistrer les clés ».

La sauvegarde de la base doit être accompagnée du fichier `conf.php` de la même
instance : la clé d’instance qu’il contient est nécessaire pour relire les
constantes sensibles après restauration. Les installations historiques qui
fournissent déjà les deux valeurs par variables d’environnement restent prises
en charge sans migration forcée.

## Cartographie et fournisseurs

L’onglet **Fournisseurs** contient une procédure pas-à-pas et les liens vers les
politiques officielles :

- les tuiles OpenStreetMap utilisent par défaut
  `https://tile.openstreetmap.org/{z}/{x}/{y}.png` et exigent attribution,
  cache normal et absence de préchargement massif ;
- le géocodage exact est désactivé par défaut. Pour les adresses françaises,
  le connecteur pris en charge utilise l’API Géoplateforme
  `https://data.geopf.fr/geocodage/search`, selon le même format GeoJSON que le
  module `lmdbzoning`. Lorsqu’il est activé, les offres reçoivent des
  coordonnées exactes chiffrées et une cellule approximative ; les demandes
  conservent uniquement la cellule approximative utilisée par le matching ;
- le service Nominatim public n’est jamais utilisé pour une adresse exacte ;
- l’appel Géoplateforme n’utilise pas le helper Dolibarr v20 qui journalise
  l’URL complète, afin que l’adresse ne soit pas recopiée dans les journaux ;
- aucun connecteur SMS n’est livré dans cette version. Le réglage reste
  volontairement non activable jusqu’à l’ajout d’un transport audité ; les
  notifications transactionnelles disponibles utilisent le courriel. Le guide
  renvoie vers `/admin/sms.php` pour installer et tester séparément le moteur
  SMS natif Dolibarr.

## Développement et validation

La documentation technique est disponible dans [`docs/`](docs/), notamment :

- [`architecture.md`](docs/architecture.md) ;
- [`data-model.md`](docs/data-model.md) ;
- [`decisions.md`](docs/decisions.md) ;
- [`privacy.md`](docs/privacy.md) ;
- [`security-review.md`](docs/security-review.md) ;
- [`test-report.md`](docs/test-report.md).

Contrôles autonomes disponibles depuis la racine du module :

```bash
php test/static-contracts.php
php test/cron-contract.php
php test/multicompany-visibility-contract.php
```

La validation complète exige une instance Dolibarr v20+, une base
MySQL/MariaDB et au moins deux entités Multicompany.

## Licence

Emergency House est distribué sous licence GPL-3.0-or-later.
