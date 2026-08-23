<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds 9 templates into Zaplane's own Email Templates store (`EmailTemplate`
 * model / "Zaplane -> Email Templates" page), one per Academy LMS
 * student-facing event. AcademyLmsRecipeSeeder looks these up by title and
 * wires each Recipe's `send_email` action to `content_source: 'template'`
 * instead of an inline body, so admins get one real, visually-editable
 * template per event instead of PHP-baked HTML.
 *
 * Content uses Zaplane's own {{1.field}} node-expression syntax (matching
 * each Academy trigger's resolve_trigger() output in
 * zaplane/integrations/academy.php) — these templates only render correctly
 * when sent via their matching Recipe, not as a generic standalone template.
 *
 * Block-tree shape mirrors GemCRM's EmailTreeRenderer manifest (same shape
 * the GemCRM Prebuilt Templates gallery uses in
 * dev_gemcrm/components/EmbEmailEditor/emailTemplates.js) so it renders
 * identically through the same renderer.
 */
class AcademyLmsEmailTemplateSeeder {

	private int $seq = 0;

	public function run(): void {
		if ( ! class_exists( 'Academy' ) || ! class_exists( EmailTemplate::class ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );

		$this->seed(
			'Academy LMS: Welcome Email',
			'Welcome, {{1.first_name}}!',
			'',
			[
				$this->heading( 'Welcome, {{1.first_name}}!' ),
				$this->text( 'Thanks for creating a student account on ' . esc_html( $site_name ) . '. You are all set — start exploring courses and pick up right where you left off any time.' ),
				$this->button( 'Go to Dashboard', $site_url ),
			]
		);

		$this->seed(
			'Academy LMS: Course Enrollment',
			"You're enrolled! 🎓",
			'',
			[
				$this->heading( "You're enrolled! 🎓" ),
				$this->text( 'Hi {{1.first_name}}, you have successfully enrolled in {{1.course_title}}. Jump back in any time to continue learning.' ),
				$this->button( 'Start Course', '{{1.course_url}}' ),
			]
		);

		$this->seed(
			'Academy LMS: Course Completed',
			'Congratulations, {{1.first_name}}! 🏆',
			'',
			[
				$this->heading( 'Congratulations, {{1.first_name}}! 🏆', [ 'alignment' => 'center', 'fontSize' => 30 ] ),
				$this->text( 'You have successfully completed {{1.course_title}}. Great work — see what else you can learn next.', [ 'alignment' => 'center' ] ),
				$this->button( 'View Certificate', '{{1.course_url}}', [ 'alignment' => 'center' ] ),
			]
		);

		$this->seed(
			'Academy LMS: Quiz Result',
			'Your quiz has been evaluated',
			'',
			[
				$this->heading( 'Your quiz has been evaluated' ),
				$this->text( 'Hi {{1.first_name}}, your quiz attempt in {{1.course_title}} has been reviewed.' ),
				$this->button( 'View Result', '{{1.course_url}}' ),
			]
		);

		$this->seed(
			'Academy LMS: Assignment Evaluated',
			'Your assignment has been evaluated',
			'',
			[
				$this->heading( 'Your assignment has been evaluated' ),
				$this->text( 'Hi {{1.first_name}}, your assignment submission for {{1.course_title}} has been graded. You scored {{1.achieved_mark}} out of {{1.total_mark}}.' ),
				$this->button( 'View Feedback', '{{1.course_url}}' ),
			]
		);

		$this->seed(
			'Academy LMS: Question Answered',
			'Your question has an answer',
			'',
			[
				$this->heading( 'Your question has an answer' ),
				$this->text( 'Hi {{1.first_name}}, your question on {{1.course_title}} has just been answered. Head back to the course to read the reply.' ),
				$this->button( 'View Answer', '{{1.course_url}}' ),
			]
		);

		$this->seed(
			'Academy LMS: Booking Confirmed',
			'Your booking is confirmed',
			'',
			[
				$this->heading( 'Your booking is confirmed' ),
				$this->text( 'Hi {{1.first_name}}, your session "{{1.tutor_name}}" is confirmed for {{1.session_time}}. Details on how to join are below.' ),
				$this->text( '{{1.location_or_meeting_link}}' ),
			]
		);

		$this->seed(
			'Academy LMS: Instructor Request Accepted',
			'You are now an instructor! 🎉',
			'',
			[
				$this->heading( 'You are now an instructor! 🎉' ),
				$this->text( 'Hi {{1.first_name}}, your request to become an instructor on ' . esc_html( $site_name ) . ' has been approved. You can log in and start creating courses right away.' ),
				$this->button( 'Log In', $site_url ),
			]
		);

		$this->seed(
			'Academy LMS: Instructor Request Denied',
			'Update on your instructor request',
			'',
			[
				$this->heading( 'Update on your instructor request' ),
				$this->text( 'Hi {{1.first_name}}, thank you for your interest in becoming an instructor on ' . esc_html( $site_name ) . '. After review, we are not able to approve your request at this time.' ),
			]
		);
	}

	/**
	 * Look up a previously seeded template's id by title. Used by
	 * AcademyLmsRecipeSeeder to wire each Recipe's send_email action to the
	 * matching template.
	 */
	public static function find_id( string $title ): ?int {
		$template = EmailTemplate::where( 'title', $title )->first();
		return $template ? (int) $template->id : null;
	}

	private function seed( string $title, string $subject, string $pre_header, array $children ): void {
		if ( EmailTemplate::where( 'title', $title )->first() ) {
			return;
		}

		$tree = [
			'version' => 2,
			'root'    => [
				'id'         => 'root',
				'type'       => 'email',
				'attributes' => [
					'bgColor'         => '#F1F5F9',
					'contentBg'       => '#FFFFFF',
					'contentWidth'    => 600,
					'padding'         => [ 40, 32, 40, 32 ],
					'fontFamily'      => 'Arial, sans-serif',
					'contentBorder'   => [ 'width' => 0, 'style' => 'solid', 'color' => 'var(--gemcrm-border-color)', 'radius' => 8 ],
					'contentBoxShadow' => [ 'enabled' => true, 'x' => 0, 'y' => 4, 'blur' => 12, 'spread' => 0, 'color' => 'rgba(15,23,42,.06)', 'inset' => false ],
				],
				'children'   => $children,
			],
		];

		EmailTemplate::create( [
			'title'      => $title,
			'subject'    => $subject,
			'pre_header' => $pre_header,
			'content'    => wp_json_encode( $tree ),
			'created_by' => 0,
		] );
	}

	private function next_id( string $prefix ): string {
		return $prefix . '_' . ( ++$this->seq );
	}

	private function typo( array $over = [] ): array {
		return array_merge(
			[
				'fontFamily'     => 'inherit',
				'fontSize'       => 14,
				'fontWeight'     => '400',
				'fontStyle'      => 'normal',
				'lineHeight'     => 1.6,
				'letterSpacing'  => 0,
				'textTransform'  => 'none',
				'textDecoration' => 'none',
				'color'          => '#334155',
			],
			$over
		);
	}

	private function no_border(): array {
		return [ 'width' => 0, 'style' => 'solid', 'color' => '#000000', 'radius' => 0 ];
	}

	private function heading( string $text, array $over = [] ): array {
		return [
			'id'         => $this->next_id( 'h' ),
			'type'       => 'heading',
			'children'   => [],
			'attributes' => [
				'text'       => $text,
				'tag'        => $over['tag'] ?? 'h1',
				'alignment'  => $over['alignment'] ?? 'left',
				'typography' => $this->typo( array_merge(
					[
						'fontSize'   => $over['fontSize'] ?? 28,
						'fontWeight' => '700',
						'lineHeight' => 1.3,
						'color'      => '#0F172A',
					],
					$over['typography'] ?? []
				) ),
				'padding'    => $over['padding'] ?? [ 0, 0, 12, 0 ],
				'margin'     => [ 0, 0, 0, 0 ],
				'border'     => $this->no_border(),
			],
		];
	}

	private function text( string $body, array $over = [] ): array {
		return [
			'id'         => $this->next_id( 't' ),
			'type'       => 'text',
			'children'   => [],
			'attributes' => [
				'text'       => $body,
				'alignment'  => $over['alignment'] ?? 'left',
				'typography' => $this->typo( $over['typography'] ?? [] ),
				'padding'    => $over['padding'] ?? [ 0, 0, 24, 0 ],
				'margin'     => [ 0, 0, 0, 0 ],
				'border'     => $this->no_border(),
			],
		];
	}

	private function button( string $text, string $href, array $over = [] ): array {
		return [
			'id'         => $this->next_id( 'b' ),
			'type'       => 'button',
			'children'   => [],
			'attributes' => [
				'text'         => $text,
				'href'         => $href,
				'openInNewTab' => true,
				'noFollow'     => false,
				'alignment'    => $over['alignment'] ?? 'left',
				'bg'           => $over['bg'] ?? '#0073E6',
				'typography'   => $this->typo( [ 'fontSize' => 14, 'fontWeight' => '600', 'lineHeight' => 1.2, 'color' => '#FFFFFF' ] ),
				'padding'      => [ 12, 24, 12, 24 ],
				'margin'       => [ 0, 0, 0, 0 ],
				'border'       => [ 'width' => 0, 'style' => 'solid', 'color' => '#000000', 'radius' => 6 ],
				'boxShadow'    => [ 'enabled' => true, 'x' => 0, 'y' => 2, 'blur' => 6, 'spread' => 0, 'color' => 'rgba(0,115,230,.25)', 'inset' => false ],
			],
		];
	}
}
