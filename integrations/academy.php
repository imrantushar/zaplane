<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Academy extends IntegrationBase
{
    public static function get_slug(): string
    {
        return 'academy';
    }

    public static function get_name(): string
    {
        return 'Academy LMS';
    }

    public static function get_icon(): string
    {
        return 'academy.svg';
    }

    public static function get_triggers(): array
    {
        return [
            'user_enroll_course' => [
                'label' => 'User enrolled in a course',
                'hook'  => 'academy/course/after_enroll'
            ],
            'academy_quiz_course_attempt' => [
                'label' => 'User attempted (submitted) a quiz',
                'hook'  => 'academy_quizzes/api/after_quiz_attempt_finished'
            ],
            'lesson_complete' => [
                'label' => 'User completed a lesson',
                'hook'  => 'academy/frontend/after_mark_topic_complete'
            ],
            'course_complete' => [
                'label' => 'User completed a course',
                'hook'  => 'academy/admin/course_complete_after'
            ],
            'quiz_target' => [
                'label' => 'User achieved target percentage on a quiz',
                'hook'  => 'academy_quizzes/api/after_quiz_attempt_finished'
            ],
        ];
    }

    public static function get_trigger_config_schema(string $trigger): array
    {
        if (in_array($trigger, [ 'user_enroll_course', 'course_complete' ], true)) {
            return [
                [
                    'key'      => 'course_id',
                    'label'    => 'Course',
                    'type'     => 'select',
                    'dynamic' => [
                        'integration' => 'academy',
                        'query'       => 'acourse',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
            ];
        }

        if (in_array($trigger, [ 'academy_quiz_course_attempt' ], true)) {
            return [
                [
                    'key'      => 'quiz_id',
                    'label'    => 'Quiz',
                    'type'     => 'select',
                    'dynamic' => [
                        'integration' => 'academy',
                        'query'       => 'quiz',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
            ];
        }

        if ('quiz_target' === $trigger) {
            return [
                [
                    'key'      => 'quiz_id',
                    'label'    => 'Quiz',
                    'type'     => 'select',
                    'dynamic' => [
                        'integration' => 'academy',
                        'query'       => 'quiz',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
                [
                    'key'      => 'target_percentage',
                    'label'    => 'Target Percentage (%)',
                    'type'     => 'number',
                    'required' => true,
                ],
            ];
        }

        if (in_array($trigger, [ 'lesson_complete' ], true)) {
            return [
                [
                    'key'      => 'lesson_id',
                    'label'    => 'Lesson',
                    'type'     => 'select',
                    'dynamic' => [
                        'integration' => 'academy',
                        'query'       => 'lesson',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
            ];
        }

        return [];
    }

    public static function get_trigger_sample_output(string $trigger): array
    {
        $samples = [
            'user_enroll_course'           => [
                'success'    => true,
                'course_id'  => 1,
                'enroll_id'  => 1,
                'user_id'    => 1,
                'user_email' => 'student@example.com',
                'first_name' => 'Jane',
                'last_name'  => 'Smith',
                'username'   => 'janesmith',
            ],
            'course_complete'              => [
                'success'      => true,
                'course_id'    => 1,
                'course_title' => 'Sample Course',
                'course_url'   => 'https://example.com/course/sample-course',
                'user_id'      => 1,
                'user_email'   => 'student@example.com',
                'first_name'   => 'Jane',
                'last_name'    => 'Smith',
            ],
            'lesson_complete'              => [
                'success'   => true,
                'lesson_id' => 1,
                'user_id'   => 1,
            ],
            'academy_quiz_course_attempt'  => [
                'success' => true,
                'quiz_id' => 1,
                'user_id' => 1,
                'score'   => 8,
                'total'   => 10,
            ],
            'quiz_target'                  => [
                'success'     => true,
                'quiz_id'     => 1,
                'user_id'     => 1,
                'score'       => 8,
                'total_marks' => 10,
                'percentage'  => 80.00,
            ],
        ];

        return $samples[ $trigger ] ?? [];
    }

    public static function resolve_trigger(array $node, array $args)
    {
        switch ($node['event']) {
            case 'user_enroll_course':
                $course_id = $args[0] ?? null;
                $enroll_id = $args[1] ?? null;
                $user_id   = $args[2] ?? null;

                if (! $course_id || ! $enroll_id) {
                    return false;
                }

                $selected_course = $node['data']['config']['course_id'] ?? 'any';

                if ('any' !== $selected_course && (int) $selected_course !== (int) $course_id) {
                    return false;
                }

                $user = $user_id ? get_userdata((int) $user_id) : null;

                return [
                    'success'    => true,
                    'course_id'  => (int) $course_id,
                    'enroll_id'  => (int) $enroll_id,
                    'user_id'    => $user ? (int) $user->ID : null,
                    'user_email' => $user ? $user->user_email : null,
                    'first_name' => $user ? $user->first_name : null,
                    'last_name'  => $user ? $user->last_name : null,
                    'username'   => $user ? $user->user_login : null,
                ];

            case 'course_complete':
                $course_id = $args[0] ?? null;
                $user_id   = $args[1] ?? get_current_user_id();

                if (! $course_id || ! $user_id) {
                    return false;
                }

                $selected_course = $node['data']['config']['course_id'] ?? 'any';

                if ('any' !== $selected_course && (int) $selected_course !== (int) $course_id) {
                    return false;
                }

                $course = get_post($course_id);
                $user   = get_userdata($user_id);

                if (! $course || ! $user) {
                    return false;
                }

                return [
                    'success'      => true,
                    'course_id'    => $course->ID,
                    'course_title' => $course->post_title,
                    'course_url'   => get_permalink($course->ID),
                    'user_id'      => $user_id,
                    'user_email'   => $user->user_email,
                    'first_name'   => $user->first_name,
                    'last_name'    => $user->last_name,
                ];

            case 'lesson_complete':
                $lesson_id = $args[0] ?? null;
                $user_id   = $args[1] ?? get_current_user_id();

                if (! $lesson_id || ! $user_id) {
                    return false;
                }

                $selected_lesson = $node['data']['config']['lesson_id'] ?? 'any';

                if ('any' !== $selected_lesson && (int) $selected_lesson !== (int) $lesson_id) {
                    return false;
                }

                return [
                    'success'   => true,
                    'lesson_id' => $lesson_id,
                    'user_id'   => $user_id,
                ];

            case 'academy_quiz_course_attempt':
                $attempt = $args[0] ?? null;
                if (! $attempt) {
                    return false;
                }

                if ('pending' === $attempt->attempt_status) {
                    return false;
                }

                $quiz_id = $attempt->quiz_id ?? null;
                $user_id = $attempt->user_id ?? null;

                if (! $quiz_id || ! $user_id) {
                    return false;
                }

                $selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

                if ('any' !== $selected_quiz && (int) $selected_quiz !== (int) $quiz_id) {
                    return false;
                }

                return [
                    'success' => true,
                    'quiz_id' => $quiz_id,
                    'user_id' => $user_id,
                    'score'   => $attempt->earned_marks ?? 0,
                    'total'   => $attempt->total_marks ?? 0,
                ];

            case 'quiz_target':
                $attempt = $args[0] ?? null;
                if (! $attempt) {
                    return false;
                }

                if ('pending' === $attempt->attempt_status) {
                    return false;
                }

                $quiz_id = $attempt->quiz_id ?? null;
                $user_id = $attempt->user_id ?? null;
                $earned  = $attempt->earned_marks ?? 0;
                $total   = $attempt->total_marks ?? 0;

                if (! $quiz_id || ! $user_id || ! $total) {
                    return false;
                }

                $selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

                if ('any' !== $selected_quiz && (int) $selected_quiz !== (int) $quiz_id) {
                    return false;
                }

                $percentage = ($earned / $total) * 100;
                $target     = (float) ($node['data']['config']['target_percentage'] ?? 0);

                if ($percentage < $target) {
                    return false;
                }

                return [
                    'success'     => true,
                    'quiz_id'     => $quiz_id,
                    'user_id'     => $user_id,
                    'score'       => $earned,
                    'total_marks' => $total,
                    'percentage'  => round($percentage, 2),
                ];
        }//end switch

        return false;
    }

    // -------------------------------------------------------------------------
    // ACTIONS
    // -------------------------------------------------------------------------

    public static function get_actions(): array
    {
        return [
            'enroll_user_in_course'     => [
                'label' => 'Enroll user in a course',
            ],
            'unenroll_user_from_course' => [
                'label' => 'Unenroll user from a course',
            ],
            'mark_lesson_complete'      => [
                'label' => 'Mark a lesson as complete for a user',
            ],
        ];
    }

    public static function get_action_config_schema(string $action): array
    {
        $schemas = [

            'enroll_user_in_course' => [
                [
                    'key'      => 'user_id',
                    'label'    => 'User',
                    'type'     => 'select',
                    'dynamic'  => [
                        'integration' => 'academy',
                        'query'       => 'user',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
                [
                    'key'      => 'course_id',
                    'label'    => 'Course',
                    'type'     => 'select',
                    'dynamic'  => [
                        'integration' => 'academy',
                        'query'       => 'acourse_no_any',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
            ],

            'unenroll_user_from_course' => [
                [
                    'key'      => 'user_id',
                    'label'    => 'User',
                    'type'     => 'select',
                    'dynamic'  => [
                        'integration' => 'academy',
                        'query'       => 'user',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
                [
                    'key'      => 'course_id',
                    'label'    => 'Course',
                    'type'     => 'select',
                    'dynamic'  => [
                        'integration' => 'academy',
                        'query'       => 'acourse_no_any',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
            ],

            'mark_lesson_complete' => [
                [
                    'key'      => 'user_id',
                    'label'    => 'User',
                    'type'     => 'select',
                    'dynamic'  => [
                        'integration' => 'academy',
                        'query'       => 'user',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
                [
                    'key'      => 'lesson_id',
                    'label'    => 'Lesson',
                    'type'     => 'select',
                    'dynamic'  => [
                        'integration' => 'academy',
                        'query'       => 'lesson_no_any',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
                [
                    'key'      => 'course_id',
                    'label'    => 'Course',
                    'type'     => 'select',
                    'dynamic'  => [
                        'integration' => 'academy',
                        'query'       => 'acourse_no_any',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
            ],
        ];

        return $schemas[ $action ] ?? [];
    }

    // -------------------------------------------------------------------------
    // Action: Enroll user in a course
    //
    // FIX (Bug 1): Renamed from private action_enroll_user() and made public
    //              so Zaplane can call Academy::enroll_user_in_course() directly.
    // FIX (Bug 2): Academy LMS stores enrollments as posts with post_parent set
    //              to the course ID. Replaced meta_query + update_post_meta with
    //              post_parent in both the duplicate-check query and wp_insert_post.
    // -------------------------------------------------------------------------

    public static function enroll_user_in_course(array $node, array $input): array
    {
        $user_id   = (int) ($node['data']['config']['user_id']   ?? 0);
        $course_id = (int) ($node['data']['config']['course_id'] ?? 0);

        $fail = static function (string $message) use ($input): array {
            return ['port' => 'main', 'data' => array_merge($input, [
                'success' => false,
                'message' => $message,
            ])];
        };

        if (! $user_id || ! $course_id) {
            return $fail('user_id and course_id are required.');
        }
        if (! class_exists('\Academy\Helper') || ! method_exists('\Academy\Helper', 'do_enroll')) {
            return $fail('Academy LMS is not active.');
        }
        if (! get_userdata($user_id)) {
            return $fail('User not found.');
        }
        if (get_post_type($course_id) !== 'academy_courses') {
            return $fail('Course not found.');
        }

        // Academy stores enrollment as the `academy_enrolled` CPT (status `completed`).
        // Check via the plugin's own helper so we match its exact convention.
        $existing = \Academy\Helper::is_enrolled($course_id, $user_id, 'any');
        if ($existing && 'cancel' !== ($existing->enrolled_status ?? '')) {
            return ['port' => 'main', 'data' => array_merge($input, [
                'success'   => false,
                'message'   => 'User is already enrolled in this course.',
                'enroll_id' => (int) $existing->ID,
                'user_id'   => $user_id,
                'course_id' => $course_id,
            ])];
        }

        // Canonical enrollment: correct CPT/status, marks the user as an
        // academy_student, clears caches, and fires academy/course/after_enroll.
        $enroll_id = \Academy\Helper::do_enroll($course_id, $user_id);

        if (! $enroll_id) {
            return $fail('Enrollment failed.');
        }

        return ['port' => 'main', 'data' => array_merge($input, [
            'success'   => true,
            'enroll_id' => (int) $enroll_id,
            'user_id'   => $user_id,
            'course_id' => $course_id,
        ])];
    }

    // -------------------------------------------------------------------------
    // Action: Unenroll user from a course
    //
    // FIX (Bug 1): Renamed from private action_unenroll_user() and made public.
    // FIX (Bug 2): Query uses post_parent instead of meta_query.
    // -------------------------------------------------------------------------

    public static function unenroll_user_from_course(array $node, array $input): array
    {
        $user_id   = (int) ($node['data']['config']['user_id']   ?? 0);
        $course_id = (int) ($node['data']['config']['course_id'] ?? 0);

        if (! $user_id || ! $course_id) {
            return [
                'port' => 'main',
                'data' => array_merge($input, [
                    'success' => false,
                    'message' => 'user_id and course_id are required.',
                ]),
            ];
        }

        $enrollments = get_posts([
            'post_type'      => 'academy_enrollment',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'author'         => $user_id,
            'post_parent'    => $course_id,   // FIX: was meta_query on _academy_course_id
        ]);

        if (empty($enrollments)) {
            return [
                'port' => 'main',
                'data' => array_merge($input, [
                    'success' => false,
                    'message' => 'No enrollment record found for this user and course.',
                ]),
            ];
        }

        $deleted_ids = [];
        foreach ($enrollments as $enrollment) {
            $result = wp_delete_post($enrollment->ID, true);
            if ($result) {
                $deleted_ids[] = $enrollment->ID;
            }
        }

        // Allow other plugins / Zaplane flows to react.
        do_action('academy/course/after_unenroll', $course_id, $user_id, $deleted_ids);

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'success'     => true,
                'user_id'     => $user_id,
                'course_id'   => $course_id,
                'deleted_ids' => $deleted_ids,
            ]),
        ];
    }

    // -------------------------------------------------------------------------
    // Action: Mark lesson complete for a user
    //
    // FIX (Bug 1): Renamed from private action_mark_lesson_complete() and made public.
    // -------------------------------------------------------------------------

    public static function mark_lesson_complete(array $node, array $input): array
    {
        $user_id   = (int) ($node['data']['config']['user_id']   ?? 0);
        $lesson_id = (int) ($node['data']['config']['lesson_id'] ?? 0);
        $course_id = (int) ($node['data']['config']['course_id'] ?? 0);

        if (! $user_id || ! $lesson_id || ! $course_id) {
            return [
                'port' => 'main',
                'data' => array_merge($input, [
                    'success' => false,
                    'message' => 'user_id, lesson_id, and course_id are required.',
                ]),
            ];
        }

        // Academy LMS tracks completed topics in user meta as a serialized array
        // keyed by course_id: _academy_completed_topics_{course_id} => [ lesson_id, ... ]
        $meta_key         = '_academy_completed_topics_' . $course_id;
        $completed_topics = get_user_meta($user_id, $meta_key, true);

        if (! is_array($completed_topics)) {
            $completed_topics = [];
        }

        if (in_array($lesson_id, $completed_topics, true)) {
            return [
                'port' => 'main',
                'data' => array_merge($input, [
                    'success'   => false,
                    'message'   => 'Lesson is already marked as complete for this user.',
                    'user_id'   => $user_id,
                    'lesson_id' => $lesson_id,
                    'course_id' => $course_id,
                ]),
            ];
        }

        $completed_topics[] = $lesson_id;
        update_user_meta($user_id, $meta_key, $completed_topics);

        // Fire the hook Academy uses for lesson completion so other flows fire too.
        do_action('academy/frontend/after_mark_topic_complete', $lesson_id, $user_id);

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'success'   => true,
                'user_id'   => $user_id,
                'lesson_id' => $lesson_id,
                'course_id' => $course_id,
            ]),
        ];
    }

    // -------------------------------------------------------------------------
    // Dynamic queries
    // -------------------------------------------------------------------------

    public static function get_dynamic_queries(): array
    {
        return [
            'acourse'        => [ self::class, 'query_courses' ],
            'acourse_no_any' => [ self::class, 'query_courses_no_any' ],
            'quiz'           => [ self::class, 'query_quiz' ],
            'lesson'         => [ self::class, 'query_lesson' ],
            'lesson_no_any'  => [ self::class, 'query_lesson_no_any' ],
            'user'           => [ self::class, 'query_users' ],
        ];
    }

    public static function query_courses(): array
    {
        $options = [
            [
                'label' => 'Any course',
                'name'  => 'any',
            ],
        ];

        if (class_exists('Academy')) {
            $courses = get_posts([
                'post_type'      => 'academy_courses',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);

            foreach ($courses as $course) {
                $options[] = [
                    'label' => $course->post_title,
                    'name'  => $course->ID,
                ];
            }
        }

        return $options;
    }

    /**
     * Same as query_courses() but without the "Any course" option.
     * Used by action config schemas where a specific course must be chosen.
     */
    public static function query_courses_no_any(): array
    {
        $options = [];

        if (class_exists('Academy')) {
            $courses = get_posts([
                'post_type'      => 'academy_courses',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);

            foreach ($courses as $course) {
                $options[] = [
                    'label' => $course->post_title,
                    'name'  => $course->ID,
                ];
            }
        }

        return $options;
    }

    public static function query_quiz(): array
    {
        $options = [
            [
                'label' => 'Any Quiz',
                'name'  => 'any',
            ],
        ];

        if (class_exists('Academy')) {
            $quizzes = get_posts([
                'post_type'      => 'academy_quiz',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);

            foreach ($quizzes as $quiz) {
                $options[] = [
                    'label' => $quiz->post_title,
                    'name'  => $quiz->ID,
                ];
            }
        }

        return $options;
    }

    public static function query_lesson(): array
    {
        $options = [
            [
                'label' => 'Any lesson',
                'name'  => 'any',
            ],
        ];

        if (class_exists('Academy')) {
            $lessons = \Academy\Lesson\LessonApi\Lesson::get(0, -1, 0, '', '', true);

            if (! empty($lessons)) {
                foreach ($lessons as $lesson) {
                    $lesson_data = (object) $lesson->get_data();
                    $options[]   = [
                        'label' => $lesson_data->lesson_title,
                        'name'  => $lesson_data->ID,
                    ];
                }
            }
        }

        return $options;
    }

    /**
     * Same as query_lesson() but without the "Any lesson" option.
     * Used by action config schemas where a specific lesson must be chosen.
     */
    public static function query_lesson_no_any(): array
    {
        $options = [];

        if (class_exists('Academy')) {
            $lessons = \Academy\Lesson\LessonApi\Lesson::get(0, -1, 0, '', '', true);

            if (! empty($lessons)) {
                foreach ($lessons as $lesson) {
                    $lesson_data = (object) $lesson->get_data();
                    $options[]   = [
                        'label' => $lesson_data->lesson_title,
                        'name'  => $lesson_data->ID,
                    ];
                }
            }
        }
    }
    /**
     * WordPress users list — action config-এ User dropdown-এর জন্য।
     */
    public static function query_users(): array
    {
        $options = [];

        $users = get_users([
            'fields'  => [ 'ID', 'display_name', 'user_email' ],
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 200,
        ]);

        foreach ($users as $user) {
            $options[] = [
                'label' => $user->display_name . ' (' . $user->user_email . ')',
                'name'  => $user->ID,
            ];
        }

        return $options;
    }
}
