<?php
/**
 * Thrown by a factory (or recipe setup) to opt a recipe OUT of running — it
 * becomes a SKIP, not a failure. Used by generated stub factories ("implement
 * me") and for soft dependencies ("plugin X not installed, skip this recipe").
 *
 * @package Zaplane
 */

namespace Zaplane\Testing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecipeSkip extends \RuntimeException {}
