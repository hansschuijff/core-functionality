<?php
/**
 * Course Fields for the AI Access abilities.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\CourseContent
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\CourseContent;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces\FieldHandlerInterface;
use Override;
use Sensei_Course_Structure;
use WP_Error;
use WP_Post;
use WP_Term;

use function __;
use function array_merge;
use function array_search;
use function array_splice;
use function array_values;
use function count;
use function current_user_can;
use function get_post_meta;
use function get_post_type;
use function get_the_terms;
use function in_array;
use function is_array;
use function is_wp_error;
use function max;
use function min;
use function sprintf;

/**
 * Class CourseFields
 *
 * Where a lesson sits: its course, its module and its position in that module. A course shows its
 * structure (modules and lessons) read-only. Placing a lesson goes through Sensei's own course
 * structure, as the course outline block does.
 *
 * @since 1.0.0
 */
class CourseFields implements FieldHandlerInterface {

	/**
	 * Returns the key of this group under "fields".
	 *
	 * @since  1.0.0
	 * @return string The id.
	 */
	#[Override]
	public function get_id(): string {
		return 'course';
	}

	/**
	 * Returns whether this group belongs to a post type.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return bool True for courses and lessons.
	 */
	#[Override]
	public function applies_to( string $post_type ): bool {
		return in_array( $post_type, array( 'course', 'lesson' ), true );
	}

	/**
	 * Returns the JSON schema of the values this group accepts.
	 *
	 * @since  1.0.0
	 * @return array An object schema.
	 */
	#[Override]
	public function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'description'          => 'Lessons only: place the lesson in a course, optionally in one of that course\'s existing modules (see the course\'s structure with get-content), at a position (1 = first). A course\'s structure is read-only here.',
			'additionalProperties' => false,
			'properties'           => array(
				'course_id' => array( 'type' => 'integer' ),
				'module_id' => array(
					'type'        => 'integer',
					'description' => '0 = not in a module.',
				),
				'position'  => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
		);
	}

	/**
	 * Returns the current values: a course's structure, or where a lesson sits.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The course or lesson.
	 * @return array The values.
	 */
	#[Override]
	public function read( WP_Post $post ): array {
		if ( 'course' === $post->post_type ) {
			return array( 'structure' => $this->describe_structure( $this->get_structure( $post->ID ) ) );
		}

		$course_id = (int) get_post_meta( $post->ID, '_lesson_course', true );
		$module_id = $this->get_module_id( $post->ID );

		return array(
			'course_id' => $course_id,
			'module_id' => $module_id,
			'position'  => $course_id ? $this->find_position( $this->get_structure( $course_id ), $post->ID, $module_id ) : 0,
		);
	}

	/**
	 * Places a lesson in a course and module. Courses take no values here.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post   The course or lesson, already saved.
	 * @param  array   $values The values from the input.
	 * @return true|WP_Error True, or the reason why it failed.
	 */
	#[Override]
	public function write( WP_Post $post, array $values ): true|WP_Error {
		if ( 'lesson' !== $post->post_type ) {
			return array() === $values
				? true
				: new WP_Error( 'cf_ai_course_readonly', __( 'A course\'s structure cannot be changed here; place each lesson instead.', 'core-functionality' ) );
		}

		if ( array() === $values ) {
			return true;
		}

		$current   = $this->read( $post );
		$course_id = (int) ( $values['course_id'] ?? $current['course_id'] );
		if ( 'course' !== get_post_type( $course_id ) || ! current_user_can( 'edit_post', $course_id ) ) {
			return new WP_Error( 'cf_ai_course', __( 'Give an existing course you may edit (course_id).', 'core-functionality' ) );
		}

		// A lesson that moves to another course leaves its module behind, unless a new one is given.
		$module_id = (int) ( $values['module_id'] ?? ( $course_id === $current['course_id'] ? $current['module_id'] : 0 ) );
		$position  = (int) ( $values['position'] ?? 0 );

		return $this->place_lesson( $post, $course_id, $module_id, $position );
	}

	/**
	 * Puts a lesson in the structure of a course and saves that structure.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $lesson    The lesson.
	 * @param  int     $course_id The course.
	 * @param  int     $module_id The module, or 0 for none.
	 * @param  int     $position  The position (1 = first), or 0 for the end.
	 * @return true|WP_Error True, or the reason why it failed.
	 */
	private function place_lesson( WP_Post $lesson, int $course_id, int $module_id, int $position ): true|WP_Error {
		$structure = $this->remove_lesson( $this->get_structure( $course_id ), $lesson->ID );
		$item      = array(
			'type'  => 'lesson',
			'id'    => $lesson->ID,
			'title' => '' !== $lesson->post_title ? $lesson->post_title : __( 'Untitled lesson', 'core-functionality' ),
		);

		if ( 0 === $module_id ) {
			$structure = $this->insert_loose_lesson( $structure, $item, $position );
		} else {
			$index = $this->find_module_index( $structure, $module_id );
			if ( null === $index ) {
				/* translators: %d: module id. */
				return new WP_Error( 'cf_ai_course_module', sprintf( __( 'The course has no module %d.', 'core-functionality' ), $module_id ) );
			}
			$structure[ $index ]['lessons'] = $this->insert_at( $structure[ $index ]['lessons'], $item, $position );
		}

		$saved = Sensei_Course_Structure::instance( $course_id )->save( $structure );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return true === $saved ? true : new WP_Error( 'cf_ai_course_save', __( 'The course structure could not be saved.', 'core-functionality' ) );
	}

	/**
	 * Returns the structure of a course as Sensei edits it, bypassing the query cache.
	 *
	 * @since  1.0.0
	 * @param  int $course_id The course.
	 * @return array The structure.
	 */
	private function get_structure( int $course_id ): array {
		return Sensei_Course_Structure::instance( $course_id )->get( 'edit', true );
	}

	/**
	 * Returns the structure without a lesson, wherever it was.
	 *
	 * @since  1.0.0
	 * @param  array $structure The structure.
	 * @param  int   $lesson_id The lesson.
	 * @return array The structure.
	 */
	private function remove_lesson( array $structure, int $lesson_id ): array {
		$result = array();
		foreach ( $structure as $item ) {
			if ( 'lesson' === $item['type'] && (int) $item['id'] === $lesson_id ) {
				continue;
			}
			if ( 'module' === $item['type'] ) {
				$item['lessons'] = $this->remove_lesson( $item['lessons'], $lesson_id );
			}
			$result[] = $item;
		}

		return $result;
	}

	/**
	 * Inserts a lesson among the lessons outside modules, which Sensei shows after the modules.
	 *
	 * @since  1.0.0
	 * @param  array $structure The structure.
	 * @param  array $item      The lesson item.
	 * @param  int   $position  The position among those lessons (1 = first), or 0 for the end.
	 * @return array The structure.
	 */
	private function insert_loose_lesson( array $structure, array $item, int $position ): array {
		$modules = array();
		$lessons = array();
		foreach ( $structure as $entry ) {
			if ( 'module' === $entry['type'] ) {
				$modules[] = $entry;
			} else {
				$lessons[] = $entry;
			}
		}

		return array_values( array_merge( $modules, $this->insert_at( $lessons, $item, $position ) ) );
	}

	/**
	 * Inserts an item in a list at a position.
	 *
	 * @since  1.0.0
	 * @param  array $items    The list.
	 * @param  array $item     The item.
	 * @param  int   $position The position (1 = first), or 0 for the end.
	 * @return array The list.
	 */
	private function insert_at( array $items, array $item, int $position ): array {
		$index = 0 === $position ? count( $items ) : max( 0, min( count( $items ), $position - 1 ) );
		array_splice( $items, $index, 0, array( $item ) );

		return $items;
	}

	/**
	 * Returns the index of a module in the structure.
	 *
	 * @since  1.0.0
	 * @param  array $structure The structure.
	 * @param  int   $module_id The module.
	 * @return int|null The index, or null when the course does not have it.
	 */
	private function find_module_index( array $structure, int $module_id ): ?int {
		foreach ( $structure as $index => $item ) {
			if ( 'module' === $item['type'] && (int) $item['id'] === $module_id ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Returns the position of a lesson in its module, or among the lessons outside modules.
	 *
	 * @since  1.0.0
	 * @param  array $structure The structure.
	 * @param  int   $lesson_id The lesson.
	 * @param  int   $module_id The module, or 0.
	 * @return int The position (1 = first), or 0 when it is not in the structure.
	 */
	private function find_position( array $structure, int $lesson_id, int $module_id ): int {
		$lessons = array();
		foreach ( $structure as $item ) {
			if ( 0 === $module_id && 'lesson' === $item['type'] ) {
				$lessons[] = (int) $item['id'];
			}
			if ( 0 !== $module_id && 'module' === $item['type'] && (int) $item['id'] === $module_id ) {
				foreach ( $item['lessons'] as $lesson ) {
					$lessons[] = (int) $lesson['id'];
				}
			}
		}

		$index = array_search( $lesson_id, $lessons, true );

		return false === $index ? 0 : $index + 1;
	}

	/**
	 * Returns the module of a lesson.
	 *
	 * @since  1.0.0
	 * @param  int $lesson_id The lesson.
	 * @return int The module id, or 0.
	 */
	private function get_module_id( int $lesson_id ): int {
		$terms = get_the_terms( $lesson_id, 'module' );

		return is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof WP_Term ? $terms[0]->term_id : 0;
	}

	/**
	 * Returns a short version of the structure for the AI assistant.
	 *
	 * @since  1.0.0
	 * @param  array $structure The structure.
	 * @return array Modules with their lessons, and lessons outside modules.
	 */
	private function describe_structure( array $structure ): array {
		$result = array();
		foreach ( $structure as $item ) {
			if ( 'module' === $item['type'] ) {
				$lessons = array();
				foreach ( $item['lessons'] as $lesson ) {
					$lessons[] = $this->describe_lesson( $lesson );
				}
				$result[] = array(
					'module_id' => (int) $item['id'],
					'title'     => (string) $item['title'],
					'lessons'   => $lessons,
				);
			} else {
				$result[] = $this->describe_lesson( $item );
			}
		}

		return $result;
	}

	/**
	 * Returns id, title and whether a lesson is a draft.
	 *
	 * @since  1.0.0
	 * @param  array $lesson The lesson item.
	 * @return array The description.
	 */
	private function describe_lesson( array $lesson ): array {
		return array(
			'lesson_id' => (int) $lesson['id'],
			'title'     => (string) $lesson['title'],
			'draft'     => ! empty( $lesson['draft'] ),
		);
	}
}
