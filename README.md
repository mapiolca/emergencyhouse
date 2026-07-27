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
4. renseigner les textes juridiques, l’URL publique et les coordonnées
   officielles ;
5. activer les travaux planifiés nécessaires depuis le module natif Dolibarr ;
6. configurer les notifications back-office dans la page native
   `/admin/notification.php` ;
7. réaliser la recette de sécurité, de droits et Multicompany avant
   l’ouverture publique.

La désactivation est non destructive : les données, constantes, modèles,
réglages Agenda/Notifications/Cron et réglages Multicompany sont conservés.

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
configuration d’environnement du serveur.

## Cartographie et fournisseurs

Les tuiles OpenStreetMap sont activables avec l’attribution requise. Le
géocodage exact reste désactivé tant qu’un fournisseur contractuel ou
auto-hébergé n’est pas configuré. Une adresse exacte ne doit jamais être
transmise au service Nominatim public. Le fournisseur SMS est également
désactivé par défaut.

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
