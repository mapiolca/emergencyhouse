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
- Le formulaire de contact impose le token CSRF, le captcha natif, une limite
  de débit et une validation réelle du type des images jointes.
- Les photos persistantes des offres sont réencodées avant stockage afin de
  supprimer les métadonnées EXIF/GPS, puis servies par un contrôleur qui
  revérifie l’offre, l’entité, le propriétaire et leur état de validation.

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

## File de vérification

`EmergencyHouseVerificationService` est l’unique frontière métier de la file.
Il expose l’entrée ou la réactivation d’une cible, la réconciliation des
affectations et l’enregistrement d’une décision depuis un `queue_id`. Les
comptes confirmés, offres en attente et demandes actives partagent le même tri
FIFO.

Chaque entité possède un curseur de rotation verrouillé. La recherche des
opérateurs lit les attributions natives `user_rights` et
`usergroup_rights` du droit `emergencyhouse/verification/write`, tout en
excluant les utilisateurs externes et désactivés. Les droits administrateur
restent gérés par le helper central pour la supervision, sans modifier
l’éligibilité à la rotation.

La création ou réactivation d’une ligne verrouille le curseur et s’appuie sur
la contrainte unique `(entity, object_type, fk_object)`. La décision verrouille
la ligne de file puis la source, revalide son état `< 1`, écrit le registre,
met à jour la source et clôt la file dans la même transaction. La
réconciliation conserve `date_queued` lorsqu’elle remplace un attributaire ;
elle annule uniquement les lignes dont la source n’est plus éligible.

L’interface sépare la file active du registre historique. Le serveur calcule
l’ancienneté et les seuils propres à l’entité ; JavaScript actualise seulement
le compteur et les classes d’urgence, sans porter de règle d’accès ou de
décision.

## Intégrations Dolibarr

- objets `CommonObject` lorsque le contrat natif est pertinent ;
- permissions et menus déclarés dans le descripteur ;
- cron déclaré avec `$this->cronjobs` ;
- triggers limités à `CREATE`, `UPDATE` et `DELETE` ;
- Agenda et Notifications natifs alimentés par `c_action_trigger` ;
- modèles de numérotation et de documents natifs ;
- fichiers déterminés avec `getMultidirOutput()` ;
- hooks Multicompany et partage explicitement configurable ;
- intégration native Adhérents obligatoire, avec type configuré par entité ;
- adaptateurs Data Policy et Ressources optionnels.

## Notifications

Les événements back-office configurables utilisent les mécanismes natifs
Dolibarr. Une file transactionnelle propre au module est réservée aux comptes
publics, qui ne sont pas des utilisateurs Dolibarr et ne peuvent donc pas être
traités comme tels par le module Notifications.

Cette file ne remplace pas le transport de messagerie : chaque envoi passe par
`CMailFile` et hérite des réglages globaux Dolibarr, notamment du serveur SMTP,
de l’expéditeur et de la copie cachée permanente. Les messages d’inscription,
de vérification, de réinitialisation et de connexion temporaire sont envoyés
directement, sans écriture dans la file et sans travail planifié. La file et son
travail planifié sont réservés aux notifications métier différées. Les anciennes
lignes d’accès créées par une version antérieure sont exclues du traitement puis
invalidées sans envoi.

Le formulaire public de contact constitue également un envoi synchrone :
`EmergencyHousePublicContactService` remet immédiatement le message et ses
images validées à `CMailFile`. L’adresse du visiteur est utilisée comme adresse
de réponse, tandis que l’expéditeur et la copie cachée permanente restent ceux
de la configuration générale. Aucune ligne de file et aucun document permanent
ne sont créés. Les fichiers temporaires disparaissent avec la requête PHP.

## Photos des offres

`EmergencyHouseOfferPhotoService` contrôle le type réel, l’extension, la taille,
le nombre d’images, le résultat antivirus et la charge mémoire prévisible avant
de décoder les photos. Il les réencode en JPG, PNG ou WebP pour supprimer les
métadonnées du fichier source, notamment EXIF et GPS. Les fichiers sont rangés
dans le sous-répertoire `photos/` retourné à partir de
`getMultidirOutput($offer, 'emergencyhouse', 1)` ; la table
`emergencyhouse_offer_photo` conserve uniquement le nom technique, l’empreinte,
la position et le statut de validation.

Le propriétaire et les opérateurs habilités peuvent consulter les photos en
attente. Le portail anonyme ne reçoit que les photos approuvées d’une offre
publiée. L’ajout ou la suppression utilise le trigger CRUD `UPDATE` de l’offre
avec `trigger_reason = photo_change` et remet la vérification à zéro.

## Contact public et anti-spam

`public/contact.php` est lié depuis l’en-tête et le pied de page. Les
coordonnées proviennent des constantes par entité
`EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL` et
`EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE`.

La génération du code anti-spam est déléguée au point d’entrée natif
`core/antispamimage.php`. Le petit relais `public/captcha.php` est nécessaire
pour conserver une URL de même origine lorsque le répertoire `public/` est
exposé comme racine d’un domaine distinct. La valeur attendue reste celle de la
session native `dol_antispam_value`. Le formulaire reste fermé lorsque
`MAIN_SECURITY_ENABLECAPTCHA` ou l’extension GD n’est pas disponible.

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
