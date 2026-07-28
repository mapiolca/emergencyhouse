# Architecture Emergency House

## Principes

Emergency House sépare strictement deux surfaces :

- un portail public autonome, sans compte utilisateur Dolibarr ;
- un back-office Dolibarr réservé aux opérateurs habilités.

Les contrôleurs restent minces. Les règles métier sont portées par les objets
et services du module. Les données opérationnelles sont isolées par entité et
les partages inter-entités sont explicites.

Lorsque `EMERGENCYHOUSE_PUBLIC_BASE_URL` est renseignée, elle représente
directement la racine web du répertoire `public/`. Le routeur de liens ajoute
uniquement le chemin relatif de la page ou de la ressource publique ; il
n’ajoute aucun chemin d’installation Dolibarr. Sans valeur configurée, le
chemin interne Dolibarr reste le fallback de recette.

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

Cette file ne remplace pas le transport de messagerie : chaque envoi passe par
`CMailFile` et hérite des réglages globaux Dolibarr, notamment du serveur SMTP,
de l’expéditeur et de la copie cachée permanente. Les messages d’accès au
compte sont tentés immédiatement ; les échecs restent en file pour reprise par
le travail planifié natif.

## Cartographie

Les tuiles OpenStreetMap peuvent être utilisées avec attribution et cache
conformes. Le service Nominatim public ne reçoit jamais une adresse exacte.
Le connecteur de géocodage exact pris en charge cible exclusivement
`data.geopf.fr` en HTTPS, refuse les redirections et traite la réponse GeoJSON
de Géoplateforme. Il utilise un appel cURL borné au lieu de
`getURLContent()` : dans Dolibarr v20, ce helper journalise l’URL complète et
donc le paramètre d’adresse.

## Aperçu privé

`admin/public-preview.php` réutilise le rendu du portail avec des liens
internes et des exemples traduits. La page reste derrière l’authentification
Dolibarr et le droit de configuration. Elle ne lit ni campagne, ni compte
public, ni donnée sensible.

## Numérotation

Les six objets métier ont chacun un modèle natif dédié. Le moteur commun
réserve atomiquement le compteur par type, période et entité canonique de
partage. Les classes spécifiques limitent chaque modèle à son propre objet et
déclarent son préfixe.
