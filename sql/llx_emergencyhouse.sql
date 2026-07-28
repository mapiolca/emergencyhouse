-- Emergency House schema for MySQL/MariaDB.
-- Dolibarr replaces the llx_ prefix during module installation.

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_schema (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	version varchar(32) NOT NULL,
	date_applied datetime NOT NULL,
	checksum varchar(128) NULL,
	UNIQUE KEY uk_emergencyhouse_schema_version (version)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_sequence (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	object_type varchar(64) NOT NULL,
	period_code varchar(16) NOT NULL,
	next_value integer DEFAULT 0 NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uk_emergencyhouse_sequence (entity, object_type, period_code)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_campaign (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	ref varchar(128) NOT NULL,
	label varchar(255) NOT NULL,
	slug varchar(191) NOT NULL,
	fk_campaign_type integer NULL,
	description_public text NULL,
	official_instructions text NULL,
	coordinator_name varchar(255) NOT NULL,
	official_phone varchar(64) NOT NULL,
	official_email varchar(255) NULL,
	date_start datetime NOT NULL,
	date_end datetime NULL,
	timezone varchar(64) NOT NULL,
	public_visibility_mode varchar(32) DEFAULT 'offers' NOT NULL,
	verification_policy varchar(32) DEFAULT 'operator_validation' NOT NULL,
	default_radius integer DEFAULT 50 NOT NULL,
	matching_config_snapshot text NULL,
	matching_config_version varchar(32) DEFAULT '1' NOT NULL,
	retention_days integer DEFAULT 90 NOT NULL,
	consent_version varchar(64) NOT NULL,
	banner_text text NULL,
	eligibility_text text NULL,
	privacy_url varchar(1024) NOT NULL,
	terms_url varchar(1024) NOT NULL,
	robots_index integer DEFAULT 0 NOT NULL,
	status integer DEFAULT 0 NOT NULL,
	date_publication datetime NULL,
	date_closure datetime NULL,
	date_archive datetime NULL,
	date_purge_planned datetime NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NULL,
	fk_user_modif integer NULL,
	import_key varchar(14) NULL,
	note_public text NULL,
	note_private text NULL,
	model_pdf varchar(255) NULL,
	last_main_doc varchar(255) NULL,
	UNIQUE KEY uk_emergencyhouse_campaign_ref (entity, ref),
	UNIQUE KEY uk_emergencyhouse_campaign_slug (entity, slug),
	KEY idx_emergencyhouse_campaign_entity_status (entity, status),
	KEY idx_emergencyhouse_campaign_dates (entity, date_start, date_end),
	KEY idx_emergencyhouse_campaign_type (fk_campaign_type)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_campaign_entity (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_campaign integer NOT NULL,
	fk_shared_entity integer NOT NULL,
	can_read integer DEFAULT 1 NOT NULL,
	can_write integer DEFAULT 0 NOT NULL,
	date_creation datetime NOT NULL,
	fk_user_creat integer NULL,
	UNIQUE KEY uk_emergencyhouse_campaign_entity (entity, fk_campaign, fk_shared_entity),
	KEY idx_emergencyhouse_campaign_entity_campaign (fk_campaign),
	KEY idx_emergencyhouse_campaign_entity_shared (fk_shared_entity)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_campaign_territory (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_campaign integer NOT NULL,
	territory_type varchar(32) NOT NULL,
	territory_code varchar(128) NOT NULL,
	label varchar(255) NOT NULL,
	geometry_encrypted longtext NULL,
	public_geometry text NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime NOT NULL,
	fk_user_creat integer NULL,
	UNIQUE KEY uk_emergencyhouse_campaign_territory (entity, fk_campaign, territory_type, territory_code),
	KEY idx_emergencyhouse_campaign_territory_campaign (fk_campaign)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_public_account (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_member integer NULL,
	public_uuid varchar(64) NOT NULL,
	firstname_encrypted longtext NOT NULL,
	lastname_encrypted longtext NOT NULL,
	email_encrypted longtext NOT NULL,
	email_hash varchar(128) NOT NULL,
	phone_encrypted longtext NULL,
	phone_hash varchar(128) NULL,
	password_hash varchar(255) NULL,
	lang varchar(8) DEFAULT 'fr_FR' NOT NULL,
	preferred_contact varchar(16) DEFAULT 'email' NOT NULL,
	contact_availability varchar(255) NULL,
	email_verified integer DEFAULT 0 NOT NULL,
	phone_verification_level integer DEFAULT 0 NOT NULL,
	manual_verification_level integer DEFAULT 0 NOT NULL,
	verification_status integer DEFAULT 0 NOT NULL,
	adult_confirmed integer DEFAULT 0 NOT NULL,
	failed_login_count integer DEFAULT 0 NOT NULL,
	locked_until datetime NULL,
	last_login datetime NULL,
	last_activity datetime NULL,
	status integer DEFAULT 0 NOT NULL,
	date_deletion_requested datetime NULL,
	date_anonymized datetime NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	import_key varchar(14) NULL,
	UNIQUE KEY uk_emergencyhouse_account_uuid (public_uuid),
	UNIQUE KEY uk_emergencyhouse_account_email (entity, email_hash),
	UNIQUE KEY uk_emergencyhouse_account_member (entity, fk_member),
	KEY idx_emergencyhouse_account_phone (entity, phone_hash),
	KEY idx_emergencyhouse_account_status (entity, status),
	KEY idx_emergencyhouse_account_verification (entity, verification_status, date_creation),
	KEY idx_emergencyhouse_account_activity (entity, last_activity)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_public_session (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_account integer NOT NULL,
	session_hash varchar(128) NOT NULL,
	csrf_secret_encrypted longtext NOT NULL,
	ip_hash varchar(128) NULL,
	user_agent_hash varchar(128) NULL,
	date_creation datetime NOT NULL,
	last_activity datetime NOT NULL,
	expires_at datetime NOT NULL,
	revoked_at datetime NULL,
	UNIQUE KEY uk_emergencyhouse_session_hash (session_hash),
	KEY idx_emergencyhouse_session_account (entity, fk_account),
	KEY idx_emergencyhouse_session_expiry (entity, expires_at)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_token (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_account integer NOT NULL,
	token_type varchar(32) NOT NULL,
	token_hash varchar(128) NOT NULL,
	date_creation datetime NOT NULL,
	expires_at datetime NOT NULL,
	used_at datetime NULL,
	attempt_count integer DEFAULT 0 NOT NULL,
	UNIQUE KEY uk_emergencyhouse_token_hash (token_hash),
	KEY idx_emergencyhouse_token_account (entity, fk_account, token_type),
	KEY idx_emergencyhouse_token_expiry (entity, expires_at)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_consent (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_account integer NOT NULL,
	fk_campaign integer NULL,
	consent_type varchar(64) NOT NULL,
	consent_version varchar(64) NOT NULL,
	is_granted integer DEFAULT 0 NOT NULL,
	proof_hash varchar(128) NULL,
	date_granted datetime NULL,
	date_withdrawn datetime NULL,
	date_creation datetime NOT NULL,
	UNIQUE KEY uk_emergencyhouse_consent (entity, fk_account, fk_campaign, consent_type, consent_version),
	KEY idx_emergencyhouse_consent_account (fk_account),
	KEY idx_emergencyhouse_consent_campaign (fk_campaign)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_block (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_account integer NOT NULL,
	fk_blocked_account integer NOT NULL,
	reason_code varchar(64) NULL,
	date_creation datetime NOT NULL,
	date_end datetime NULL,
	UNIQUE KEY uk_emergencyhouse_block (entity, fk_account, fk_blocked_account),
	KEY idx_emergencyhouse_block_reverse (entity, fk_blocked_account)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_offer (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	ref varchar(128) NOT NULL,
	public_uuid varchar(64) NOT NULL,
	fk_campaign integer NOT NULL,
	fk_account integer NOT NULL,
	fk_housing_type integer NOT NULL,
	address_encrypted longtext NOT NULL,
	zip varchar(25) NOT NULL,
	town varchar(255) NOT NULL,
	fk_pays integer NULL,
	fk_departement integer NULL,
	public_zone varchar(255) NOT NULL,
	public_location_precision varchar(16) DEFAULT 'town' NOT NULL,
	latitude_encrypted longtext NULL,
	longitude_encrypted longtext NULL,
	geo_cell varchar(32) NULL,
	date_start datetime NOT NULL,
	date_end datetime NULL,
	capacity_total integer NOT NULL,
	capacity_available integer NOT NULL,
	max_adults integer NULL,
	max_children integer NULL,
	room_count integer DEFAULT 0 NOT NULL,
	bed_count integer DEFAULT 0 NOT NULL,
	extra_bed_count integer DEFAULT 0 NOT NULL,
	tent_count integer DEFAULT 0 NOT NULL,
	title varchar(255) NOT NULL,
	description_public text NULL,
	private_instructions_encrypted longtext NULL,
	minimum_stay_days integer DEFAULT 0 NOT NULL,
	maximum_stay_days integer NULL,
	arrival_window varchar(255) NULL,
	transport_available integer DEFAULT 0 NOT NULL,
	direct_solicitation_enabled integer DEFAULT 1 NOT NULL,
	verification_status integer DEFAULT 0 NOT NULL,
	status integer DEFAULT 0 NOT NULL,
	date_last_confirmation datetime NULL,
	date_expiration datetime NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NULL,
	fk_user_modif integer NULL,
	import_key varchar(14) NULL,
	note_public text NULL,
	note_private text NULL,
	model_pdf varchar(255) NULL,
	last_main_doc varchar(255) NULL,
	UNIQUE KEY uk_emergencyhouse_offer_ref (entity, ref),
	UNIQUE KEY uk_emergencyhouse_offer_uuid (public_uuid),
	KEY idx_emergencyhouse_offer_campaign_status (entity, fk_campaign, status),
	KEY idx_emergencyhouse_offer_account (entity, fk_account),
	KEY idx_emergencyhouse_offer_dates (entity, date_start, date_end),
	KEY idx_emergencyhouse_offer_capacity (entity, capacity_available),
	KEY idx_emergencyhouse_offer_geo (entity, fk_campaign, geo_cell),
	KEY idx_emergencyhouse_offer_type (fk_housing_type),
	KEY idx_emergencyhouse_offer_expiry (entity, date_expiration)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_offer_feature (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_offer integer NOT NULL,
	fk_feature integer NOT NULL,
	value_code varchar(64) NULL,
	value_number double(24,8) NULL,
	date_creation datetime NOT NULL,
	UNIQUE KEY uk_emergencyhouse_offer_feature (entity, fk_offer, fk_feature),
	KEY idx_emergencyhouse_offer_feature_feature (entity, fk_feature)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_offer_photo (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_offer integer NOT NULL,
	file_name varchar(255) NOT NULL,
	file_hash varchar(128) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	status integer DEFAULT 0 NOT NULL,
	date_creation datetime NOT NULL,
	fk_user_creat integer NULL,
	UNIQUE KEY uk_emergencyhouse_offer_photo_hash (entity, fk_offer, file_hash),
	KEY idx_emergencyhouse_offer_photo_offer (fk_offer)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_request (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	ref varchar(128) NOT NULL,
	public_uuid varchar(64) NOT NULL,
	fk_campaign integer NOT NULL,
	fk_account integer NOT NULL,
	adults_count integer NOT NULL,
	children_infant_count integer DEFAULT 0 NOT NULL,
	children_young_count integer DEFAULT 0 NOT NULL,
	children_teen_count integer DEFAULT 0 NOT NULL,
	person_count integer NOT NULL,
	remaining_count integer NOT NULL,
	group_divisible integer DEFAULT 0 NOT NULL,
	minimum_group_size integer DEFAULT 1 NOT NULL,
	date_start datetime NOT NULL,
	date_end datetime NULL,
	duration_unknown integer DEFAULT 0 NOT NULL,
	desired_zone varchar(255) NOT NULL,
	desired_zip varchar(25) NULL,
	desired_town varchar(255) NULL,
	search_radius integer DEFAULT 50 NOT NULL,
	geo_cell varchar(32) NULL,
	pickup_location_encrypted longtext NULL,
	transport_mode varchar(32) NULL,
	pickup_possible integer DEFAULT 0 NOT NULL,
	urgency_level integer DEFAULT 0 NOT NULL,
	title varchar(255) NOT NULL,
	description_public text NULL,
	private_note_encrypted longtext NULL,
	visibility varchar(16) DEFAULT 'private' NOT NULL,
	verification_status integer DEFAULT 0 NOT NULL,
	status integer DEFAULT 0 NOT NULL,
	date_expiration datetime NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NULL,
	fk_user_modif integer NULL,
	import_key varchar(14) NULL,
	note_public text NULL,
	note_private text NULL,
	model_pdf varchar(255) NULL,
	last_main_doc varchar(255) NULL,
	UNIQUE KEY uk_emergencyhouse_request_ref (entity, ref),
	UNIQUE KEY uk_emergencyhouse_request_uuid (public_uuid),
	KEY idx_emergencyhouse_request_campaign_status (entity, fk_campaign, status),
	KEY idx_emergencyhouse_request_account (entity, fk_account),
	KEY idx_emergencyhouse_request_dates (entity, date_start, date_end),
	KEY idx_emergencyhouse_request_remaining (entity, remaining_count),
	KEY idx_emergencyhouse_request_geo (entity, fk_campaign, geo_cell),
	KEY idx_emergencyhouse_request_expiry (entity, date_expiration)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_request_housing_type (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_request integer NOT NULL,
	fk_housing_type integer NOT NULL,
	preference_level varchar(16) DEFAULT 'wanted' NOT NULL,
	date_creation datetime NOT NULL,
	UNIQUE KEY uk_emergencyhouse_request_type (entity, fk_request, fk_housing_type),
	KEY idx_emergencyhouse_request_type_type (entity, fk_housing_type)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_request_criterion (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_request integer NOT NULL,
	fk_feature integer NOT NULL,
	criterion_level varchar(16) NOT NULL,
	expected_code varchar(64) NULL,
	expected_number double(24,8) NULL,
	date_creation datetime NOT NULL,
	UNIQUE KEY uk_emergencyhouse_request_criterion (entity, fk_request, fk_feature),
	KEY idx_emergencyhouse_request_criterion_feature (entity, fk_feature, criterion_level)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_match (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_campaign integer NOT NULL,
	fk_offer integer NOT NULL,
	fk_request integer NOT NULL,
	algorithm_version varchar(32) NOT NULL,
	parameters_version varchar(32) NOT NULL,
	score_total double(8,4) NOT NULL,
	score_class varchar(16) NOT NULL,
	score_distance double(8,4) NOT NULL,
	score_capacity double(8,4) NOT NULL,
	score_dates double(8,4) NOT NULL,
	score_type double(8,4) NOT NULL,
	score_features double(8,4) NOT NULL,
	distance_km double(24,8) NULL,
	capacity_evaluated integer NOT NULL,
	nights_requested integer NULL,
	nights_covered integer NULL,
	explanation_snapshot longtext NOT NULL,
	warnings_snapshot longtext NULL,
	status integer DEFAULT 1 NOT NULL,
	date_calculation datetime NOT NULL,
	date_invalidation datetime NULL,
	UNIQUE KEY uk_emergencyhouse_match_version (entity, fk_offer, fk_request, algorithm_version, parameters_version),
	KEY idx_emergencyhouse_match_request_score (entity, fk_request, status, score_total),
	KEY idx_emergencyhouse_match_offer_score (entity, fk_offer, status, score_total),
	KEY idx_emergencyhouse_match_campaign (entity, fk_campaign, status)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_job (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	job_type varchar(64) NOT NULL,
	object_type varchar(64) NULL,
	fk_object integer NULL,
	fk_campaign integer NULL,
	idempotency_key varchar(128) NOT NULL,
	payload_snapshot longtext NULL,
	status integer DEFAULT 0 NOT NULL,
	priority integer DEFAULT 50 NOT NULL,
	attempt_count integer DEFAULT 0 NOT NULL,
	next_attempt datetime NOT NULL,
	locked_at datetime NULL,
	lock_token varchar(128) NULL,
	last_error_code varchar(128) NULL,
	date_creation datetime NOT NULL,
	date_completed datetime NULL,
	UNIQUE KEY uk_emergencyhouse_job_idempotency (entity, idempotency_key),
	KEY idx_emergencyhouse_job_queue (entity, job_type, status, next_attempt, priority),
	KEY idx_emergencyhouse_job_object (entity, object_type, fk_object)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_solicitation (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	ref varchar(128) NOT NULL,
	public_uuid varchar(64) NOT NULL,
	fk_campaign integer NOT NULL,
	fk_offer integer NOT NULL,
	fk_request integer NOT NULL,
	fk_match integer NULL,
	fk_initiator_account integer NULL,
	fk_initiator_user integer NULL,
	initiator_direction varchar(16) NOT NULL,
	status integer DEFAULT 0 NOT NULL,
	initial_message_encrypted longtext NULL,
	confirmed_gaps_snapshot longtext NULL,
	refusal_reason varchar(64) NULL,
	cancellation_reason varchar(64) NULL,
	initiator_contact_consent integer DEFAULT 1 NOT NULL,
	recipient_contact_consent integer DEFAULT 0 NOT NULL,
	address_share_authorized integer DEFAULT 0 NOT NULL,
	date_read datetime NULL,
	date_response datetime NULL,
	date_contact_revealed datetime NULL,
	date_address_revealed datetime NULL,
	date_closure datetime NULL,
	date_expiration datetime NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NULL,
	fk_user_modif integer NULL,
	UNIQUE KEY uk_emergencyhouse_solicitation_ref (entity, ref),
	UNIQUE KEY uk_emergencyhouse_solicitation_uuid (public_uuid),
	KEY idx_emergencyhouse_solicitation_pair (entity, fk_offer, fk_request, status),
	KEY idx_emergencyhouse_solicitation_campaign (entity, fk_campaign, status),
	KEY idx_emergencyhouse_solicitation_initiator (entity, fk_initiator_account),
	KEY idx_emergencyhouse_solicitation_expiry (entity, date_expiration)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_message (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	public_uuid varchar(64) NOT NULL,
	fk_solicitation integer NOT NULL,
	fk_author_account integer NULL,
	fk_author_user integer NULL,
	message_type varchar(16) DEFAULT 'user' NOT NULL,
	body_encrypted longtext NOT NULL,
	body_fingerprint varchar(128) NULL,
	moderation_status integer DEFAULT 0 NOT NULL,
	date_creation datetime NOT NULL,
	date_read datetime NULL,
	date_deleted datetime NULL,
	UNIQUE KEY uk_emergencyhouse_message_uuid (public_uuid),
	KEY idx_emergencyhouse_message_thread (entity, fk_solicitation, date_creation),
	KEY idx_emergencyhouse_message_author (entity, fk_author_account)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_allocation (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	ref varchar(128) NOT NULL,
	public_uuid varchar(64) NOT NULL,
	fk_campaign integer NOT NULL,
	fk_offer integer NOT NULL,
	fk_request integer NOT NULL,
	fk_solicitation integer NULL,
	quantity integer NOT NULL,
	subgroup_code varchar(64) NULL,
	date_start datetime NOT NULL,
	date_end datetime NULL,
	actual_start datetime NULL,
	actual_end datetime NULL,
	status integer DEFAULT 0 NOT NULL,
	host_confirmed integer DEFAULT 0 NOT NULL,
	requester_confirmed integer DEFAULT 0 NOT NULL,
	host_confirmation_date datetime NULL,
	requester_confirmation_date datetime NULL,
	address_share_authorized integer DEFAULT 0 NOT NULL,
	cancellation_reason varchar(64) NULL,
	incident_open integer DEFAULT 0 NOT NULL,
	fk_operator integer NULL,
	idempotency_key varchar(128) NOT NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NULL,
	fk_user_modif integer NULL,
	import_key varchar(14) NULL,
	note_public text NULL,
	note_private text NULL,
	model_pdf varchar(255) NULL,
	last_main_doc varchar(255) NULL,
	UNIQUE KEY uk_emergencyhouse_allocation_ref (entity, ref),
	UNIQUE KEY uk_emergencyhouse_allocation_uuid (public_uuid),
	UNIQUE KEY uk_emergencyhouse_allocation_idem (entity, idempotency_key),
	KEY idx_emergencyhouse_allocation_offer_dates (entity, fk_offer, status, date_start, date_end),
	KEY idx_emergencyhouse_allocation_request (entity, fk_request, status),
	KEY idx_emergencyhouse_allocation_campaign (entity, fk_campaign, status),
	KEY idx_emergencyhouse_allocation_operator (fk_operator)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_verification (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	object_type varchar(64) NOT NULL,
	fk_object integer NOT NULL,
	verification_type varchar(64) NOT NULL,
	fk_verification_level integer NOT NULL,
	status integer DEFAULT 0 NOT NULL,
	method_code varchar(64) NULL,
	private_note_encrypted longtext NULL,
	fk_operator integer NULL,
	date_creation datetime NOT NULL,
	date_verified datetime NULL,
	date_expiration datetime NULL,
	KEY idx_emergencyhouse_verification_object (entity, object_type, fk_object),
	KEY idx_emergencyhouse_verification_queue (entity, status, date_creation),
	KEY idx_emergencyhouse_verification_operator (fk_operator)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_verification_queue (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	object_type varchar(64) NOT NULL,
	fk_object integer NOT NULL,
	queue_status integer DEFAULT 0 NOT NULL,
	fk_assigned_user integer NULL,
	date_queued datetime NOT NULL,
	date_assigned datetime NULL,
	date_completed datetime NULL,
	fk_verification integer NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uk_emergencyhouse_verification_queue_object (entity, object_type, fk_object),
	KEY idx_emergencyhouse_verification_queue_fifo (entity, queue_status, date_queued, rowid),
	KEY idx_emergencyhouse_verification_queue_user (entity, queue_status, fk_assigned_user, date_queued),
	KEY idx_emergencyhouse_verification_queue_result (fk_verification)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_verification_rotation (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_last_user integer NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uk_emergencyhouse_verification_rotation_entity (entity)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_report (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	ref varchar(128) NOT NULL,
	public_uuid varchar(64) NOT NULL,
	fk_campaign integer NOT NULL,
	object_type varchar(64) NOT NULL,
	fk_object integer NOT NULL,
	fk_reporter_account integer NULL,
	fk_reporter_user integer NULL,
	fk_report_reason integer NOT NULL,
	severity integer DEFAULT 1 NOT NULL,
	status integer DEFAULT 0 NOT NULL,
	description_encrypted longtext NULL,
	private_notes_encrypted longtext NULL,
	fk_assigned_user integer NULL,
	retention_hold integer DEFAULT 0 NOT NULL,
	retention_hold_reason varchar(255) NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	date_closure datetime NULL,
	UNIQUE KEY uk_emergencyhouse_report_ref (entity, ref),
	UNIQUE KEY uk_emergencyhouse_report_uuid (public_uuid),
	KEY idx_emergencyhouse_report_queue (entity, status, severity, date_creation),
	KEY idx_emergencyhouse_report_object (entity, object_type, fk_object),
	KEY idx_emergencyhouse_report_assigned (fk_assigned_user)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_moderation_action (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_moderation_action integer NOT NULL,
	target_type varchar(64) NOT NULL,
	fk_target integer NOT NULL,
	fk_operator integer NOT NULL,
	reason_code varchar(64) NULL,
	private_note_encrypted longtext NULL,
	date_start datetime NOT NULL,
	date_end datetime NULL,
	date_reversed datetime NULL,
	fk_reversed_by integer NULL,
	KEY idx_emergencyhouse_moderation_report (entity, fk_report),
	KEY idx_emergencyhouse_moderation_target (entity, target_type, fk_target),
	KEY idx_emergencyhouse_moderation_operator (fk_operator)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_notification (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_campaign integer NULL,
	fk_account integer NULL,
	channel varchar(16) NOT NULL,
	event_code varchar(128) NOT NULL,
	template_code varchar(128) NOT NULL,
	recipient_encrypted longtext NOT NULL,
	locale varchar(8) DEFAULT 'fr_FR' NOT NULL,
	payload_encrypted longtext NOT NULL,
	idempotency_key varchar(128) NOT NULL,
	priority integer DEFAULT 50 NOT NULL,
	status integer DEFAULT 0 NOT NULL,
	attempt_count integer DEFAULT 0 NOT NULL,
	next_attempt datetime NOT NULL,
	locked_at datetime NULL,
	lock_token varchar(128) NULL,
	last_error_code varchar(128) NULL,
	date_creation datetime NOT NULL,
	date_sent datetime NULL,
	UNIQUE KEY uk_emergencyhouse_notification_idem (entity, idempotency_key),
	KEY idx_emergencyhouse_notification_queue (entity, channel, status, next_attempt, priority),
	KEY idx_emergencyhouse_notification_account (entity, fk_account)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_notification_template (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_campaign integer DEFAULT 0 NOT NULL,
	template_code varchar(128) NOT NULL,
	channel varchar(16) NOT NULL,
	lang varchar(8) NOT NULL,
	subject_template text NULL,
	body_template longtext NOT NULL,
	is_mandatory integer DEFAULT 0 NOT NULL,
	status integer DEFAULT 1 NOT NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NULL,
	fk_user_modif integer NULL,
	UNIQUE KEY uk_emergencyhouse_notification_template (entity, fk_campaign, template_code, channel, lang)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_audit (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_campaign integer NULL,
	actor_type varchar(32) NOT NULL,
	fk_actor integer NULL,
	action_code varchar(128) NOT NULL,
	object_type varchar(64) NOT NULL,
	fk_object integer NOT NULL,
	justification_code varchar(128) NULL,
	ip_hash varchar(128) NULL,
	metadata_snapshot text NULL,
	date_creation datetime NOT NULL,
	KEY idx_emergencyhouse_audit_object (entity, object_type, fk_object, date_creation),
	KEY idx_emergencyhouse_audit_actor (entity, actor_type, fk_actor, date_creation),
	KEY idx_emergencyhouse_audit_campaign (entity, fk_campaign, date_creation),
	KEY idx_emergencyhouse_audit_action (entity, action_code, date_creation)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_rate_limit (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	key_hash varchar(128) NOT NULL,
	scope varchar(64) NOT NULL,
	window_start datetime NOT NULL,
	window_seconds integer NOT NULL,
	hit_count integer DEFAULT 0 NOT NULL,
	blocked_until datetime NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uk_emergencyhouse_rate_window (entity, key_hash, scope, window_start),
	KEY idx_emergencyhouse_rate_cleanup (entity, window_start)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_geo_cache (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	provider_code varchar(64) NOT NULL,
	address_hash varchar(128) NOT NULL,
	result_encrypted longtext NOT NULL,
	public_zone varchar(255) NULL,
	geo_cell varchar(32) NULL,
	date_creation datetime NOT NULL,
	expires_at datetime NOT NULL,
	UNIQUE KEY uk_emergencyhouse_geo_cache (entity, provider_code, address_hash),
	KEY idx_emergencyhouse_geo_cache_expiry (entity, expires_at)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_stat_daily (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_campaign integer NOT NULL,
	metric_date date NOT NULL,
	metric_code varchar(128) NOT NULL,
	dimension_code varchar(128) DEFAULT '' NOT NULL,
	metric_value double(24,8) NOT NULL,
	date_calculation datetime NOT NULL,
	UNIQUE KEY uk_emergencyhouse_stat_daily (entity, fk_campaign, metric_date, metric_code, dimension_code),
	KEY idx_emergencyhouse_stat_campaign_date (entity, fk_campaign, metric_date)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_external_link (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	source_type varchar(64) NOT NULL,
	fk_source integer NOT NULL,
	target_module varchar(64) NOT NULL,
	target_type varchar(64) NOT NULL,
	fk_target integer NOT NULL,
	sync_mode varchar(32) DEFAULT 'none' NOT NULL,
	sync_direction varchar(16) DEFAULT 'none' NOT NULL,
	legal_basis_code varchar(64) NULL,
	fk_consent integer NULL,
	status integer DEFAULT 1 NOT NULL,
	idempotency_key varchar(128) NOT NULL,
	last_checked_at datetime NULL,
	last_error_code varchar(128) NULL,
	date_creation datetime NOT NULL,
	date_unlinked datetime NULL,
	fk_user_creat integer NULL,
	fk_user_unlink integer NULL,
	UNIQUE KEY uk_emergencyhouse_external_link (entity, source_type, fk_source, target_module, target_type, fk_target),
	UNIQUE KEY uk_emergencyhouse_external_link_idem (entity, idempotency_key),
	KEY idx_emergencyhouse_external_target (entity, target_module, target_type, fk_target)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_offer_resource (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_offer integer NOT NULL,
	fk_resource integer NOT NULL,
	resource_role varchar(64) NOT NULL,
	contributed_capacity integer NULL,
	consider_unavailability integer DEFAULT 1 NOT NULL,
	status integer DEFAULT 1 NOT NULL,
	last_assignment_idempotency_key varchar(128) NULL,
	last_checked_at datetime NULL,
	last_error_code varchar(128) NULL,
	date_creation datetime NOT NULL,
	fk_user_creat integer NULL,
	UNIQUE KEY uk_emergencyhouse_offer_resource (entity, fk_offer, fk_resource, resource_role),
	KEY idx_emergencyhouse_offer_resource_resource (entity, fk_resource)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_api_key (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	label varchar(255) NOT NULL,
	key_prefix varchar(32) NOT NULL,
	key_hash varchar(128) NOT NULL,
	scopes varchar(1024) NOT NULL,
	fk_campaign integer NULL,
	status integer DEFAULT 1 NOT NULL,
	last_used_at datetime NULL,
	expires_at datetime NULL,
	date_creation datetime NOT NULL,
	fk_user_creat integer NOT NULL,
	UNIQUE KEY uk_emergencyhouse_api_key_hash (key_hash),
	KEY idx_emergencyhouse_api_key_scope (entity, status, expires_at)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_emergencyhouse_webhook (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	label varchar(255) NOT NULL,
	endpoint_url varchar(1024) NOT NULL,
	secret_env_name varchar(128) NOT NULL,
	event_codes varchar(1024) NOT NULL,
	fk_campaign integer NULL,
	status integer DEFAULT 0 NOT NULL,
	last_delivery_at datetime NULL,
	last_error_code varchar(128) NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer NULL,
	KEY idx_emergencyhouse_webhook_status (entity, status),
	KEY idx_emergencyhouse_webhook_campaign (entity, fk_campaign)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_c_emergencyhouse_campaign_type (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active integer DEFAULT 1 NOT NULL,
	UNIQUE KEY uk_c_emergencyhouse_campaign_type (entity, code)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_c_emergencyhouse_housing_type (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active integer DEFAULT 1 NOT NULL,
	UNIQUE KEY uk_c_emergencyhouse_housing_type (entity, code)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_c_emergencyhouse_feature (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	feature_group varchar(64) NOT NULL,
	value_type varchar(16) DEFAULT 'boolean' NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active integer DEFAULT 1 NOT NULL,
	UNIQUE KEY uk_c_emergencyhouse_feature (entity, code),
	KEY idx_c_emergencyhouse_feature_group (entity, feature_group, active)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_c_emergencyhouse_animal_type (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active integer DEFAULT 1 NOT NULL,
	UNIQUE KEY uk_c_emergencyhouse_animal_type (entity, code)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_c_emergencyhouse_verification_level (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active integer DEFAULT 1 NOT NULL,
	UNIQUE KEY uk_c_emergencyhouse_verification (entity, code)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_c_emergencyhouse_refusal_reason (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active integer DEFAULT 1 NOT NULL,
	UNIQUE KEY uk_c_emergencyhouse_refusal (entity, code)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_c_emergencyhouse_cancellation_reason (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active integer DEFAULT 1 NOT NULL,
	UNIQUE KEY uk_c_emergencyhouse_cancellation (entity, code)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_c_emergencyhouse_report_reason (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active integer DEFAULT 1 NOT NULL,
	UNIQUE KEY uk_c_emergencyhouse_report_reason (entity, code)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_c_emergencyhouse_moderation_action (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active integer DEFAULT 1 NOT NULL,
	UNIQUE KEY uk_c_emergencyhouse_moderation (entity, code)
) ENGINE=innodb;
