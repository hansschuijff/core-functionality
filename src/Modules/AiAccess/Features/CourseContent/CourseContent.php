<?php
/**
 * AI Course Content Feature.
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

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentEditing;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentTypeProfile;
use Override;

use function __;
use function add_filter;
use function is_array;

/**
 * Class CourseContent
 *
 * Makes courses and lessons of Sensei LMS available to an AI assistant: a lesson can be placed in
 * a course and module, and a course shows its structure. The blocks that hold a course's outline
 * or a lesson's quiz and actions must stay in the content. Quizzes and enrolments are left alone.
 *
 * @since 1.0.0
 */
class CourseContent extends AbstractFeature {

	/**
	 * The blocks of a course that must stay once they are in the content.
	 *
	 * @var string[]
	 */
	private const COURSE_BLOCKS = array(
		'sensei-lms/course-outline',
		'sensei-lms/course-progress',
		'sensei-lms/course-actions',
	);

	/**
	 * The blocks of a lesson that must stay once they are in the content.
	 *
	 * @var string[]
	 */
	private const LESSON_BLOCKS = array(
		'sensei-lms/quiz',
		'sensei-lms/lesson-actions',
		'sensei-lms/lesson-properties',
	);

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'ai_access_course_content',
			name:          __( 'Course Content', 'core-functionality' ),
			description:   __( 'An AI assistant may write drafts of Sensei courses and lessons and place lessons in a course and module. Quizzes and enrolments are left alone.', 'core-functionality' ),
			dependencies:  'sensei-lms',
			uses_features: array( ContentEditing::class ),
			status:        Deployment::PRODUCTION,
		);
	}

	/**
	 * Adds the hooks of this feature.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_filter( 'core-functionality/ai-access-content-types', array( $this, 'add_content_types' ) );
		add_filter( 'core-functionality/ai-access-field-handlers', array( $this, 'add_field_handler' ) );
	}

	/**
	 * Adds courses and lessons. A Rewrite & Republish copy stays off until it is tested for them.
	 *
	 * @since  1.0.0
	 * @param  mixed $profiles The profiles.
	 * @return array The profiles.
	 */
	public function add_content_types( mixed $profiles ): array {
		$profiles = is_array( $profiles ) ? $profiles : array();

		$profiles['course'] = new ContentTypeProfile(
			'course',
			taxonomies:       array( 'course-category' ),
			protected_blocks: self::COURSE_BLOCKS,
		);
		$profiles['lesson'] = new ContentTypeProfile(
			'lesson',
			taxonomies:       array( 'lesson-tag' ),
			protected_blocks: self::LESSON_BLOCKS,
		);

		return $profiles;
	}

	/**
	 * Adds the course fields.
	 *
	 * @since  1.0.0
	 * @param  mixed $handlers The handlers.
	 * @return array The handlers.
	 */
	public function add_field_handler( mixed $handlers ): array {
		$handlers   = is_array( $handlers ) ? $handlers : array();
		$handlers[] = new CourseFields();

		return $handlers;
	}
}
