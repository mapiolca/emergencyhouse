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
  réinitialisation de mot de passe ;
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

Les intégrations Data Policy, Adhérents et Ressources sont optionnelles. Leur
indisponibilité ne bloque pas le socle du module.

## Installation

Le contenu du dépôt constitue directement la racine du module
`emergencyhouse`. Il doit être placé dans le répertoire des modules externes
Dolibarr sans créer de répertoire imbriqué
`emergencyhouse/emergencyhouse`.

Après copie ou clonage :

1. activer Emergency House dans la liste des modules ;
2. ouvrir les réglages et vérifier les onglets **Compatibilité** et
   **Diagnostic** ;
3. configurer les secrets de chiffrement dans l’environnement du serveur ;
4. renseigner les textes juridiques, les coordonnées officielles et l’URL
   racine qui expose directement le répertoire `public/` ;
5. activer les travaux planifiés nécessaires depuis le module natif Dolibarr ;
6. configurer les notifications back-office dans la page native
   `/admin/notification.php` ;
7. réaliser la recette de sécurité, de droits et Multicompany avant
   l’ouverture publique.

La désactivation est non destructive : les données, constantes, modèles,
réglages Agenda/Notifications/Cron et réglages Multicompany sont conservés.

### URL du portail public

`EMERGENCYHOUSE_PUBLIC_BASE_URL` désigne la racine web du répertoire
`public/`, et non la racine de Dolibarr. Pour une valeur
`https://emergencyhouse.example.org/`, les liens produits sont par exemple :

- `https://emergencyhouse.example.org/` pour l’accueil ;
- `https://emergencyhouse.example.org/offer/index.php` pour les offres ;
- `https://emergencyhouse.example.org/account/index.php` pour l’espace
  personnel.

Le module n’ajoute jamais `/custom/emergencyhouse/public` à cette valeur. Les
feuilles de style, le script public et le logo sont également servis sous
`/assets/` depuis cette même racine. Si la constante est vide, le portail
conserve son URL interne Dolibarr pour faciliter une recette locale.

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

## Secrets

Deux variables d’environnement distinctes sont attendues par défaut :

- `EMERGENCYHOUSE_ENCRYPTION_KEY` pour le chiffrement authentifié ;
- `EMERGENCYHOUSE_HMAC_KEY` pour les empreintes de recherche.

Utiliser deux valeurs aléatoires différentes d’au moins 32 octets, idéalement
encodées en base64. Elles ne doivent être enregistrées ni dans Git, ni dans la
base de données, ni dans une constante Dolibarr. Leur nom peut être adapté dans
les réglages du module.

L’onglet **Sécurité** des réglages permet de générer ces deux valeurs avec le
générateur natif `getRandomPassword()` de Dolibarr. Le module encode chaque
valeur en base64, les affiche une seule fois dans une réponse non mise en cache
et ne les enregistre pas. Il faut les copier immédiatement dans la
configuration d’environnement du serveur, redémarrer le processus PHP, puis
recharger l’onglet. Celui-ci vérifie sans afficher les secrets : Sodium, la
présence et la longueur des deux clés, leur différence et la disponibilité du
service de chiffrement.

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
```

La validation complète exige une instance Dolibarr v20+, une base
MySQL/MariaDB et au moins deux entités Multicompany.

## Licence

Emergency House est distribué sous licence GPL-3.0-or-later.
