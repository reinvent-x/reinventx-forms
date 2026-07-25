<?php
declare(strict_types=1);

namespace Reinventx\Forms;

/**
 * Lifecycle status of a form.
 *
 * Forms are archived, never deleted: leads keep a valid form_id forever.
 */
enum FormStatus: string {
	case Active   = 'active';
	case Archived = 'archived';
}
