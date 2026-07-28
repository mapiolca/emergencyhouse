# Confidentialité et conservation

Emergency House collecte uniquement les informations nécessaires à la mise en
relation et au suivi opérationnel. Les enfants ne sont jamais identifiés
nominativement et aucun diagnostic médical n'est collecté.

Les valeurs initiales de conservation sont :

- visites et événements d’audience détaillés : 90 jours ;
- agrégats quotidiens d’audience : 25 mois ;
- sessions et jetons expirés : 7 jours ;
- comptes inactifs sans annonce : 90 jours ;
- adresses exactes : 30 jours après le dernier séjour ;
- messages : 90 jours après clôture ;
- données opérationnelles : 90 jours après archivage ;
- audit de révélation : 12 mois.

Un gel documenté lié à un incident suspend la purge des seules données
concernées.

À l’inscription, l’identité et les coordonnées sont également enregistrées
dans une fiche d’adhérent native afin d’assurer la gestion associative. La
suppression ou l’anonymisation du compte public détache cette liaison, mais ne
supprime pas automatiquement la fiche d’adhérent : ses cotisations, pièces ou
obligations administratives peuvent imposer un cycle de conservation distinct.
Cette fiche doit être traitée séparément lorsqu’une demande d’effacement porte
aussi sur le registre des adhérents.

## Mesure d’audience optionnelle

La collecte d’audience interne est désactivée par défaut et activable par
entité. Elle exclut les robots connus, les ressources techniques, photos,
captcha, sitemap, index LLM et aperçus privés. Elle ne stocke jamais d’adresse
IP brute, d’URL complète, de paramètres, de jeton, de texte saisi, de message,
d’adresse électronique, de User-Agent complet ou d’identifiant de compte.

Le cookie d’audience est first-party, aléatoire, signé, HttpOnly, Secure et
SameSite=Lax. Son expiration est fixée à treize mois sans renouvellement
automatique et son empreinte est cloisonnée par entité. Le portail ne
catégorise que la source, le domaine référent, l’appareil et le contexte
anonyme/authentifié.

La page publique **Mesure d’audience** permet une opposition immédiate. Cette
action efface le cookie d’audience et ne conserve qu’un cookie technique de
refus. Les agrégats déjà anonymisés ne permettent pas d’effacement individuel
rétroactif. La conception n’ajoute ni publicité, ni profilage, ni
géolocalisation, ni rapprochement avec les adhérents ou d’autres modules.

Le responsable du traitement doit réaliser sa propre évaluation au regard des
critères de la CNIL avant activation :
<https://www.cnil.fr/fr/cookies-et-autres-traceurs/regles/cookies-solutions-pour-les-outils-de-mesure-daudience>.
