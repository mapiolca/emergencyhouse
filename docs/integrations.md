# Intégrations optionnelles

Les intégrations Data Policy, Adhérents et Ressources sont désactivées par
défaut, configurables par entité et sans dépendance obligatoire.

Chaque liaison est explicite, auditée et conserve sa source de vérité. Une
désactivation du module cible interrompt la synchronisation sans supprimer les
liens ni les données Emergency House.

## Notifications Dolibarr

Les 18 événements CRUD des campagnes, offres, demandes, sollicitations,
allocations et signalements sont déclarés dans `c_action_trigger`, exposés par
le hook natif `notifsupported` et traduits en français et en anglais.

Le champ `elementtype` de ces déclarations contient la clé de module
`emergencyhouse`. Ce format permet aux pages Notifications des versions
Dolibarr 20 à 23 de vérifier que le module propriétaire est actif. Il reste
compatible avec l’Agenda natif, qui lie les événements aux objets à partir des
propriétés `element`, `module` et `id` de l’objet transmis au trigger CRUD.
