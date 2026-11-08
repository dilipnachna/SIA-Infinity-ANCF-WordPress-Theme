<?php
declare(strict_types=1);

namespace SIA\ANCF\ThemeEditorial;

final class Workspace
{
    public const META = '_ancf_editorial_record';
    public const AUDIT = '_ancf_editorial_audit';
    private static string $file;
    private static array $blocked = [];

    public static function boot(string $file): void
    {
        self::$file = $file;
        add_action('add_meta_boxes_post', [self::class, 'addBox']);
        add_action('save_post_post', [self::class, 'checkConcurrency'], 5, 3);
        add_action('save_post_post', [self::class, 'save'], 100, 3);
        add_action('admin_enqueue_scripts', [self::class, 'styles']);
        add_action('admin_notices', [self::class, 'notices']);
        add_action('plugins_loaded', [self::class, 'retireStandaloneHooks'], 1000);
        add_filter('sia_publisher_intelligence_context', [self::class, 'publisherContext'], 30, 2);
        add_action('sia_publisher_intelligence_after_sections', [self::class, 'publisherSection'], 30, 2);
    }

    public static function retireStandaloneHooks(): void
    {
        // Handles the brief update window before the standalone plugin is deactivated.
        if (class_exists('ANCF\\Editorial\\Workspace')) {
            foreach (['add_meta_boxes_post' => 'addBox', 'admin_enqueue_scripts' => 'styles', 'admin_notices' => 'notices'] as $hook => $method) {
                remove_action($hook, ['ANCF\\Editorial\\Workspace', $method]);
            }
            remove_action('save_post_post', ['ANCF\\Editorial\\Workspace', 'save'], 100);
        }
    }

    public static function shared(int $id): array
    {
        $shared = [];
        foreach (['_sia_content_type', '_sia_editorial_state', '_sia_ai_assistance', '_sia_commercial_type', '_sia_primary_silo_term_id'] as $key) {
            $shared[$key] = (string) get_post_meta($id, $key, true);
        }
        return $shared;
    }

    public static function publisherContext(array $context, \WP_Post $post): array
    {
        $saved = self::load($post->ID);
        $context['ancf_editorial'] = ['schema_version' => $saved['schema_version'] ?? null,
            'has_record' => $saved !== [], 'review_current' => Record::reviewIsCurrent($saved, self::subject($post))];
        // Do not report missing assistance metadata as a declaration of no AI.
        if (self::shared($post->ID)['_sia_ai_assistance'] === '') {
            $context['ai_assistance'] = 'not_recorded';
        }
        return $context;
    }

    public static function publisherSection(\WP_Post $post, array $context): void
    {
        echo '<hr><h3>Editorial evidence · 9W+4H</h3>';
        self::render($post);
    }

    public static function addBox(): void
    {
        if (class_exists('SIA_Publisher_Intelligence')) {
            return; // Inside the theme's existing Publisher Intelligence panel.
        }
        add_meta_box('ancf-editorial', 'ANCF Editorial · लेख की तैयारी', [self::class, 'render'],
            'post', 'normal', 'high', ['__block_editor_compatible_meta_box' => true]);
    }

    public static function styles(): void
    {
        $screen = get_current_screen();
        if ($screen && $screen->base === 'post' && $screen->post_type === 'post') {
            wp_enqueue_style('sia-ancf-editorial', get_template_directory_uri() . '/inc/ancf-core/editorial-workspace.css', [], SIA_NATIVE_ANCF_EDITORIAL_VERSION);
        }
    }

    public static function subject(\WP_Post $post): array
    {
        $categoryIds = wp_get_post_categories($post->ID);
        $tagIds = wp_get_post_tags($post->ID, ['fields' => 'ids']);
        sort($categoryIds, SORT_NUMERIC);
        sort($tagIds, SORT_NUMERIC);
        return ['id' => $post->ID, 'title' => $post->post_title, 'content' => $post->post_content,
            'excerpt' => $post->post_excerpt, 'author_id' => (int) $post->post_author,
            'category_ids' => $categoryIds, 'tag_ids' => $tagIds,
            'featured_image_id' => (int) get_post_thumbnail_id($post->ID), 'sia_core' => self::shared($post->ID)];
    }

    public static function load(int $id): array
    {
        $raw = get_post_meta($id, self::META, true);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function render(\WP_Post $post): void
    {
        $saved = self::load($post->ID);
        try {
            $record = Record::editorialFromEnvelope($saved);
            if (!$saved && str_starts_with((string) wp_parse_url(home_url('/'), PHP_URL_HOST), 'hindi.')) {
                $record['language'] = 'hi';
            }
        } catch (\InvalidArgumentException $error) {
            echo '<p class="ancf-warning">Stored record could not be read safely. Original metadata has not been modified. ' . esc_html($error->getMessage()) . '</p>';
            return;
        }
        $author = get_userdata((int) $post->post_author);
        $names = wp_get_post_categories($post->ID, ['fields' => 'names']);
        wp_nonce_field('ancf_save_' . $post->ID, 'ancf_nonce');
        $shared = self::shared($post->ID);
        echo '<div class="ancf-workspace"><p class="ancf-banner">SIA ANCF Core · Editorial evidence. No automatic fact checking or publishing approval.</p>';
        echo '<input type="hidden" name="ancf_present" value="1">';
        echo '<input type="hidden" name="ancf_expected" value="' . esc_attr(hash('sha256', (string) get_post_meta($post->ID, self::META, true))) . '">';
        echo '<input type="hidden" name="ancf_shared_expected" value="' . esc_attr(hash('sha256', (string) wp_json_encode($shared))) . '">';
        echo '<p class="ancf-summary"><strong>Author:</strong> ' . esc_html($author ? $author->display_name : 'Not set')
            . ' <span>·</span> <strong>Category:</strong> ' . esc_html(implode(', ', $names))
            . ' <span>·</span> <strong>Post:</strong> ' . esc_html((string) $post->ID)
            . '. Author/category बदलने के लिए WordPress के अपने controls इस्तेमाल करें।</p>';
        echo '<div class="ancf-grid">';
        echo '<p><strong>Core content type:</strong> ' . esc_html($shared['_sia_content_type'] ?: 'दर्ज नहीं')
            . ' · <strong>AI assistance:</strong> ' . esc_html($shared['_sia_ai_assistance'] ?: 'दर्ज नहीं')
            . ' · <strong>Editorial state:</strong> ' . esc_html($shared['_sia_editorial_state'] ?: 'दर्ज नहीं')
            . '. इन्हें SIA ANCF Story controls में चुनें।</p>';
        $legacy = Record::legacyClassification($saved);
        if ($legacy !== null) {
            echo '<p class="ancf-warning">पुराने plugin की classification सुरक्षित रखी गई है: ' . esc_html((string) wp_json_encode($legacy, JSON_UNESCAPED_UNICODE))
                . '. Core में सही विकल्प स्वयं चुनें; पुराने yes/no को किसी AI stage में अपने आप नहीं बदला गया है।</p>';
        }
        self::select('language', 'भाषा', ['en' => 'English', 'hi' => 'हिन्दी'], $record['language']);
        self::select('basis', 'लेख का आधार', Record::BASIS, $record['basis']);
        echo '</div>';
        self::textarea('source_log', 'स्रोतों का रिकॉर्ड', $record['source_log'], 'URL/दस्तावेज़, संबंधित दावा और जाँच की तारीख दर्ज करें।');
        self::textarea('provenance_note', 'अनुभव, AI सहायता और सीमा', $record['provenance_note'], 'Firsthand अनुभव केवल लेखक से मिली वास्तविक जानकारी पर आधारित हो।');
        echo '<details class="ancf-questions" open><summary>9W + 4H · रिपोर्ट की जानकारी और साक्ष्य</summary>';
        echo '<p>हर प्रश्न पर विचार करें। अज्ञात या लागू नहीं दर्ज करना स्वीकार्य है। कोई completion score या automatic approval नहीं दिया जाएगा।</p>';
        foreach (Record::QUESTIONS as $key => [$label, $hint]) {
            $row = $record['questions'][$key];
            $prefix = 'questions][' . $key;
            echo '<section class="ancf-question"><h4>' . esc_html($label) . ' <small>' . esc_html($hint) . '</small></h4>';
            self::textarea($prefix . '][answer', 'उत्तर / लागू नहीं होने का कारण', $row['answer']);
            echo '<div class="ancf-grid">';
            self::select($prefix . '][state', 'साक्ष्य की स्थिति', Record::STATES, $row['state']);
            echo '<label class="ancf-field">जाँच की तारीख<input type="date" aria-label="जाँच की तारीख" name="ancf[' . esc_attr($prefix . '][checked_on') . ']" value="' . esc_attr($row['checked_on']) . '"></label></div>';
            self::textarea($prefix . '][source', 'स्रोत / attribution', $row['source']);
            echo '</section>';
        }
        echo '</details>';
        self::textarea('editor_note', 'बाकी सवाल, सुधार या संपादकीय नोट', $record['editor_note']);
        echo '<div class="ancf-review"><h4>समीक्षा का रिकॉर्ड</h4>';
        if (isset($saved['review']) && is_array($saved['review']) && isset($saved['review']['account_id'], $saved['review']['recorded_at_utc'])) {
            $review = $saved['review'];
            $reviewer = get_userdata((int) $review['account_id']);
            $current = Record::reviewIsCurrent($saved, self::subject($post));
            echo '<p>' . esc_html(($reviewer ? $reviewer->display_name : 'WordPress account ' . $review['account_id']) . ' · ' . $review['recorded_at_utc']) . '</p>';
            echo '<p class="' . ($current ? 'ancf-confirmed' : 'ancf-warning') . '">' . ($current ? 'समीक्षा इस version से मेल खाती है।' : 'सामग्री या editorial record बदला है; पिछली समीक्षा इस version पर लागू नहीं है।') . '</p>';
        } else {
            echo '<p>अभी कोई समीक्षा दर्ज नहीं है।</p>';
        }
        echo '<label><input type="checkbox" name="ancf_record_review" value="1"> मैंने स्वयं इस version की समीक्षा की है; मेरे WordPress account और समय के साथ दर्ज करें।</label>';
        echo '<p class="ancf-muted">यह account की स्पष्ट घोषणा है, मानव पहचान या ANCF approval का स्वतंत्र प्रमाण नहीं। सामान्य Save/Update से record सेव होगा।</p></div></div>';
    }

    private static function select(string $key, string $label, array $options, string $value): void
    {
        echo '<label class="ancf-field">' . esc_html($label) . '<select aria-label="' . esc_attr($label) . '" name="ancf[' . esc_attr($key) . ']">';
        foreach ($options as $id => $name) {
            echo '<option value="' . esc_attr($id) . '"' . selected($value, $id, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select></label>';
    }

    private static function textarea(string $key, string $label, string $value, string $hint = ''): void
    {
        echo '<label class="ancf-field">' . esc_html($label) . '<textarea aria-label="' . esc_attr($label) . '" rows="2" name="ancf[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
        if ($hint !== '') {
            echo '<small>' . esc_html($hint) . '</small>';
        }
        echo '</label>';
    }

    public static function save(int $id, \WP_Post $post, bool $update): void
    {
        if (isset(self::$blocked[$id])) {
            return;
        }
        if ($post->post_type !== 'post' || !isset($_POST['ancf_present']) || wp_is_post_revision($id)
            || wp_is_post_autosave($id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        if (!current_user_can('edit_post', $id) || !isset($_POST['ancf_nonce']) || !is_string($_POST['ancf_nonce'])
            || !wp_verify_nonce(wp_unslash($_POST['ancf_nonce']), 'ancf_save_' . $id)) {
            return;
        }
        $oldRaw = (string) get_post_meta($id, self::META, true);
        $expected = $_POST['ancf_expected'] ?? '';
        if (!is_string($expected) || !hash_equals(hash('sha256', $oldRaw), wp_unslash($expected))) {
            self::error($id, 'Editorial record किसी और ने बदला है। Post content सेव हो सकता है; editorial fields overwrite नहीं किए गए। Reload करके जानकारी मिलाएँ।');
            return;
        }
        try {
            if (!isset($_POST['ancf']) || !is_array($_POST['ancf'])) {
                throw new \InvalidArgumentException('Editorial fields missing.');
            }
            $record = Record::normalize(wp_unslash($_POST['ancf']));
            $old = self::load($id);
            $subject = self::subject($post);
            $fingerprint = Record::fingerprint($record, $subject);
            $review = $old['review'] ?? null;
            if (isset($_POST['ancf_record_review']) && $_POST['ancf_record_review'] === '1') {
                $review = ['account_id' => get_current_user_id(), 'recorded_at_utc' => gmdate('c'),
                    'method' => 'explicit_account_attestation', 'fingerprint' => $fingerprint];
            }
            $envelope = ['schema_version' => 2, 'editorial' => $record, 'review' => $review,
                'saved_by' => get_current_user_id(), 'saved_at_utc' => gmdate('c'), 'fingerprint' => $fingerprint];
            $legacy = Record::legacyClassification($old);
            if ($legacy !== null) {
                $envelope['legacy_classification'] = $legacy;
            }
            $encoded = wp_json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                throw new \InvalidArgumentException('Editorial record could not be encoded.');
            }
            $saved = update_post_meta($id, self::META, wp_slash($encoded));
            if (!$saved && get_post_meta($id, self::META, true) !== $encoded) {
                self::error($id, 'Editorial metadata save विफल हुआ। Post content और editorial record की स्थिति अलग जाँचें।');
                return;
            }
            $audit = ['event' => 'editorial_record_saved', 'post_id' => $id, 'account_id' => get_current_user_id(),
                'at_utc' => gmdate('c'), 'before_sha256' => hash('sha256', $oldRaw), 'after_sha256' => hash('sha256', $encoded),
                'post_status' => $post->post_status, 'author_id' => $subject['author_id'],
                'category_ids' => $subject['category_ids'], 'schema_version' => 2,
                'module_version' => SIA_NATIVE_ANCF_EDITORIAL_VERSION, 'shared_classification' => $subject['sia_core'],
                'review_current' => Record::reviewIsCurrent($envelope, $subject)];
            $auditSaved = add_post_meta($id, self::AUDIT, wp_slash((string) wp_json_encode($audit)));
            if (!$auditSaved) {
                self::error($id, 'Editorial record सेव हुआ, लेकिन audit event save विफल हुआ।');
            }
        } catch (\InvalidArgumentException $error) {
            self::error($id, 'Editorial record सेव नहीं हुआ: ' . $error->getMessage() . ' Post content अलग से सेव हो सकता है।');
        }
    }

    public static function checkConcurrency(int $id, \WP_Post $post, bool $update): void
    {
        unset(self::$blocked[$id]);
        if (!isset($_POST['ancf_present']) || wp_is_post_revision($id) || wp_is_post_autosave($id)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $id)
            || !isset($_POST['ancf_nonce']) || !is_string($_POST['ancf_nonce'])
            || !wp_verify_nonce(wp_unslash($_POST['ancf_nonce']), 'ancf_save_' . $id)) {
            return;
        }
        $expected = $_POST['ancf_shared_expected'] ?? null;
        $ownExpected = $_POST['ancf_expected'] ?? null;
        $sharedHash = hash('sha256', (string) wp_json_encode(self::shared($id)));
        $ownHash = hash('sha256', (string) get_post_meta($id, self::META, true));
        if (!is_string($expected) || !hash_equals($sharedHash, wp_unslash($expected))
            || !is_string($ownExpected) || !hash_equals($ownHash, wp_unslash($ownExpected))) {
            self::$blocked[$id] = true;
            // Core/Silo writers run at priorities 10/20; prevent stale form metadata writes.
            unset($_POST['sia_ancf_story_nonce'], $_POST['sia_primary_silo_nonce']);
            self::error($id, 'Core या editorial record किसी और ने बदला है। Post content सेव हो सकता है; metadata overwrite नहीं हुआ। Reload करके जानकारी मिलाएँ।');
        }
    }

    private static function error(int $id, string $message): void
    {
        set_transient('ancf_error_' . get_current_user_id() . '_' . $id, $message, 300);
    }

    public static function notices(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'post') {
            return;
        }
        $id = isset($_GET['post']) && is_scalar($_GET['post']) ? absint($_GET['post']) : 0;
        $key = 'ancf_error_' . get_current_user_id() . '_' . $id;
        $message = get_transient($key);
        if ($message) {
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            delete_transient($key);
        }
    }
}
