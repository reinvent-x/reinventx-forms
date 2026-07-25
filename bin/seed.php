<?php
/**
 * Development fixture seeder. Not shipped (see .distignore) and never
 * loaded by the plugin — run it manually against a dev site.
 *
 * No declare(strict_types) on purpose: `wp eval-file` wraps the file in
 * eval(), where a declare after the first statement is a fatal error.
 *
 * Run:
 *
 *   wp eval-file wp-content/plugins/reinventx-forms/bin/seed.php
 *
 * Creates realistic demo content — three forms and a spread of leads with
 * statuses, source context, and notes — suitable for exercising inbox
 * filtering/pagination and for taking directory screenshots.
 */

use Reinventx\Database\Tables;
use Reinventx\Forms\FieldTypes\FieldTypeRegistry;
use Reinventx\Forms\FormConfig;
use Reinventx\Forms\FormRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Run via: wp eval-file bin/seed.php' . PHP_EOL );
}

global $wpdb;

$tables = new Tables( $wpdb->prefix );
$types  = FieldTypeRegistry::withDefaults();
$forms  = new FormRepository( $wpdb, $tables, $types );

$field = static function ( string $id, string $type, string $label, bool $required = false ): array {
	return array(
		'id'       => $id,
		'type'     => $type,
		'label'    => $label,
		'required' => $required,
	);
};

$contact = $forms->insert(
	'Contact us',
	FormConfig::fromArray(
		array(
			'fields' => array(
				$field( 'name', 'text', 'Your name', true ),
				$field( 'email', 'email', 'Email address', true ),
				$field( 'message', 'textarea', 'How can we help?' ),
			),
		),
		$types
	)
);

$quote = $forms->insert(
	'Request a quote',
	FormConfig::fromArray(
		array(
			'fields' => array(
				$field( 'name', 'text', 'Contact person', true ),
				$field( 'email', 'email', 'Work email', true ),
				$field( 'company', 'text', 'Company' ),
				$field( 'details', 'textarea', 'What do you need?', true ),
			),
		),
		$types
	)
);

$newsletter = $forms->insert(
	'Newsletter signup',
	FormConfig::fromArray(
		array(
			'fields' => array(
				$field( 'name', 'text', 'First name' ),
				$field( 'email', 'email', 'Email address', true ),
			),
		),
		$types
	)
);

/**
 * Each row: form, hours ago, status, data, source title, referrer.
 *
 * @var array<int, array{0: object, 1: int, 2: string, 3: array<string, string>, 4: string, 5: ?string}>
 */
$leads = array(
	array(
		$contact,
		2,
		'new',
		array(
			'name'    => 'Sarah Mitchell',
			'email'   => 'sarah@mitchellbakery.com',
			'message' => "Hi — we're redoing our bakery's website and the current contact form loses messages. Do you offer setup help for small shops?",
		),
		'Contact us',
		'https://www.google.com/search?q=wordpress+lead+inbox',
	),
	array(
		$contact,
		7,
		'new',
		array(
			'name'    => 'Daniel Okafor',
			'email'   => 'd.okafor@brightpath.co',
			'message' => 'Interested in using Reinventx Forms on three client sites. Is there a limit on the number of forms?',
		),
		'Contact us',
		null,
	),
	array(
		$quote,
		11,
		'contacted',
		array(
			'name'    => 'Emma Larsen',
			'email'   => 'emma@larsenstudio.dk',
			'company' => 'Larsen Studio',
			'details' => "Portfolio site relaunch for a design studio.\nWe need a contact form plus a project-inquiry form with follow-up tracking.\nTimeline: 6 weeks.",
		),
		'Request a quote',
		'https://www.linkedin.com/feed/',
	),
	array(
		$contact,
		26,
		'contacted',
		array(
			'name'    => 'Miguel Torres',
			'email'   => 'miguel.torres@solarhaus.mx',
			'message' => 'We get about 40 inquiries a month through our site. Can statuses be customized later?',
		),
		'Contact us',
		'https://www.google.com/search?q=contact+form+crm+wordpress',
	),
	array(
		$quote,
		30,
		'qualified',
		array(
			'name'    => 'Priya Raman',
			'email'   => 'priya@verdantlandscapes.in',
			'company' => 'Verdant Landscapes',
			'details' => 'Quote for a seasonal campaign microsite with a lead capture form. Budget approved, looking to start next month.',
		),
		'Request a quote',
		null,
	),
	array(
		$newsletter,
		33,
		'new',
		array(
			'name'  => 'Tom',
			'email' => 'tom.becker@fastmail.com',
		),
		'Newsletter signup',
		'https://twitter.com/',
	),
	array(
		$contact,
		49,
		'won',
		array(
			'name'    => 'Alice Fontaine',
			'email'   => 'alice@fontaine-avocats.fr',
			'message' => 'Following up on our call — please send the onboarding details for the law firm site.',
		),
		'Contact us',
		null,
	),
	array(
		$newsletter,
		55,
		'new',
		array(
			'name'  => 'Grace',
			'email' => 'grace.w@outlook.com',
		),
		'Newsletter signup',
		null,
	),
	array(
		$quote,
		73,
		'lost',
		array(
			'name'    => 'Henrik Vos',
			'email'   => 'h.vos@vosmedia.nl',
			'company' => 'Vos Media',
			'details' => 'One-page site with a signup form. Decided to build in-house for now, but keep us on the list.',
		),
		'Request a quote',
		'https://duckduckgo.com/',
	),
	array(
		$contact,
		80,
		'spam',
		array(
			'name'    => 'crypto winner',
			'email'   => 'promo@example-spam.biz',
			'message' => 'CONGRATULATIONS you have been selected. Click here to claim.',
		),
		'Contact us',
		null,
	),
	array(
		$contact,
		96,
		'qualified',
		array(
			'name'    => 'Leah Gordon',
			'email'   => 'leah@gordonphysio.ca',
			'message' => 'Clinic with two locations. We want each location page to have its own form but one shared inbox — is that supported?',
		),
		'Contact us',
		'https://www.bing.com/search?q=reinventx-forms',
	),
	array(
		$newsletter,
		120,
		'new',
		array(
			'name'  => 'Jonas',
			'email' => 'jonas.k@proton.me',
		),
		'Newsletter signup',
		null,
	),
);

$agents = array(
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
	'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Safari/604.1',
);

$first_lead_id = 0;

foreach ( $leads as $i => $row ) {
	list( $form, $hours_ago, $status, $data, $title, $referrer ) = $row;

	$wpdb->insert(
		$tables->leads(),
		array(
			'form_id'      => $form->id,
			'status'       => $status,
			'data'         => (string) wp_json_encode( $data ),
			'source_url'   => home_url( '/' . sanitize_title( $title ) . '/' ),
			'source_title' => $title,
			'referrer_url' => $referrer,
			'user_agent'   => $agents[ $i % count( $agents ) ],
			'ip_hash'      => hash( 'sha256', 'seed-' . $i ),
			'submitted_at' => gmdate( 'Y-m-d H:i:s', time() - $hours_ago * 3600 ),
		)
	);

	if ( 0 === $first_lead_id ) {
		$first_lead_id = (int) $wpdb->insert_id;
	}
}

// A short follow-up trail on the qualified quote lead (nice for the
// lead-detail screenshot). Attribute to the first administrator.
$admins    = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
$author_id = array() !== $admins ? (int) $admins[0]->ID : 1;

$priya_id = (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT id FROM {$tables->leads()} WHERE status = %s ORDER BY id ASC LIMIT 1", 'qualified' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
);

foreach ( array(
	array( 28, 'Called — budget confirmed, decision maker is Priya herself. Sending proposal draft tomorrow.' ),
	array( 5, 'Proposal sent. She asked about maintenance plans; follow up on Thursday.' ),
) as $note ) {
	$wpdb->insert(
		$tables->leadNotes(),
		array(
			'lead_id'    => $priya_id,
			'user_id'    => $author_id,
			'note'       => $note[1],
			'created_at' => gmdate( 'Y-m-d H:i:s', time() - $note[0] * 3600 ),
		)
	);
}

echo sprintf(
	'Seeded 3 forms (#%d Contact us, #%d Request a quote, #%d Newsletter signup), %d leads, 2 notes.%s',
	$contact->id,
	$quote->id,
	$newsletter->id,
	count( $leads ),
	PHP_EOL
);
