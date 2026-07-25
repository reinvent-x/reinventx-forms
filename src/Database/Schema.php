<?php
declare(strict_types=1);

namespace Reinventx\Database;

/**
 * Schema v1 table definitions in dbDelta-compatible form.
 *
 * dbDelta is picky: one column per line, two spaces after PRIMARY KEY,
 * KEY (not INDEX), and no backticks. Do not "clean up" the formatting.
 */
final class Schema {

	public function __construct( private readonly Tables $tables ) {
	}

	/**
	 * CREATE TABLE statements keyed by table name.
	 *
	 * @param string $charset_collate Charset/collation clause from wpdb.
	 * @return array<string, string>
	 */
	public function createTableStatements( string $charset_collate ): array {
		$forms      = $this->tables->forms();
		$leads      = $this->tables->leads();
		$lead_notes = $this->tables->leadNotes();

		return array(
			$forms      => "CREATE TABLE {$forms} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	name varchar(190) NOT NULL,
	status varchar(20) NOT NULL DEFAULT 'active',
	config longtext NOT NULL,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY status (status)
) {$charset_collate}",
			$leads      => "CREATE TABLE {$leads} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	form_id bigint(20) unsigned NOT NULL,
	status varchar(20) NOT NULL DEFAULT 'new',
	data longtext NOT NULL,
	source_url text NULL,
	source_title text NULL,
	referrer_url text NULL,
	user_agent varchar(255) NULL,
	ip_hash varchar(64) NULL,
	submitted_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY form_status (form_id,status),
	KEY submitted_at (submitted_at)
) {$charset_collate}",
			$lead_notes => "CREATE TABLE {$lead_notes} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	lead_id bigint(20) unsigned NOT NULL,
	user_id bigint(20) unsigned NOT NULL,
	note text NOT NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY lead_id (lead_id)
) {$charset_collate}",
		);
	}
}
