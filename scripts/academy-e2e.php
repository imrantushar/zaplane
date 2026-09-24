<?php
/**
 * Zaplane x Academy E2E harness — run ONLY via WP-CLI on an isolated LOCAL site.
 * Install at zaplane/scripts/academy-e2e.php.
 * Usage: wp eval-file scripts/academy-e2e.php plan
 *        wp eval-file scripts/academy-e2e.php triggers I_UNDERSTAND_TEST_DATA
 *        wp eval-file scripts/academy-e2e.php actions I_UNDERSTAND_TEST_DATA [lesson_id]
 *        wp eval-file scripts/academy-e2e.php all I_UNDERSTAND_TEST_DATA [lesson_id]
 *        wp eval-file scripts/academy-e2e.php cleanup I_UNDERSTAND_TEST_DATA [run_token]
 *
 * Simulated hooks prove Zaplane hook routing/async node execution; NOT Academy's actual
 * business lifecycle. Action tests call the real Zaplane Academy actions against
 * tagged, purpose-made students/instructor/course, then verify Academy state.
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit( 'WP-CLI required.' );
}

use Zaplane\Authoring\WorkflowAuthor;
use Zaplane\Framework\Classes\Query;
use Zaplane\Framework\Core\Automation;
use Zaplane\Integrations\Academy;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;

final class ZaplaneAcademyE2E {
    private const INDEX = 'zaplane_academy_e2e_runs_v1';
    private const CONFIRM = 'I_UNDERSTAND_TEST_DATA';
    private const MARKER = '[ACADEMY E2E]';
    private array $index;
    private string $token;
    private array $entry;
    private Automation $engine;
    private int $author = 0;
    private array $rows = [];
    private array $registered_hooks = [];

    public function __construct() {
        $this->index = (array) get_option( self::INDEX, [] );
        $this->token = gmdate( 'YmdHis' ) . '-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 );
        $this->entry = [ 'created' => current_time( 'mysql' ), 'workflows' => [], 'posts' => [], 'users' => [], 'comments' => [], 'lessons' => [], 'course' => 0, 'fixtures' => [], 'report' => [] ];
    }

    private static function say( string $message ): void { \WP_CLI::log( $message ); }
    private static function die( string $message ): void { \WP_CLI::error( $message ); }
    private function save(): void { $this->index[ $this->token ] = $this->entry; update_option( self::INDEX, $this->index, false ); }
    private function record( string $status, string $kind, string $name, string $detail = '', int $workflow = 0, int $post = 0 ): void {
        $row = compact( 'status', 'kind', 'name', 'detail', 'workflow', 'post' );
        $this->rows[] = $row;
        self::say( sprintf( '%-7s %-7s %-36s %s', $status, $kind, $name, $detail ) );
    }

    private static function local_only(): void {
        $host = strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) );
        $local = in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) || (bool) preg_match( '/\.(local|test|localhost|invalid)$/', $host );
        if ( ! $local ) {
            self::die( 'Refusing data-writing tests: site host is not .local/.test/localhost (' . $host . ').' );
        }
    }

    private function initialize( bool $writing ): void {
        // Integration classes are registry-loaded, not necessarily PHP-autoloaded.
        \Zaplane\Framework\Core\IntegrationLoader::get( 'academy' );
        if ( ! class_exists( WorkflowAuthor::class ) || ! class_exists( Academy::class ) ) {
            self::die( 'Zaplane / Academy integration classes unavailable. Activate Zaplane and regenerate the integration manifest.' );
        }
        $admin = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
        $this->author = (int) ( $admin[0] ?? 0 );
        if ( ! $this->author ) { self::die( 'No administrator user found.' ); }
        if ( $writing ) {
            self::local_only();
            if ( ! class_exists( '\\Academy\\Helper' ) ) { self::die( 'Academy LMS is not active.' ); }
            $this->assert_isolated_site();
            wp_set_current_user( $this->author );
        }
        $this->engine = Automation::get_instance() ?: Zaplane::init()->container->get( 'automation' );
        if ( ! $this->engine ) { self::die( 'Zaplane automation engine not bootstrapped.' ); }
    }

    private function assert_isolated_site(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'zaplane_workflows';
        $active = $wpdb->get_results( "SELECT id, title FROM `{$table}` WHERE status = 'active' LIMIT 5", ARRAY_A );
        if ( $active ) {
            self::die( 'Pause ALL existing active workflows on this LOCAL site before running. Found: ' . wp_json_encode( $active ) );
        }
    }

    public function plan(): void {
        $this->initialize( false );
        $t = Academy::get_triggers(); $a = Academy::get_actions();
        self::say( sprintf( 'Academy registered: %d triggers / %d actions', count( $t ), count( $a ) ) );
        if ( 27 !== count( $t ) || 9 !== count( $a ) ) { self::die( 'Unexpected Academy registry count. Stop and review branch.' ); }
        foreach ( $t as $id => $v ) { self::say( sprintf( 'TRIGGER  %-36s %s', $id, implode( ', ', (array) $v['hook'] ) ) ); }
        foreach ( $a as $id => $v ) { self::say( sprintf( 'ACTION   %-36s %s', $id, $v['label'] ) ); }
        self::say( 'PLAN is read-only. triggers = simulated WP hooks; actions = real Academy action on tagged LOCAL fixtures.' );
    }

    private function create_post( string $title, string $status = 'draft' ): int {
        $id = wp_insert_post( [ 'post_type' => 'post', 'post_title' => self::MARKER . ' ' . $this->token . ' ' . $title,
            'post_status' => $status, 'post_content' => 'ACADEMY_E2E_TOKEN:' . $this->token ], true );
        if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'Cannot create fixture post: ' . ( is_wp_error( $id ) ? $id->get_error_message() : '' ) ); }
        $id = (int) $id;
        update_post_meta( $id, '_zaplane_academy_e2e_token', $this->token );
        $this->entry['posts'][] = $id; $this->save(); return $id;
    }

    private function fixtures(): array {
        if ( isset( $this->entry['fixtures']['post'] ) ) { return $this->entry['fixtures']; }
        $post = $this->create_post( 'simulation target' );
        $comment = wp_insert_comment( [ 'comment_post_ID' => $post, 'comment_author' => 'E2E Student',
            'comment_author_email' => 'e2e@example.test', 'comment_content' => 'Simulated Academy feedback',
            'comment_approved' => 1, 'user_id' => $this->author ] );
        if ( ! $comment ) { throw new RuntimeException( 'Cannot create fixture comment.' ); }
        $this->entry['comments'][] = (int) $comment;
        $this->entry['fixtures'] = [ 'post' => $post, 'comment' => (int) $comment, 'user' => $this->author ];
        $this->save(); return $this->entry['fixtures'];
    }

    private function workflow( string $name, array $nodes ): int {
        $title = self::MARKER . ' ' . $this->token . ' | ' . $name;
        $result = WorkflowAuthor::create( $title, [ 'nodes' => $nodes ], [ 'user_id' => $this->author, 'status' => 'active' ] );
        $id = (int) $result['workflow_id'];
        $this->entry['workflows'][] = $id; $this->save();
        Query::flush_trigger_map();
        return $id;
    }

    private function pause( int $id ): void {
        $w = Workflow::find( $id );
        if ( $w ) { $w->status = 'paused'; $w->save(); }
        Query::flush_trigger_map();
    }

    private function ensure_router( string $hook ): void {
        if ( isset( $this->registered_hooks[ $hook ] ) ) { return; }
        // WP-CLI skips Automation::boot(); explicitly register only this harness's router.
        add_action( $hook, [ $this->engine, 'trigger_router' ], 10, 99 );
        $this->registered_hooks[ $hook ] = true;
    }

    private function fire_isolated( string $hook, array $params ): void {
        global $wp_filter;
        $previous = $wp_filter[ $hook ] ?? null;
        $wp_filter[ $hook ] = new WP_Hook();
        add_action( $hook, [ $this->engine, 'trigger_router' ], 10, 99 );
        try { do_action_ref_array( $hook, $params ); }
        finally {
            if ( null !== $previous ) { $wp_filter[ $hook ] = $previous; }
            else { unset( $wp_filter[ $hook ] ); }
        }
    }

    private function drain( int $run_id ): void {
        // WP-CLI skips Automation::boot(), so process persisted node runs here.
        // IMPORTANT: Zaplane QueryBuilder caches get()/first() for the entire PHP
        // request. This long-running test script reads the *same* query after
        // writing statuses. Without fresh(), completed nodes are re-read as
        // pending on each pass and all 27 triggers falsely fail after 12 passes.
        for ( $pass = 0; $pass < 12; $pass++ ) {
            $pending = NodeRun::where( 'run_id', $run_id )->where( 'status', 'pending' )->fresh()->get();
            if ( ! count( $pending ) ) { return; }
            foreach ( $pending as $node ) {
                $node_id = (int) $node->id;
                $this->engine->dispatch_node_run( $node_id );
                if ( function_exists( 'as_unschedule_action' ) ) {
                    as_unschedule_action( 'zaplane_execute_node_run', [ 'node_run_id' => $node_id ], 'zaplane' );
                }
                $after = NodeRun::where( 'id', $node_id )->fresh()->first();
                if ( $after && 'pending' === $after->status ) {
                    throw new RuntimeException( 'Node dispatcher made no progress: run=' . $run_id . ' node=' . $node_id . ' key=' . $node->node_key . ' remains pending.' );
                }
            }
        }
        $nodes = NodeRun::where( 'run_id', $run_id )->fresh()->get();
        $statuses = [];
        foreach ( $nodes as $node ) {
            $statuses[] = [ 'id' => (int) $node->id, 'key' => (int) $node->node_key, 'status' => (string) $node->status, 'output' => $node->getOutput() ];
        }
        throw new RuntimeException( 'Node executor exceeded 12 passes: ' . wp_json_encode( $statuses ) );
    }

    private function results( int $id, string $expected_title, bool $academy_action = false ): array {
        $run = Run::where( 'workflow_id', $id )->orderBy( 'id', 'desc' )->fresh()->first();
        if ( ! $run ) { throw new RuntimeException( 'No Zaplane run created. Trigger router did not fire.' ); }
        $this->drain( (int) $run->id );
        $run = Run::where( 'id', $run->id )->fresh()->first();
        $node = NodeRun::where( 'run_id', $run->id )->where( 'node_key', 2 )->fresh()->first();
        if ( ! $node || 'completed' !== $node->status ) { throw new RuntimeException( 'WordPress Create Post node did not complete. Run=' . $run->id ); }
        $out = $node->getOutput();
        $post_id = (int) ( $out['data']['post_id'] ?? $out['post_id'] ?? 0 );
        $post = get_post( $post_id );
        if ( ! $post || 'draft' !== $post->post_status || $post->post_title !== $expected_title ) {
            throw new RuntimeException( 'WordPress draft receipt missing/wrong. ID=' . $post_id );
        }
        update_post_meta( $post_id, '_zaplane_academy_e2e_token', $this->token );
        $this->entry['posts'][] = $post_id; $this->save();
        $action_output = [];
        if ( $academy_action ) {
            $action_node = NodeRun::where( 'run_id', $run->id )->where( 'node_key', 3 )->fresh()->first();
            if ( ! $action_node || 'completed' !== $action_node->status ) {
                $error = $action_node ? wp_json_encode( $action_node->getOutput() ) : 'missing';
                throw new RuntimeException( 'Academy Action node failed: ' . $error );
            }
            $action_output = $action_node->getOutput();
            if ( empty( $action_output['data']['success'] ) ) { throw new RuntimeException( 'Academy Action returned success=false.' ); }
        }
        if ( 'completed' !== $run->status ) { throw new RuntimeException( 'Zaplane run status=' . $run->status . ', error=' . $run->last_error ); }
        return [ 'run' => (int) $run->id, 'post' => $post_id, 'action' => $action_output ];
    }

    private function capture( string $kind, string $name, callable $test ): void {
        try {
            $out = $test();
            if ( $out['skip'] ?? false ) { $this->record( 'SKIPPED', $kind, $name, (string) ( $out['reason'] ?? '' ) ); }
            else { $this->record( $kind === 'TRIGGER' ? 'SIM-PASS' : 'REAL-PASS', $kind, $name,
                'run=' . ( $out['run'] ?? '-' ) . ' draft=' . ( $out['post'] ?? '-' ), (int) ( $out['workflow'] ?? 0 ), (int) ( $out['post'] ?? 0 ) ); }
        } catch ( Throwable $e ) {
            $this->record( 'FAIL', $kind, $name, $e->getMessage() );
        }
    }

    private function trigger_fixture( string $event, array $f ): array {
        $post = $f['post']; $user = $f['user']; $comment = $f['comment'];
        $config = [];
        $pub = [ 'course_published' => 'academy_courses', 'quiz_published' => 'academy_quiz',
            'announcement_published' => 'academy_announcement', 'assignment_published' => 'academy_assignments',
            'tutor_booking_published' => 'academy_booking', 'course_bundle_published' => 'alms_course_bundle' ];
        if ( isset( $pub[ $event ] ) ) {
            $data = (object) [ 'ID' => $post, 'post_type' => $pub[ $event ], 'post_title' => 'E2E publication', 'post_author' => $user ];
            return [ 'transition_post_status', [ 'publish', 'draft', $data ], $config ];
        }
        if ( in_array( $event, [ 'quiz_attempt_submitted', 'quiz_passed', 'quiz_failed', 'quiz_target' ], true ) ) {
            $status = 'quiz_failed' === $event ? 'failed' : ( 'quiz_attempt_submitted' === $event ? 'attempt_ended' : 'passed' );
            $arg = (object) [ 'quiz_id' => $post, 'course_id' => $post, 'user_id' => $user,
                'attempt_id' => $post, 'attempt_status' => $status, 'earned_marks' => 8, 'total_marks' => 10 ];
            if ( 'quiz_target' === $event ) { $config['target_percentage'] = 50; }
            return [ 'academy_quizzes/api/after_quiz_attempt_finished', [ $arg ], $config ];
        }
        switch ( $event ) {
            case 'user_enroll_course': return [ 'academy/course/after_enroll', [ $post, $post, $user ], $config ];
            case 'course_complete': return [ 'academy/admin/course_complete_after', [ $post, $user ], $config ];
            case 'lesson_complete': return [ 'academy/frontend/after_mark_topic_complete', [ 'lesson', $post, $post, $user ], $config ];
            case 'assignment_completed': return [ 'academy/frontend/after_mark_topic_complete', [ 'assignment', $post, $post, $user ], $config ];
            case 'tutor_booking_completed': return [ 'academy/frontend/after_mark_topic_complete', [ 'booking', $post, $post, $user ], $config ];
            case 'zoom_meeting_completed': return [ 'academy/frontend/after_mark_topic_complete', [ 'zoom', $post, $post, $user ], $config ];
            case 'lesson_published': return [ 'academy_new_lesson_published', [[ 'ID' => $post, 'lesson_status' => 'publish',
                'lesson_date' => '2026-01-01 00:00:00', 'lesson_modified' => '2026-01-01 00:00:00',
                'lesson_author' => $user, 'lesson_title' => 'E2E lesson', 'course_id' => $post ]], $config ];
            case 'student_registered': return [ 'academy/admin/after_register_student', [ $user ], $config ];
            case 'instructor_registered': return [ 'academy/admin/after_register_instructor', [ $user ], $config ];
            case 'course_review_submitted': return [ 'academy/frontend/after_course_rating', [ $comment, $post, 5 ], $config ];
            case 'course_question_asked': return [ 'academy/frontend/insert_course_qa', [[ 'id' => $comment, 'post' => $post, 'status' => 'waiting_for_answer' ]], $config ];
            case 'course_question_replied': return [ 'academy/frontend/insert_course_qa_answered', [[ 'id' => $comment, 'post' => $post, 'status' => 'answered' ]], $config ];
            case 'assignment_submitted': case 'assignment_evaluated':
                $arg = (object) [ 'comment_ID' => $comment, 'comment_post_ID' => $post, 'user_id' => $user,
                    'comment_parent' => $post, 'comment_content' => 'E2E submission', 'comment_approved' => 'approved',
                    'meta' => [ 'academy_pro_assignment_evaluate_point' => 9, 'academy_pro_assignment_evaluate_feedback' => 'Good' ] ];
                return [ 'assignment_submitted' === $event ? 'academy_pro/frontend/submitted_assignment' : 'academy_pro/frontend/evaluate_submitted_assignment', [ $arg ], $config ];
            case 'tutor_booking_booked': return [ 'academy_pro/booking/after_booked', [ $post, $post, $user ], $config ];
            case 'tutor_booking_review_submitted': return [ 'academy_pro/fronted/academy_booking_review', [ $comment, 5 ], $config ];
            case 'zoom_meeting_published': return [ 'academy_pro/frontend/after_zoom_publish', [ $post ], $config ];
        }
        throw new RuntimeException( 'No test vector defined for ' . $event );
    }

    private function run_trigger( string $event, array $definition, array $fixture ): array {
        [ $hook, $params, $config ] = $this->trigger_fixture( $event, $fixture );
        // Academy selectors are required by GraphValidator. 'any' is a real
        // selectable option for these trigger fields and prevents false failures.
        foreach ( Academy::get_trigger_config_schema( $event ) as $field ) {
            if ( ! empty( $field['required'] ) && ! array_key_exists( $field['key'], $config ) ) {
                if ( in_array( $field['key'], [ 'course_id', 'quiz_id', 'lesson_id' ], true ) ) {
                    $config[ $field['key'] ] = 'any';
                }
            }
        }
        $label = $definition['label'];
        $title = self::MARKER . ' ' . $this->token . ' | Trigger: ' . $label;
        $w = $this->workflow( 'Trigger: ' . $label, [
            [ 'type' => 'trigger', 'data' => [ 'app' => 'academy', 'event' => $event, 'config' => $config ] ],
            [ 'type' => 'action', 'data' => [ 'app' => 'wordpress', 'event' => 'create_post',
                'config' => [ 'post_title' => $title, 'post_status' => 'draft', 'post_type' => 'post',
                    'post_content' => 'ACADEMY_E2E_TOKEN:' . $this->token . ';simulated_trigger=' . $event ] ] ],
        ] );
        try {
            // Assert a created workflow is actually indexed for the exact hook.
            $found = array_filter( Query::get_active_workflows_for_event( $hook ), static fn( $t ) => (int) $t['workflow_id'] === $w );
            if ( ! $found ) { throw new RuntimeException( 'Zaplane active trigger map does not contain hook ' . $hook ); }
            $this->fire_isolated( $hook, $params );
            return $this->results( $w, $title ) + [ 'workflow' => $w ];
        } finally { $this->pause( $w ); }
    }

    public function triggers(): void {
        $this->initialize( true );
        $f = $this->fixtures();
        self::say( "Run token: {$this->token}. Trigger tests simulate Academy hook payloads and exercise real Zaplane workflow execution." );
        foreach ( Academy::get_triggers() as $event => $definition ) {
            $this->capture( 'TRIGGER', $event, fn() => $this->run_trigger( $event, $definition, $f ) );
        }
        $this->finish();
    }

    private function make_user( string $kind ): int {
        $login = 'zaplane_academy_test_' . $kind . '_' . substr( $this->token, -8 );
        $id = wp_create_user( $login, wp_generate_password( 30, true, true ), $login . '@example.test' );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $id = (int) $id; update_user_meta( $id, '_zaplane_academy_e2e_token', $this->token );
        $this->entry['users'][] = $id; $this->save();
        $u = new WP_User( $id );
        if ( 'instructor' === $kind ) {
            $u->set_role( get_role( 'academy_instructor' ) ? 'academy_instructor' : 'subscriber' );
            update_user_meta( $id, 'academy_instructor_status', 'approved' );
        } else { $u->set_role( get_role( 'academy_student' ) ? 'academy_student' : 'subscriber' ); }
        return $id;
    }

    private function action_fixtures( int $lesson ): array {
        $student = $this->make_user( 'student' );
        $instructor = $this->make_user( 'instructor' );
        $course = wp_insert_post( [ 'post_type' => 'academy_courses', 'post_status' => 'publish',
            'post_title' => self::MARKER . ' ' . $this->token . ' test course', 'post_author' => $this->author,
            'post_content' => 'Disposable LOCAL Academy integration test course.' ], true );
        if ( ! $course || is_wp_error( $course ) ) { throw new RuntimeException( 'Could not create Academy course fixture.' ); }
        $course = (int) $course;
        update_post_meta( $course, '_zaplane_academy_e2e_token', $this->token );
        $this->entry['course'] = $course; $this->entry['posts'][] = $course; $this->save();
        if ( ! $lesson && class_exists( '\\Academy\\Classes\\Query' ) && method_exists( '\\Academy\\Classes\\Query', 'lesson_insert' ) ) {
            // Create an actual Academy custom-table lesson when this Academy
            // version supports the public lesson-insert API. No real lessons touched.
            try {
                $lesson = (int) \Academy\Classes\Query::lesson_insert( [
                    'lesson_author' => $this->author,
                    'lesson_title' => self::MARKER . ' ' . $this->token . ' lesson',
                    'lesson_content' => 'Disposable E2E lesson', 'lesson_status' => 'publish',
                ] );
                if ( $lesson > 0 ) {
                    $this->entry['lessons'][] = $lesson; $this->save();
                    if ( method_exists( '\\Academy\\Classes\\Query', 'lesson_meta_insert' ) ) {
                        \Academy\Classes\Query::lesson_meta_insert( $lesson, [
                            '_zaplane_academy_e2e_token' => $this->token,
                            'featured_media' => 0, 'attachment' => 0, 'is_previewable' => 0,
                        ] );
                    }
                    self::say( 'Created real Academy test lesson: ' . $lesson );
                }
            } catch ( Throwable $e ) {
                self::say( 'Lesson fixture unavailable: ' . $e->getMessage() );
                $lesson = 0;
            }
        }
        if ( $lesson ) {
            // Only this newly-created test course is modified; any user-provided
            // existing lesson row remains untouched.
            $curriculum = (array) get_post_meta( $course, 'academy_course_curriculum', true );
            $curriculum[] = [ 'title' => 'E2E lesson section', 'topics' => [ [ 'id' => $lesson, 'type' => 'lesson', 'name' => 'E2E lesson' ] ] ];
            update_post_meta( $course, 'academy_course_curriculum', $curriculum );
        }
        return compact( 'student', 'instructor', 'course', 'lesson' );
    }

    private function verify_action( string $action, array $f, array $response ): void {
        global $wpdb;
        $course = $f['course'];
        $user = in_array( $action, [ 'assign-instructor', 'remove-instructor' ], true ) ? $f['instructor'] : $f['student'];
        $meta = static fn( string $key ) => in_array( $course, array_map( 'intval', get_user_meta( $user, $key, false ) ), true );
        $enrolled = static function () use ( $wpdb, $course, $user ): bool {
            return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='academy_enrolled' AND post_parent=%d AND post_author=%d AND post_status='completed' LIMIT 1", $course, $user ) );
        };
        $completed = static function () use ( $wpdb, $course, $user ): bool {
            return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_type='course_completed' AND comment_agent='academy' AND comment_post_ID=%d AND user_id=%d LIMIT 1", $course, $user ) );
        };
        $topics = json_decode( (string) get_user_meta( $user, 'academy_course_' . $course . '_completed_topics', true ), true );
        $topics = is_array( $topics ) ? $topics : [];
        $ok = [
            'enroll-course' => $enrolled(),
            'unenroll-course' => ! $enrolled(),
            'complete-course' => $completed(),
            'complete-lesson' => isset( $topics['lesson'][ $f['lesson'] ] ),
            'reset-course-progress' => ! $completed() && empty( $topics ) && $enrolled(),
            'add-to-wishlist' => $meta( 'academy_course_wishlist' ),
            'remove-from-wishlist' => ! $meta( 'academy_course_wishlist' ),
            'assign-instructor' => $meta( 'academy_instructor_course_id' ),
            'remove-instructor' => ! $meta( 'academy_instructor_course_id' ),
        ];
        if ( ! ( $ok[ $action ] ?? false ) ) { throw new RuntimeException( 'Academy state verification failed after ' . $action . ': ' . wp_json_encode( $response['action']['data'] ?? [] ) ); }
    }

    private function run_action( string $action, array $f ): array {
        if ( 'complete-lesson' === $action && ! $f['lesson'] ) { return [ 'skip' => true, 'reason' => 'Pass a real Academy lesson ID as argument 3 to run this test.' ]; }
        $actor = in_array( $action, [ 'assign-instructor', 'remove-instructor' ], true ) ? $f['instructor'] : $f['student'];
        $config = [ 'user_id' => (string) $actor, 'selectedCourse' => (string) $f['course'] ];
        if ( 'complete-course' === $action ) { $config['force_completion'] = true; }
        if ( 'reset-course-progress' === $action ) { $config['confirm_reset'] = true; }
        if ( 'complete-lesson' === $action ) { $config['selectedLesson'] = (string) $f['lesson']; }
        $label = Academy::get_actions()[ $action ]['label'];
        $title = self::MARKER . ' ' . $this->token . ' | Action: ' . $label;
        $observer_by_action = [
            'enroll-course' => [ 'user_enroll_course', 'academy/course/after_enroll', [ 'course_id' => 'any' ] ],
            'complete-course' => [ 'course_complete', 'academy/admin/course_complete_after', [ 'course_id' => 'any' ] ],
            'complete-lesson' => [ 'lesson_complete', 'academy/frontend/after_mark_topic_complete', [ 'course_id' => 'any', 'lesson_id' => 'any' ] ],
        ];
        $observer = 0; $observer_title = '';
        if ( isset( $observer_by_action[ $action ] ) ) {
            [ $event, $real_hook, $event_config ] = $observer_by_action[ $action ];
            $observer_title = self::MARKER . ' ' . $this->token . ' | Real trigger: ' . $event;
            $observer = $this->workflow( 'Real trigger: ' . $event, [
                [ 'type' => 'trigger', 'data' => [ 'app' => 'academy', 'event' => $event, 'config' => $event_config ] ],
                [ 'type' => 'action', 'data' => [ 'app' => 'wordpress', 'event' => 'create_post',
                    'config' => [ 'post_title' => $observer_title, 'post_status' => 'draft', 'post_type' => 'post',
                        'post_content' => 'ACADEMY_E2E_TOKEN:' . $this->token . ';REAL_TRIGGER=' . $event ] ] ],
            ] );
            $this->ensure_router( $real_hook );
        }
        $w = $this->workflow( 'Action: ' . $label, [
            [ 'type' => 'trigger', 'data' => [ 'app' => 'academy', 'event' => 'student_registered', 'config' => [] ] ],
            [ 'type' => 'action', 'data' => [ 'app' => 'wordpress', 'event' => 'create_post',
                'config' => [ 'post_title' => $title, 'post_status' => 'draft', 'post_type' => 'post',
                    'post_content' => 'ACADEMY_E2E_TOKEN:' . $this->token . ';action=' . $action ] ] ],
            [ 'type' => 'action', 'data' => [ 'app' => 'academy', 'event' => $action, 'config' => $config ] ],
        ] );
        try {
            $this->fire_isolated( 'academy/admin/after_register_student', [ $f['student'] ] );
            $result = $this->results( $w, $title, true );
            $this->verify_action( $action, $f, $result );
            if ( $observer ) {
                try {
                    $seen = $this->results( $observer, $observer_title );
                    $this->record( 'REAL-PASS', 'TRIGGER', $observer_by_action[ $action ][0],
                        'actual Academy operation; run=' . $seen['run'] . ' draft=' . $seen['post'], $observer, $seen['post'] );
                } catch ( Throwable $e ) {
                    $this->record( 'FAIL', 'TRIGGER', $observer_by_action[ $action ][0],
                        'real Academy hook not observed: ' . $e->getMessage() );
                }
            }
            return $result + [ 'workflow' => $w ];
        } finally { $this->pause( $w ); if ( $observer ) { $this->pause( $observer ); } }
    }

    public function actions( int $lesson ): void {
        $this->initialize( true );
        $f = $this->action_fixtures( $lesson );
        self::say( 'Real Academy action fixtures: ' . wp_json_encode( $f ) );
        // Ordered to keep enrollment active until all progress/wishlist/instructor tests finish.
        $order = [ 'enroll-course', 'complete-course', 'reset-course-progress', 'complete-lesson',
            'add-to-wishlist', 'remove-from-wishlist', 'assign-instructor', 'remove-instructor', 'unenroll-course' ];
        foreach ( $order as $action ) { $this->capture( 'ACTION', $action, fn() => $this->run_action( $action, $f ) ); }
        $this->finish();
    }

    private function finish(): void {
        $this->entry['report'] = $this->rows; $this->save();
        $counts = array_count_values( array_column( $this->rows, 'status' ) );
        self::say( '--- ' . wp_json_encode( $counts ) . ' ---' );
        $dir = WP_CONTENT_DIR . '/uploads/zaplane-academy-e2e';
        wp_mkdir_p( $dir );
        $file = $dir . '/report-' . $this->token . '.json';
        file_put_contents( $file, wp_json_encode( [ 'token' => $this->token, 'site' => site_url(), 'rows' => $this->rows ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
        self::say( 'REPORT: ' . $file );
        self::say( 'To clean ONLY these tagged fixtures: wp eval-file scripts/academy-e2e.php cleanup I_UNDERSTAND_TEST_DATA ' . $this->token );
    }

    public function cleanup( string $token ): void {
        self::local_only();
        if ( ! $token || ! isset( $this->index[ $token ] ) ) { self::die( 'Pass an existing exact token (shown in test report).' ); }
        $e = $this->index[ $token ];
        foreach ( (array) ( $e['workflows'] ?? [] ) as $id ) {
            $w = Workflow::find( (int) $id );
            if ( ! $w || strpos( (string) $w->title, self::MARKER . ' ' . $token . ' | ' ) !== 0 ) { continue; }
            $w->status = 'paused'; $w->save();
            foreach ( Run::where( 'workflow_id', (int) $id )->get() as $run ) {
                foreach ( NodeRun::where( 'run_id', (int) $run->id )->get() as $node ) {
                    if ( function_exists( 'as_unschedule_action' ) ) {
                        as_unschedule_action( 'zaplane_execute_node_run', [ 'node_run_id' => (int) $node->id ], 'zaplane' );
                    }
                    $node->delete();
                }
                $run->delete();
            }
            WorkflowVersion::where( 'workflow_id', (int) $id )->delete(); $w->delete();
        }
        Query::flush_trigger_map();
        foreach ( (array) ( $e['comments'] ?? [] ) as $id ) {
            $c = get_comment( (int) $id );
            if ( $c && in_array( (int) $c->comment_post_ID, (array) ( $e['posts'] ?? [] ), true ) ) { wp_delete_comment( (int) $id, true ); }
        }
        // Include receipts whose run failed before their ID could be indexed.
        global $wpdb;
        $loose_receipts = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_title LIKE %s AND post_content LIKE %s",
            $wpdb->esc_like( self::MARKER . ' ' . $token . ' | ' ) . '%',
            $wpdb->esc_like( 'ACADEMY_E2E_TOKEN:' . $token ) . '%'
        ) );
        foreach ( array_unique( array_merge( (array) ( $e['posts'] ?? [] ), array_map( 'intval', (array) $loose_receipts ) ) ) as $id ) {
            $post = get_post( (int) $id );
            if ( $post && strpos( (string) $post->post_content, 'ACADEMY_E2E_TOKEN:' . $token ) === 0 &&
                strpos( (string) $post->post_title, self::MARKER . ' ' . $token ) === 0 ) {
                wp_delete_post( (int) $id, true );
                continue;
            }

            if ( get_post_meta( (int) $id, '_zaplane_academy_e2e_token', true ) === $token ) { wp_delete_post( (int) $id, true ); }
        }
        global $wpdb;
        // Academy lessons live in custom tables, not wp_posts. Delete only a
        // lesson whose title still contains this EXACT test token.
        foreach ( (array) ( $e['lessons'] ?? [] ) as $id ) {
            $table = $wpdb->prefix . 'academy_lessons';
            $title = $wpdb->get_var( $wpdb->prepare( "SELECT lesson_title FROM `{$table}` WHERE ID = %d", (int) $id ) );
            if ( strpos( (string) $title, self::MARKER . ' ' . $token . ' lesson' ) !== 0 ) { continue; }
            $wpdb->delete( $wpdb->prefix . 'academy_lessonmeta', [ 'lesson_id' => (int) $id ], [ '%d' ] );
            $wpdb->delete( $table, [ 'ID' => (int) $id ], [ '%d' ] );
        }
        foreach ( (array) ( $e['users'] ?? [] ) as $id ) {
            $user = get_userdata( (int) $id );
            if ( $user && strpos( $user->user_login, 'zaplane_academy_test_' ) === 0 && get_user_meta( (int) $id, '_zaplane_academy_e2e_token', true ) === $token ) {
                if ( ! function_exists( 'wp_delete_user' ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; }
                wp_delete_user( (int) $id );
            }
        }
        unset( $this->index[ $token ] ); update_option( self::INDEX, $this->index, false );
        self::say( 'Tagged test workflows/posts/users cleaned: ' . $token . '. Report JSON was retained.' );
    }

    public static function main( array $args ): void {
        $cmd = (string) ( $args[0] ?? 'plan' );
        $suite = new self();
        if ( 'plan' === $cmd ) { $suite->plan(); return; }
        if ( ( $args[1] ?? '' ) !== self::CONFIRM ) { self::die( 'Writing commands require second argument: ' . self::CONFIRM ); }
        switch ( $cmd ) {
            case 'triggers': $suite->triggers(); break;
            case 'actions': $suite->actions( max( 0, (int) ( $args[2] ?? 0 ) ) ); break;
            case 'all': $suite->triggers(); $suite = new self(); $suite->actions( max( 0, (int) ( $args[2] ?? 0 ) ) ); break;
            case 'cleanup': $suite->cleanup( (string) ( $args[2] ?? '' ) ); break;
            default: self::die( 'Unknown command: plan | triggers | actions | all | cleanup' );
        }
    }
}

ZaplaneAcademyE2E::main( isset( $args ) && is_array( $args ) ? $args : [] );
