# Intégrations

Data Policy et Ressources restent des intégrations optionnelles, configurables
par entité. Le module natif Adhérents est désormais une dépendance obligatoire
d’Emergency House.

Chaque liaison est explicite, auditée et conserve sa source de vérité. Une
désactivation du module cible interrompt la synchronisation sans supprimer les
liens ni les données Emergency House.

## Adhérents

Chaque inscription publique crée immédiatement un objet natif `Adherent`, puis
le valide avec `Adherent::create()` et `Adherent::validate()`. Le compte public
reste néanmoins en attente jusqu’à la vérification de son adresse e-mail.

Le type utilisé est sélectionné par entité avec
`EMERGENCYHOUSE_ADHERENT_TYPE_ID`. Seuls les types actifs acceptant les
personnes physiques sont proposés. Si le module ou ce réglage est
indisponible, le formulaire public bloque l’inscription avant toute écriture.

Le rapprochement avec un adhérent existant se fait sur l’adresse e-mail
normalisée, strictement dans la même entité :

- une correspondance validée est liée ;
- une correspondance brouillon est validée puis liée ;
- plusieurs correspondances, une personne morale, un adhérent résilié ou
  exclu provoquent un conflit sans création partielle.

La fiche d’adhérent reçoit les prénom, nom, e-mail et téléphone. Son
identifiant de connexion est opaque et aucun mot de passe public, utilisateur
Dolibarr, tiers ou cotisation n’est créé.

La reprise administrateur traite par lots les seuls comptes actifs, vérifiés
et sans liaison. Elle est protégée par le token CSRF, limitée à l’entité
courante et rejouable. L’ancienne constante
`EMERGENCYHOUSE_ADHERENT_MODE` est conservée pour compatibilité mais n’est plus
utilisée.

L’anonymisation d’un compte public détache `fk_member` sans supprimer ni
anonymiser l’adhérent natif, dont le cycle administratif peut comporter des
cotisations ou des obligations de conservation indépendantes.

## Notifications Dolibarr

Les 18 événements CRUD des campagnes, offres, demandes, sollicitations,
allocations et signalements sont déclarés dans `c_action_trigger`, exposés par
le hook natif `notifsupported` et traduits en français et en anglais.

Le champ `elementtype` de ces déclarations contient la clé de module
`emergencyhouse`. Ce format permet aux pages Notifications des versions
Dolibarr 20 à 23 de vérifier que le module propriétaire est actif. Il reste
compatible avec l’Agenda natif, qui lie les événements aux objets à partir des
propriétés `element`, `module` et `id` de l’objet transmis au trigger CRUD.
