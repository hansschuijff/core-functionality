<?php
/**
 * Sensei Lms Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;
use function admin_url;
use function home_url;

/**
 * Class SenseiLms
 *
 * Represents Sensei Lms in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class SenseiLms extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'sensei-lms';
	}

	/**
	 * Returns the post types Sensei LMS registers.
	 *
	 * @since  1.0.0
	 * @return string[] Post type names.
	 */
	#[Override]
	public function get_post_types(): array {
		return array( 'course', 'lesson', 'quiz', 'question', 'multiple_question', 'sensei_message', 'sensei_email' );
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'SENSEI_LMS_VERSION' );
	}

	/**
	 * Returns the admin toolbar links this plugin offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:      'cf-sensei-lms',
				title:   __( 'Courses', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=course' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Courses', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-grading',
				title:   __( 'Grading', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=sensei_grading' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Grading', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-student-management',
				title:   __( 'Student Management', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=sensei_learners' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Student Management', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-edit-courses',
				title:   __( 'Edit Courses', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=course' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Courses', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-lessons',
				title:   __( 'Lessons', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=lesson' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Lessons', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-categories',
				title:   __( 'Course Categories', 'core-functionality' ),
				href:    admin_url( 'edit-tags.php?taxonomy=course-category&post_type=course' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Course Categories', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Categories', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-modules',
				title:   __( 'Modules', 'core-functionality' ),
				href:    admin_url( 'edit-tags.php?taxonomy=module' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Modules', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-module-order',
				title:   __( 'Module Order', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=course&page=module-order' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Module Order', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-lesson-tags',
				title:   __( 'Lesson Tags', 'core-functionality' ),
				href:    admin_url( 'edit-tags.php?taxonomy=lesson-tag&post_type=lesson' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Lesson Tags', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-questions',
				title:   __( 'Quiz Questions', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=question' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Questions', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Questions', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-messages',
				title:   __( 'Course Messages', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=sensei_message' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Messages', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Messages', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-settings',
				title:   __( 'Sensei Settings', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=sensei-settings' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Settings', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-analytics',
				title:   __( 'Course Analytics', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=sensei_analysis' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Analytics', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Analytics', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view',
				title:   __( 'View Courses', 'core-functionality' ),
				href:    home_url( '/cursussen/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Courses', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
				short_title: __( 'View', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-courses',
				title:   __( 'Courses', 'core-functionality' ),
				href:    home_url( '/cursussen/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Courses', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-courses-list',
				title:   __( 'Course List', 'core-functionality' ),
				href:    home_url( '/cursusoverzicht/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Course List', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-category-mediumschap',
				title:   __( 'Course-category: Mediumschap', 'core-functionality' ),
				href:    home_url( '/cursuscategorie/mediumschap/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Course-category: Mediumschap', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-my-courses',
				title:   __( 'My Courses', 'core-functionality' ),
				href:    home_url( '/mijn-cursussen/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS My Courses', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-learners-profile',
				title:   __( 'Learners Profile', 'core-functionality' ),
				href:    home_url( '/student/hansepans/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Learners Profile', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-basis-mediumschap',
				title:   __( 'Course: Basis Mediumschap', 'core-functionality' ),
				href:    home_url( '/cursus/mediumschap-basis/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Course: Basis Mediumschap', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-basis-mediumschap-results',
				title:   __( 'Course results', 'core-functionality' ),
				href:    home_url( '/cursus/mediumschap-basis/results/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Course results', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-module-orakelen',
				title:   __( 'Module: Orakelen als een orakel', 'core-functionality' ),
				href:    home_url( '/modules/orakelen-als-een-orakel/?course_id=6732' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Module: Orakelen als een orakel', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-lessons',
				title:   __( 'Lessons', 'core-functionality' ),
				href:    home_url( '/les/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Lessons', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-lesson-tag-aura',
				title:   __( 'Lesson-tag: Aura', 'core-functionality' ),
				href:    home_url( '/lestag/aura/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Lesson-tag: Aura', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-lesson-aura-reading',
				title:   __( 'Lesson: Aura Reading', 'core-functionality' ),
				href:    home_url( '/les/auras-waarnemen-en-lezen/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Lesson: Aura Reading', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-quiz-my-psychic-ability',
				title:   __( 'Quiz: The History of My Psychic Ability', 'core-functionality' ),
				href:    home_url( '/test/hoe-ik-mijn-mediumschap-ontwikkelde/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Quiz: The History of My Psychic Ability', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-messages',
				title:   __( 'Messages', 'core-functionality' ),
				href:    home_url( '/berichten/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Messages', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-sensei-lms-view-message-questions',
				title:   __( 'Message: I have some questions', 'core-functionality' ),
				href:    home_url( '/berichten/ik-heb-een-paar-vragen-over-de-roos/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Sensei LMS Message: I have some questions', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
		);
	}

	/**
	 * Returns the admin menu blocks this plugin offers.
	 *
	 * @since  1.0.0
	 * @return AdminMenuBlock[] The menu blocks.
	 */
	#[Override]
	protected function get_menu_nodes(): array {
		return array(); }
}
