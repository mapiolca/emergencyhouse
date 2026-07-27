INSERT INTO llx_emergencyhouse_schema (version, date_applied, checksum)
SELECT '1.0.0', NOW(), 'initial'
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_schema WHERE version = '1.0.0');

INSERT INTO llx_c_emergencyhouse_campaign_type (entity, code, label, position, active)
SELECT 1, 'fire', 'CampaignTypeFire', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_campaign_type WHERE entity = 1 AND code = 'fire');
INSERT INTO llx_c_emergencyhouse_campaign_type (entity, code, label, position, active)
SELECT 1, 'flood', 'CampaignTypeFlood', 20, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_campaign_type WHERE entity = 1 AND code = 'flood');
INSERT INTO llx_c_emergencyhouse_campaign_type (entity, code, label, position, active)
SELECT 1, 'storm', 'CampaignTypeStorm', 30, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_campaign_type WHERE entity = 1 AND code = 'storm');
INSERT INTO llx_c_emergencyhouse_campaign_type (entity, code, label, position, active)
SELECT 1, 'industrial_accident', 'CampaignTypeIndustrialAccident', 40, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_campaign_type WHERE entity = 1 AND code = 'industrial_accident');
INSERT INTO llx_c_emergencyhouse_campaign_type (entity, code, label, position, active)
SELECT 1, 'other', 'CampaignTypeOther', 100, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_campaign_type WHERE entity = 1 AND code = 'other');

INSERT INTO llx_c_emergencyhouse_housing_type (entity, code, label, position, active)
SELECT 1, 'whole_home', 'HousingTypeWholeHome', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_housing_type WHERE entity = 1 AND code = 'whole_home');
INSERT INTO llx_c_emergencyhouse_housing_type (entity, code, label, position, active)
SELECT 1, 'private_room', 'HousingTypePrivateRoom', 20, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_housing_type WHERE entity = 1 AND code = 'private_room');
INSERT INTO llx_c_emergencyhouse_housing_type (entity, code, label, position, active)
SELECT 1, 'shared_room', 'HousingTypeSharedRoom', 30, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_housing_type WHERE entity = 1 AND code = 'shared_room');
INSERT INTO llx_c_emergencyhouse_housing_type (entity, code, label, position, active)
SELECT 1, 'temporary_bed', 'HousingTypeTemporaryBed', 40, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_housing_type WHERE entity = 1 AND code = 'temporary_bed');
INSERT INTO llx_c_emergencyhouse_housing_type (entity, code, label, position, active)
SELECT 1, 'tent_garden', 'HousingTypeTentGarden', 50, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_housing_type WHERE entity = 1 AND code = 'tent_garden');
INSERT INTO llx_c_emergencyhouse_housing_type (entity, code, label, position, active)
SELECT 1, 'camper_pitch', 'HousingTypeCamperPitch', 60, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_housing_type WHERE entity = 1 AND code = 'camper_pitch');
INSERT INTO llx_c_emergencyhouse_housing_type (entity, code, label, position, active)
SELECT 1, 'collective', 'HousingTypeCollective', 70, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_housing_type WHERE entity = 1 AND code = 'collective');
INSERT INTO llx_c_emergencyhouse_housing_type (entity, code, label, position, active)
SELECT 1, 'other', 'HousingTypeOther', 100, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_housing_type WHERE entity = 1 AND code = 'other');

INSERT INTO llx_c_emergencyhouse_feature (entity, code, label, feature_group, value_type, position, active)
SELECT 1, 'drinking_water', 'FeatureDrinkingWater', 'utilities', 'boolean', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_feature WHERE entity = 1 AND code = 'drinking_water');
INSERT INTO llx_c_emergencyhouse_feature (entity, code, label, feature_group, value_type, position, active)
SELECT 1, 'electricity', 'FeatureElectricity', 'utilities', 'boolean', 20, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_feature WHERE entity = 1 AND code = 'electricity');
INSERT INTO llx_c_emergencyhouse_feature (entity, code, label, feature_group, value_type, position, active)
SELECT 1, 'heating', 'FeatureHeating', 'utilities', 'boolean', 30, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_feature WHERE entity = 1 AND code = 'heating');
INSERT INTO llx_c_emergencyhouse_feature (entity, code, label, feature_group, value_type, position, active)
SELECT 1, 'kitchen', 'FeatureKitchen', 'facilities', 'boolean', 40, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_feature WHERE entity = 1 AND code = 'kitchen');
INSERT INTO llx_c_emergencyhouse_feature (entity, code, label, feature_group, value_type, position, active)
SELECT 1, 'private_bathroom', 'FeaturePrivateBathroom', 'facilities', 'boolean', 50, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_feature WHERE entity = 1 AND code = 'private_bathroom');
INSERT INTO llx_c_emergencyhouse_feature (entity, code, label, feature_group, value_type, position, active)
SELECT 1, 'accessible', 'FeatureAccessible', 'accessibility', 'boolean', 60, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_feature WHERE entity = 1 AND code = 'accessible');
INSERT INTO llx_c_emergencyhouse_feature (entity, code, label, feature_group, value_type, position, active)
SELECT 1, 'animals_allowed', 'FeatureAnimalsAllowed', 'cohabitation', 'boolean', 70, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_feature WHERE entity = 1 AND code = 'animals_allowed');
INSERT INTO llx_c_emergencyhouse_feature (entity, code, label, feature_group, value_type, position, active)
SELECT 1, 'internet', 'FeatureInternet', 'utilities', 'boolean', 80, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_feature WHERE entity = 1 AND code = 'internet');

INSERT INTO llx_c_emergencyhouse_report_reason (entity, code, label, position, active)
SELECT 1, 'false_listing', 'ReportReasonFalseListing', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_report_reason WHERE entity = 1 AND code = 'false_listing');
INSERT INTO llx_c_emergencyhouse_report_reason (entity, code, label, position, active)
SELECT 1, 'money_request', 'ReportReasonMoneyRequest', 20, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_report_reason WHERE entity = 1 AND code = 'money_request');
INSERT INTO llx_c_emergencyhouse_report_reason (entity, code, label, position, active)
SELECT 1, 'discrimination', 'ReportReasonDiscrimination', 30, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_report_reason WHERE entity = 1 AND code = 'discrimination');
INSERT INTO llx_c_emergencyhouse_report_reason (entity, code, label, position, active)
SELECT 1, 'harassment', 'ReportReasonHarassment', 40, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_report_reason WHERE entity = 1 AND code = 'harassment');
INSERT INTO llx_c_emergencyhouse_report_reason (entity, code, label, position, active)
SELECT 1, 'safety', 'ReportReasonSafety', 50, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_report_reason WHERE entity = 1 AND code = 'safety');
INSERT INTO llx_c_emergencyhouse_report_reason (entity, code, label, position, active)
SELECT 1, 'privacy', 'ReportReasonPrivacy', 60, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_report_reason WHERE entity = 1 AND code = 'privacy');
INSERT INTO llx_c_emergencyhouse_report_reason (entity, code, label, position, active)
SELECT 1, 'impersonation', 'ReportReasonImpersonation', 70, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_report_reason WHERE entity = 1 AND code = 'impersonation');
INSERT INTO llx_c_emergencyhouse_report_reason (entity, code, label, position, active)
SELECT 1, 'spam', 'ReportReasonSpam', 80, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_report_reason WHERE entity = 1 AND code = 'spam');
INSERT INTO llx_c_emergencyhouse_report_reason (entity, code, label, position, active)
SELECT 1, 'other', 'ReportReasonOther', 100, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_report_reason WHERE entity = 1 AND code = 'other');

INSERT INTO llx_c_emergencyhouse_animal_type (entity, code, label, position, active)
SELECT 1, 'dog', 'AnimalTypeDog', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_animal_type WHERE entity = 1 AND code = 'dog');
INSERT INTO llx_c_emergencyhouse_animal_type (entity, code, label, position, active)
SELECT 1, 'cat', 'AnimalTypeCat', 20, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_animal_type WHERE entity = 1 AND code = 'cat');
INSERT INTO llx_c_emergencyhouse_animal_type (entity, code, label, position, active)
SELECT 1, 'other', 'AnimalTypeOther', 100, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_animal_type WHERE entity = 1 AND code = 'other');

INSERT INTO llx_c_emergencyhouse_verification_level (entity, code, label, position, active)
SELECT 1, 'email', 'VerificationLevelEmail', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_verification_level WHERE entity = 1 AND code = 'email');
INSERT INTO llx_c_emergencyhouse_verification_level (entity, code, label, position, active)
SELECT 1, 'phone', 'VerificationLevelPhone', 20, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_verification_level WHERE entity = 1 AND code = 'phone');
INSERT INTO llx_c_emergencyhouse_verification_level (entity, code, label, position, active)
SELECT 1, 'operator', 'VerificationLevelOperator', 30, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_verification_level WHERE entity = 1 AND code = 'operator');
INSERT INTO llx_c_emergencyhouse_verification_level (entity, code, label, position, active)
SELECT 1, 'identity', 'VerificationLevelIdentity', 40, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_verification_level WHERE entity = 1 AND code = 'identity');

INSERT INTO llx_c_emergencyhouse_refusal_reason (entity, code, label, position, active)
SELECT 1, 'unavailable', 'RefusalReasonUnavailable', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_refusal_reason WHERE entity = 1 AND code = 'unavailable');
INSERT INTO llx_c_emergencyhouse_refusal_reason (entity, code, label, position, active)
SELECT 1, 'dates_incompatible', 'RefusalReasonDatesIncompatible', 20, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_refusal_reason WHERE entity = 1 AND code = 'dates_incompatible');
INSERT INTO llx_c_emergencyhouse_refusal_reason (entity, code, label, position, active)
SELECT 1, 'capacity_insufficient', 'RefusalReasonCapacityInsufficient', 30, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_refusal_reason WHERE entity = 1 AND code = 'capacity_insufficient');
INSERT INTO llx_c_emergencyhouse_refusal_reason (entity, code, label, position, active)
SELECT 1, 'criteria_incompatible', 'RefusalReasonCriteriaIncompatible', 40, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_refusal_reason WHERE entity = 1 AND code = 'criteria_incompatible');
INSERT INTO llx_c_emergencyhouse_refusal_reason (entity, code, label, position, active)
SELECT 1, 'other', 'RefusalReasonOther', 100, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_refusal_reason WHERE entity = 1 AND code = 'other');

INSERT INTO llx_c_emergencyhouse_cancellation_reason (entity, code, label, position, active)
SELECT 1, 'emergency_resolved', 'CancellationReasonEmergencyResolved', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_cancellation_reason WHERE entity = 1 AND code = 'emergency_resolved');
INSERT INTO llx_c_emergencyhouse_cancellation_reason (entity, code, label, position, active)
SELECT 1, 'plans_changed', 'CancellationReasonPlansChanged', 20, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_cancellation_reason WHERE entity = 1 AND code = 'plans_changed');
INSERT INTO llx_c_emergencyhouse_cancellation_reason (entity, code, label, position, active)
SELECT 1, 'safety_concern', 'CancellationReasonSafetyConcern', 30, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_cancellation_reason WHERE entity = 1 AND code = 'safety_concern');
INSERT INTO llx_c_emergencyhouse_cancellation_reason (entity, code, label, position, active)
SELECT 1, 'no_response', 'CancellationReasonNoResponse', 40, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_cancellation_reason WHERE entity = 1 AND code = 'no_response');
INSERT INTO llx_c_emergencyhouse_cancellation_reason (entity, code, label, position, active)
SELECT 1, 'other', 'CancellationReasonOther', 100, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_cancellation_reason WHERE entity = 1 AND code = 'other');

INSERT INTO llx_c_emergencyhouse_moderation_action (entity, code, label, position, active)
SELECT 1, 'warn', 'ModerationActionWarn', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_moderation_action WHERE entity = 1 AND code = 'warn');
INSERT INTO llx_c_emergencyhouse_moderation_action (entity, code, label, position, active)
SELECT 1, 'hide', 'ModerationActionHide', 20, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_moderation_action WHERE entity = 1 AND code = 'hide');
INSERT INTO llx_c_emergencyhouse_moderation_action (entity, code, label, position, active)
SELECT 1, 'suspend', 'ModerationActionSuspend', 30, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_moderation_action WHERE entity = 1 AND code = 'suspend');
INSERT INTO llx_c_emergencyhouse_moderation_action (entity, code, label, position, active)
SELECT 1, 'close', 'ModerationActionClose', 40, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_emergencyhouse_moderation_action WHERE entity = 1 AND code = 'close');

INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'account_verification', 'email', 'fr_FR', 'Confirmez votre adresse e-mail – __SERVICE_NAME__', 'Bonjour __FIRSTNAME__, confirmez votre adresse e-mail en ouvrant ce lien : __VERIFY_URL__', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'account_verification' AND channel = 'email' AND lang = 'fr_FR');
INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'account_verification', 'email', 'en_US', 'Confirm your email address – __SERVICE_NAME__', 'Hello __FIRSTNAME__, confirm your email address by opening this link: __VERIFY_URL__', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'account_verification' AND channel = 'email' AND lang = 'en_US');

INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'password_reset', 'email', 'fr_FR', 'Réinitialisation de votre mot de passe – __SERVICE_NAME__', 'Bonjour __FIRSTNAME__, réinitialisez votre mot de passe avec ce lien temporaire : __RESET_URL__', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'password_reset' AND channel = 'email' AND lang = 'fr_FR');
INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'password_reset', 'email', 'en_US', 'Reset your password – __SERVICE_NAME__', 'Hello __FIRSTNAME__, reset your password with this temporary link: __RESET_URL__', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'password_reset' AND channel = 'email' AND lang = 'en_US');

INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'magic_login', 'email', 'fr_FR', 'Votre lien de connexion – __SERVICE_NAME__', 'Bonjour __FIRSTNAME__, connectez-vous avec ce lien temporaire : __LOGIN_URL__', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'magic_login' AND channel = 'email' AND lang = 'fr_FR');
INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'magic_login', 'email', 'en_US', 'Your sign-in link – __SERVICE_NAME__', 'Hello __FIRSTNAME__, sign in with this temporary link: __LOGIN_URL__', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'magic_login' AND channel = 'email' AND lang = 'en_US');

INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'solicitation_create', 'email', 'fr_FR', 'Nouvelle sollicitation __SOLICITATION_REF__', 'Une nouvelle sollicitation vous concerne. Consultez-la dans votre espace sécurisé : __SOLICITATION_URL__', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'solicitation_create' AND channel = 'email' AND lang = 'fr_FR');
INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'solicitation_create', 'email', 'en_US', 'New solicitation __SOLICITATION_REF__', 'A new solicitation concerns you. Open it in your secure space: __SOLICITATION_URL__', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'solicitation_create' AND channel = 'email' AND lang = 'en_US');

INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'solicitation_update', 'email', 'fr_FR', 'Mise à jour de la sollicitation __SOLICITATION_REF__', 'La sollicitation a été mise à jour. Consultez son état dans votre espace sécurisé : __SOLICITATION_URL__', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'solicitation_update' AND channel = 'email' AND lang = 'fr_FR');
INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'solicitation_update', 'email', 'en_US', 'Solicitation __SOLICITATION_REF__ updated', 'The solicitation was updated. Review its status in your secure space: __SOLICITATION_URL__', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'solicitation_update' AND channel = 'email' AND lang = 'en_US');

INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'message_created', 'email', 'fr_FR', 'Nouveau message pour __SOLICITATION_REF__', 'Vous avez reçu un nouveau message. Ouvrez la conversation sécurisée : __SOLICITATION_URL__', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'message_created' AND channel = 'email' AND lang = 'fr_FR');
INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'message_created', 'email', 'en_US', 'New message for __SOLICITATION_REF__', 'You received a new message. Open the secure conversation: __SOLICITATION_URL__', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'message_created' AND channel = 'email' AND lang = 'en_US');

INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'allocation_create', 'email', 'fr_FR', 'Proposition de séjour __ALLOCATION_REF__', 'Une proposition de séjour requiert votre confirmation : __ALLOCATION_URL__', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'allocation_create' AND channel = 'email' AND lang = 'fr_FR');
INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'allocation_create', 'email', 'en_US', 'Stay proposal __ALLOCATION_REF__', 'A stay proposal needs your confirmation: __ALLOCATION_URL__', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'allocation_create' AND channel = 'email' AND lang = 'en_US');

INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'allocation_update', 'email', 'fr_FR', 'Mise à jour du séjour __ALLOCATION_REF__', 'Le séjour a été mis à jour. Consultez son état : __ALLOCATION_URL__', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'allocation_update' AND channel = 'email' AND lang = 'fr_FR');
INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'allocation_update', 'email', 'en_US', 'Stay __ALLOCATION_REF__ updated', 'The stay was updated. Review its status: __ALLOCATION_URL__', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'allocation_update' AND channel = 'email' AND lang = 'en_US');

INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'offer_confirmation_due', 'email', 'fr_FR', 'Confirmez la disponibilité de __OFFER_REF__', 'Merci de vérifier et confirmer la disponibilité de votre offre __OFFER_REF__ dans votre espace Emergency House.', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'offer_confirmation_due' AND channel = 'email' AND lang = 'fr_FR');
INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'offer_confirmation_due', 'email', 'en_US', 'Confirm availability for __OFFER_REF__', 'Please review and confirm the availability of your offer __OFFER_REF__ in your Emergency House space.', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'offer_confirmation_due' AND channel = 'email' AND lang = 'en_US');

INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'stay_reminder', 'email', 'fr_FR', 'Rappel pour le séjour __ALLOCATION_REF__', 'Le séjour __ALLOCATION_REF__ débute prochainement. Consultez votre espace sécurisé pour vérifier les informations.', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'stay_reminder' AND channel = 'email' AND lang = 'fr_FR');
INSERT INTO llx_emergencyhouse_notification_template
(entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)
SELECT 1, 0, 'stay_reminder', 'email', 'en_US', 'Reminder for stay __ALLOCATION_REF__', 'Stay __ALLOCATION_REF__ starts soon. Review the information in your secure space.', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_emergencyhouse_notification_template WHERE entity = 1 AND fk_campaign = 0 AND template_code = 'stay_reminder' AND channel = 'email' AND lang = 'en_US');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'campaign@emergencyhouse', 'EMERGENCYHOUSE_CAMPAIGN_CREATE', 'CampaignCreated', 'CampaignCreatedDescription', 45020101
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_CAMPAIGN_CREATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'campaign@emergencyhouse', 'EMERGENCYHOUSE_CAMPAIGN_UPDATE', 'CampaignUpdated', 'CampaignUpdatedDescription', 45020102
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_CAMPAIGN_UPDATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'campaign@emergencyhouse', 'EMERGENCYHOUSE_CAMPAIGN_DELETE', 'CampaignDeleted', 'CampaignDeletedDescription', 45020103
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_CAMPAIGN_DELETE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'offer@emergencyhouse', 'EMERGENCYHOUSE_OFFER_CREATE', 'OfferCreated', 'OfferCreatedDescription', 45020104
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_OFFER_CREATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'offer@emergencyhouse', 'EMERGENCYHOUSE_OFFER_UPDATE', 'OfferUpdated', 'OfferUpdatedDescription', 45020105
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_OFFER_UPDATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'offer@emergencyhouse', 'EMERGENCYHOUSE_OFFER_DELETE', 'OfferDeleted', 'OfferDeletedDescription', 45020106
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_OFFER_DELETE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'request@emergencyhouse', 'EMERGENCYHOUSE_REQUEST_CREATE', 'RequestCreated', 'RequestCreatedDescription', 45020107
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_REQUEST_CREATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'request@emergencyhouse', 'EMERGENCYHOUSE_REQUEST_UPDATE', 'RequestUpdated', 'RequestUpdatedDescription', 45020108
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_REQUEST_UPDATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'request@emergencyhouse', 'EMERGENCYHOUSE_REQUEST_DELETE', 'RequestDeleted', 'RequestDeletedDescription', 45020109
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_REQUEST_DELETE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'solicitation@emergencyhouse', 'EMERGENCYHOUSE_SOLICITATION_CREATE', 'SolicitationCreated', 'SolicitationCreatedDescription', 45020110
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_SOLICITATION_CREATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'solicitation@emergencyhouse', 'EMERGENCYHOUSE_SOLICITATION_UPDATE', 'SolicitationUpdated', 'SolicitationUpdatedDescription', 45020111
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_SOLICITATION_UPDATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'solicitation@emergencyhouse', 'EMERGENCYHOUSE_SOLICITATION_DELETE', 'SolicitationDeleted', 'SolicitationDeletedDescription', 45020112
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_SOLICITATION_DELETE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'allocation@emergencyhouse', 'EMERGENCYHOUSE_ALLOCATION_CREATE', 'AllocationCreated', 'AllocationCreatedDescription', 45020113
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_ALLOCATION_CREATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'allocation@emergencyhouse', 'EMERGENCYHOUSE_ALLOCATION_UPDATE', 'AllocationUpdated', 'AllocationUpdatedDescription', 45020114
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_ALLOCATION_UPDATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'allocation@emergencyhouse', 'EMERGENCYHOUSE_ALLOCATION_DELETE', 'AllocationDeleted', 'AllocationDeletedDescription', 45020115
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_ALLOCATION_DELETE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'report@emergencyhouse', 'EMERGENCYHOUSE_REPORT_CREATE', 'ReportCreated', 'ReportCreatedDescription', 45020116
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_REPORT_CREATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'report@emergencyhouse', 'EMERGENCYHOUSE_REPORT_UPDATE', 'ReportUpdated', 'ReportUpdatedDescription', 45020117
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_REPORT_UPDATE');
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'report@emergencyhouse', 'EMERGENCYHOUSE_REPORT_DELETE', 'ReportDeleted', 'ReportDeletedDescription', 45020118
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'EMERGENCYHOUSE_REPORT_DELETE');
