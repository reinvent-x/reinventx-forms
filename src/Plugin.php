<?php
declare(strict_types=1);

namespace Reinventx;

use Reinventx\Admin\Menu;
use Reinventx\Database\Migrator;
use Reinventx\Database\Schema;
use Reinventx\Database\Tables;
use Reinventx\Forms\FieldTypes\FieldTypeRegistry;
use Reinventx\Forms\FormRepository;
use Reinventx\Http\FormsController;
use Reinventx\Http\LeadsController;
use Reinventx\Http\SettingsController;
use Reinventx\Http\SubmissionsController;
use Reinventx\Leads\LeadNoteRepository;
use Reinventx\Leads\LeadRepository;
use Reinventx\Leads\LeadStatusService;
use Reinventx\Privacy\PersonalData;
use Reinventx\Rendering\FormEmbed;
use Reinventx\Rendering\FormRenderer;
use Reinventx\Rendering\Shortcode;
use Reinventx\Submissions\RateLimiter;
use Reinventx\Submissions\SubmissionHandler;
use Reinventx\Submissions\SubmissionToken;
use Reinventx\Submissions\SubmissionValidator;

/**
 * Composition root. Builds the plugin's services and registers their hooks.
 *
 * Keep this the only place that wires concrete implementations together.
 * No business logic lives here.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private function __construct( private readonly string $file ) {
	}

	public static function boot( string $file ): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new Plugin( $file );
			self::$instance->register();
		}

		return self::$instance;
	}

	public function file(): string {
		return $this->file;
	}

	public function dir(): string {
		return plugin_dir_path( $this->file );
	}

	public function url(): string {
		return plugin_dir_url( $this->file );
	}

	private function register(): void {
		( new Menu( $this ) )->register();

		// Run pending schema migrations after a plugin update. Admin-only on
		// purpose: the public request path must never pay for upgrade checks.
		add_action( 'admin_init', array( $this, 'maybeUpgrade' ) );

		add_action( 'rest_api_init', array( $this, 'registerRestRoutes' ) );
		add_action( 'init', array( $this, 'registerEmbeds' ) );

		// Leads are personal data, so core's Tools → Export/Erase Personal
		// Data must be able to reach them. Registered on init because the
		// repositories need $wpdb->prefix to be settled.
		add_action( 'init', array( $this, 'registerPrivacyHooks' ) );
	}

	public function registerPrivacyHooks(): void {
		global $wpdb;

		$tables = new Tables( $wpdb->prefix );

		( new PersonalData(
			new LeadRepository( $wpdb, $tables ),
			new LeadNoteRepository( $wpdb, $tables ),
			new FormRepository( $wpdb, $tables, FieldTypeRegistry::withDefaults() )
		) )->register();
	}

	public function registerRestRoutes(): void {
		global $wpdb;

		$tables = new Tables( $wpdb->prefix );
		$types  = FieldTypeRegistry::withDefaults();
		$forms  = new FormRepository( $wpdb, $tables, $types );
		$leads  = new LeadRepository( $wpdb, $tables );

		( new FormsController( $forms, $types ) )->registerRoutes();
		( new SubmissionsController( self::submissionHandler() ) )->registerRoutes();
		( new LeadsController(
			$leads,
			new LeadNoteRepository( $wpdb, $tables ),
			$forms,
			new LeadStatusService( $leads )
		) )->registerRoutes();
		( new SettingsController() )->registerRoutes();
	}

	public function registerEmbeds(): void {
		$embed = $this->formEmbed();

		( new Shortcode( $embed ) )->register();

		$asset_file = $this->dir() . 'build/block-form.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;

			wp_register_script(
				'rvtx-block-form',
				$this->url() . 'build/block-form.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);
		}

		register_block_type(
			$this->dir() . 'blocks/form',
			array(
				'render_callback' => static function ( array $attributes ) use ( $embed ): string {
					return $embed->render( (int) ( $attributes['formId'] ?? 0 ) );
				},
			)
		);
	}

	private function formEmbed(): FormEmbed {
		global $wpdb;

		$types = FieldTypeRegistry::withDefaults();
		$forms = new FormRepository( $wpdb, new Tables( $wpdb->prefix ), $types );

		return new FormEmbed(
			$this,
			$forms,
			self::submissionHandler(),
			new FormRenderer( self::submissionToken() )
		);
	}

	/**
	 * Factory for the shared submission pipeline (REST + no-JS fallback).
	 */
	public static function submissionHandler(): SubmissionHandler {
		global $wpdb;

		$tables = new Tables( $wpdb->prefix );
		$types  = FieldTypeRegistry::withDefaults();

		return new SubmissionHandler(
			new FormRepository( $wpdb, $tables, $types ),
			new SubmissionValidator( $types ),
			self::submissionToken(),
			new RateLimiter(),
			new LeadRepository( $wpdb, $tables )
		);
	}

	public static function submissionToken(): SubmissionToken {
		return new SubmissionToken( wp_salt( 'nonce' ) );
	}

	public function maybeUpgrade(): void {
		$migrator = self::migrator();

		if ( $migrator->needsMigration() ) {
			$migrator->migrate();
		}
	}

	/**
	 * Factory for the migrator, shared by runtime upgrades and activation.
	 */
	public static function migrator(): Migrator {
		global $wpdb;

		return new Migrator( $wpdb, new Schema( new Tables( $wpdb->prefix ) ) );
	}
}
