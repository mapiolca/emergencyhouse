-- Emergency House housing types dictionary exposed to Multicompany.
CREATE TABLE IF NOT EXISTS llx_c_emergencyhouse_housing_type (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active integer DEFAULT 1 NOT NULL,
	UNIQUE KEY uk_c_emergencyhouse_housing_type (entity, code)
) ENGINE=innodb;
