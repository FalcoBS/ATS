<?php
/**
 * Advanced Threads System - Shortcodes
 * 
 * @package AdvancedThreadsSystem
 * @subpackage Shortcodes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ATS_Shortcodes {
    
    private static $instance = null;
    private $thread_manager;
    private $user_manager;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->thread_manager = new ATS_Thread_Manager();
        $this->user_manager = new ATS_User_Manager();
        
        $this->init_shortcodes();
    }
    
    /**
     * Initialize all shortcodes
     */
    private function init_shortcodes() {
        add_shortcode('ats_threads_listing', array($this, 'threads_listing'));
        add_shortcode('ats_user_profile', array($this, 'user_profile'));
        add_shortcode('ats_create_thread_form', array($this, 'create_thread_form'));
        add_shortcode('ats_leaderboard', array($this, 'leaderboard'));
        add_shortcode('ats_thread_categories', array($this, 'thread_categories'));
        add_shortcode('ats_recent_replies', array($this, 'recent_replies'));
        add_shortcode('ats_user_stats', array($this, 'user_stats'));
        add_shortcode('ats_popular_threads', array($this, 'popular_threads'));
        add_shortcode('ats_thread_search', array($this, 'thread_search'));
        add_shortcode('ats_user_card', array($this, 'user_card'));
    }
    
    /**
     * Threads listing shortcode
     */
    public function threads_listing($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'author' => '',
            'limit' => 20,
            'order_by' => 'last_activity',
            'order' => 'DESC',
            'show_filters' => 'true',
            'show_categories' => 'true',
            'layout' => 'list'
        ), $atts, 'ats_threads_listing');
        
        ob_start();
        
        // Enqueue necessary scripts/styles
        wp_enqueue_script('ats-frontend');
        wp_enqueue_style('ats-frontend');
        
        $args = array(
            'category' => $atts['category'],
            'author_id' => $atts['author'],
            'limit' => intval($atts['limit']),
            'order_by' => $atts['order_by'],
            'order' => $atts['order']
        );
        
        $threads = $this->thread_manager->get_threads($args);
        $total_threads = $this->thread_manager->get_thread_count($args);
        
        ?>
        <div class="ats-threads-container">
            <?php if ($atts['show_filters'] === 'true'): ?>
                <div class="ats-threads-filters">
                    <div class="filter-row">
                        <div class="sort-options">
                            <select id="ats-sort-select">
                                <option value="last_activity"><?php _e('Latest Activity', 'advanced-threads'); ?></option>
                                <option value="created_at"><?php _e('Newest', 'advanced-threads'); ?></option>
                                <option value="votes"><?php _e('Most Voted', 'advanced-threads'); ?></option>
                                <option value="replies"><?php _e('Most Replies', 'advanced-threads'); ?></option>
                                <option value="views"><?php _e('Most Views', 'advanced-threads'); ?></option>
                            </select>
                        </div>
                        
                        <?php if ($atts['show_categories'] === 'true'): ?>
                            <div class="category-filter">
                                <select id="ats-category-select">
                                    <option value=""><?php _e('All Categories', 'advanced-threads'); ?></option>
                                    <?php
                                    $categories = ats_get_categories();
                                    foreach ($categories as $category): ?>
                                        <option value="<?php echo esc_attr($category->slug); ?>">
                                            <?php echo esc_html($category->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <div class="layout-toggle">
                            <button class="layout-btn <?php echo $atts['layout'] === 'list' ? 'active' : ''; ?>" 
                                    data-layout="list">
                                <i class="ats-icon-list"></i>
                            </button>
                            <button class="layout-btn <?php echo $atts['layout'] === 'grid' ? 'active' : ''; ?>" 
                                    data-layout="grid">
                                <i class="ats-icon-grid"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="ats-threads-list layout-<?php echo esc_attr($atts['layout']); ?>" 
                 id="ats-threads-list">
                <?php foreach ($threads as $thread): ?>
                    <?php $this->render_thread_item($thread, $atts['layout']); ?>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($threads)): ?>
                <div class="ats-no-threads">
                    <div class="no-threads-icon">
                        <i class="ats-icon-message-circle"></i>
                    </div>
                    <h3><?php _e('No threads found', 'advanced-threads'); ?></h3>
                    <p><?php _e('Be the first to start a discussion!', 'advanced-threads'); ?></p>
                    <?php if (is_user_logged_in()): ?>
                        <a href="<?php echo home_url('/create-thread/'); ?>" class="ats-btn ats-btn-primary">
                            <?php _e('Create Thread', 'advanced-threads'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="ats-pagination" id="ats-pagination">
                <!-- Pagination will be loaded via AJAX -->
            </div>
            
            <div class="ats-loading" id="ats-loading" style="display: none;">
                <div class="loading-spinner"></div>
                <span><?php _e('Loading...', 'advanced-threads'); ?></span>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize threads listing
            atsInitThreadsListing({
                container: '#ats-threads-list',
                filters: <?php echo $atts['show_filters'] === 'true' ? 'true' : 'false'; ?>,
                limit: <?php echo intval($atts['limit']); ?>,
                total: <?php echo $total_threads; ?>
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * User profile shortcode
     */
    public function user_profile($atts) {
        $atts = shortcode_atts(array(
            'user_id' => '',
            'username' => '',
            'show_tabs' => 'true',
            'default_tab' => 'overview'
        ), $atts, 'ats_user_profile');
        
        // Get user ID
        if (!empty($atts['username'])) {
            $user = get_user_by('login', $atts['username']);
            $user_id = $user ? $user->ID : 0;
        } elseif (!empty($atts['user_id'])) {
            $user_id = intval($atts['user_id']);
        } else {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return '<p>' . __('User not found', 'advanced-threads') . '</p>';
        }
        
        $profile = $this->user_manager->get_user_profile($user_id);
        if (!$profile) {
            return '<p>' . __('Profile not found', 'advanced-threads') . '</p>';
        }
        
        ob_start();
        
        wp_enqueue_script('ats-frontend');
        wp_enqueue_style('ats-frontend');
        
        ?>
        <div class="ats-user-profile" data-user-id="<?php echo $user_id; ?>">
            <div class="profile-header">
                <div class="profile-cover" 
                     <?php if ($profile->cover_image): ?>
                         style="background-image: url('<?php echo esc_url($profile->cover_image); ?>')"
                     <?php endif; ?>>
                </div>
                
                <div class="profile-info">
                    <div class="avatar-section">
                        <img src="<?php echo esc_url($profile->avatar ?: ats_get_user_avatar($user_id, 120)); ?>" 
                             alt="<?php echo esc_attr($profile->display_name); ?>" 
                             class="profile-avatar">
                        
                        <?php if ($profile->is_online): ?>
                            <span class="online-indicator" title="<?php _e('Online', 'advanced-threads'); ?>"></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="user-details">
                        <h1 class="user-name">
                            <?php echo esc_html($profile->display_name); ?>
                            <?php if ($profile->badge): ?>
                                <span class="user-badge badge-<?php echo esc_attr($profile->badge); ?>">
                                    <?php echo esc_html(ucfirst($profile->badge)); ?>
                                </span>
                            <?php endif; ?>
                        </h1>
                        
                        <?php if ($profile->title): ?>
                            <p class="user-title"><?php echo esc_html($profile->title); ?></p>
                        <?php endif; ?>
                        
                        <div class="user-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($profile->reputation); ?></span>
                                <span class="stat-label"><?php _e('Reputation', 'advanced-threads'); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($profile->threads_count); ?></span>
                                <span class="stat-label"><?php _e('Threads', 'advanced-threads'); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($profile->replies_count); ?></span>
                                <span class="stat-label"><?php _e('Replies', 'advanced-threads'); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($profile->followers_count); ?></span>
                                <span class="stat-label"><?php _e('Followers', 'advanced-threads'); ?></span>
                            </div>
                        </div>
                        
                        <?php if (is_user_logged_in() && get_current_user_id() !== $user_id): ?>
                            <div class="profile-actions">
                                <button class="ats-btn ats-btn-primary follow-user-btn" 
                                        data-user-id="<?php echo $user_id; ?>"
                                        data-action="<?php echo $profile->is_followed ? 'unfollow' : 'follow'; ?>">
                                    <?php echo $profile->is_followed ? __('Unfollow', 'advanced-threads') : __('Follow', 'advanced-threads'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($profile->bio || $profile->location || $profile->website): ?>
                <div class="profile-about">
                    <?php if ($profile->bio): ?>
                        <p class="user-bio"><?php echo wp_kses_post($profile->bio); ?></p>
                    <?php endif; ?>
                    
                    <div class="user-meta">
                        <?php if ($profile->location): ?>
                            <span class="meta-item">
                                <i class="ats-icon-map-pin"></i>
                                <?php echo esc_html($profile->location); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($profile->website): ?>
                            <span class="meta-item">
                                <i class="ats-icon-link"></i>
                                <a href="<?php echo esc_url($profile->website); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html(parse_url($profile->website, PHP_URL_HOST)); ?>
                                </a>
                            </span>
                        <?php endif; ?>
                        
                        <span class="meta-item">
                            <i class="ats-icon-calendar"></i>
                            <?php printf(__('Joined %s', 'advanced-threads'), 
                                date_i18n(get_option('date_format'), strtotime($profile->user_registered))); ?>
                        </span>
                        
                        <span class="meta-item">
                            <i class="ats-icon-clock"></i>
                            <?php printf(__('Last seen %s', 'advanced-threads'), 
                                $profile->last_seen_human); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_tabs'] === 'true'): ?>
                <div class="profile-tabs">
                    <nav class="tab-nav">
                        <button class="tab-btn active" data-tab="overview">
                            <?php _e('Overview', 'advanced-threads'); ?>
                        </button>
                        <button class="tab-btn" data-tab="threads">
                            <?php _e('Threads', 'advanced-threads'); ?>
                        </button>
                        <button class="tab-btn" data-tab="replies">
                            <?php _e('Replies', 'advanced-threads'); ?>
                        </button>
                        <button class="tab-btn" data-tab="activity">
                            <?php _e('Activity', 'advanced-threads'); ?>
                        </button>
                        <?php if (get_current_user_id() === $user_id): ?>
                            <button class="tab-btn" data-tab="settings">
                                <?php _e('Settings', 'advanced-threads'); ?>
                            </button>
                        <?php endif; ?>
                    </nav>
                    
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab-overview">
                            <?php $this->render_profile_overview($profile); ?>
                        </div>
                        <div class="tab-pane" id="tab-threads">
                            <div class="loading-placeholder">
                                <?php _e('Loading threads...', 'advanced-threads'); ?>
                            </div>
                        </div>
                        <div class="tab-pane" id="tab-replies">
                            <div class="loading-placeholder">
                                <?php _e('Loading replies...', 'advanced-threads'); ?>
                            </div>
                        </div>
                        <div class="tab-pane" id="tab-activity">
                            <div class="loading-placeholder">
                                <?php _e('Loading activity...', 'advanced-threads'); ?>
                            </div>
                        </div>
                        <?php if (get_current_user_id() === $user_id): ?>
                            <div class="tab-pane" id="tab-settings">
                                <?php $this->render_profile_settings($profile); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            atsInitUserProfile(<?php echo $user_id; ?>);
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Create thread form shortcode
     */
    public function create_thread_form($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('You must be logged in to create a thread.', 'advanced-threads') . 
                   ' <a href="' . wp_login_url(get_permalink()) . '">' . __('Login here', 'advanced-threads') . '</a></p>';
        }
        
        $atts = shortcode_atts(array(
            'category' => '',
            'redirect' => 'thread',
            'show_preview' => 'true'
        ), $atts, 'ats_create_thread_form');
        
        ob_start();
        
        wp_enqueue_script('ats-frontend');
        wp_enqueue_script('ats-editor');
        wp_enqueue_style('ats-frontend');
        
        ?>
        <div class="ats-create-thread-form">
            <form id="ats-thread-form" method="post">
                <?php wp_nonce_field('create_thread', 'thread_nonce'); ?>
                
                <div class="form-group">
                    <label for="thread-title"><?php _e('Thread Title', 'advanced-threads'); ?> *</label>
                    <input type="text" id="thread-title" name="thread_title" 
                           maxlength="<?php echo ats_get_option('max_thread_title_length', 200); ?>" 
                           required>
                    <div class="field-help">
                        <?php _e('Choose a descriptive title for your thread', 'advanced-threads'); ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="thread-category"><?php _e('Category', 'advanced-threads'); ?> *</label>
                    <select id="thread-category" name="thread_category" required>
                        <option value=""><?php _e('Select a category', 'advanced-threads'); ?></option>
                        <?php
                        $categories = ats_get_categories();
                        foreach ($categories as $category): ?>
                            <option value="<?php echo esc_attr($category->slug); ?>"
                                    <?php selected($atts['category'], $category->slug); ?>>
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="thread-content"><?php _e('Thread Content', 'advanced-threads'); ?> *</label>
                    <?php if (ats_get_option('enable_rich_editor', 1)): ?>
                        <div id="thread-editor-container">
                            <div id="thread-editor"></div>
                        </div>
                        <textarea id="thread-content" name="thread_content" style="display:none;" required></textarea>
                    <?php else: ?>
                        <textarea id="thread-content" name="thread_content" rows="10" required></textarea>
                    <?php endif; ?>
                    <div class="character-count">
                        <span id="char-count">0</span> / <?php echo number_format(ats_get_option('max_content_length', 10000)); ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="thread-tags"><?php _e('Tags', 'advanced-threads'); ?></label>
                    <input type="text" id="thread-tags" name="thread_tags" 
                           placeholder="<?php esc_attr_e('Add tags separated by commas', 'advanced-threads'); ?>">
                    <div class="field-help">
                        <?php _e('Add relevant tags to help others find your thread', 'advanced-threads'); ?>
                    </div>
                </div>
                
                <?php if (ats_get_option('enable_image_uploads', 1)): ?>
                    <div class="form-group">
                        <label for="thread-image"><?php _e('Featured Image', 'advanced-threads'); ?></label>
                        <input type="file" id="thread-image" name="thread_image" 
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="image-preview" id="image-preview"></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['show_preview'] === 'true'): ?>
                    <div class="form-actions">
                        <button type="button" class="ats-btn ats-btn-secondary" id="preview-btn">
                            <?php _e('Preview', 'advanced-threads'); ?>
                        </button>
                        <button type="submit" class="ats-btn ats-btn-primary" id="submit-btn">
                            <?php _e('Create Thread', 'advanced-threads'); ?>
                        </button>
                    </div>
                    
                    <div class="thread-preview" id="thread-preview" style="display:none;">
                        <h3><?php _e('Preview', 'advanced-threads'); ?></h3>
                        <div class="preview-content"></div>
                        <button type="button" class="ats-btn ats-btn-secondary" id="edit-btn">
                            <?php _e('Continue Editing', 'advanced-threads'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="form-actions">
                        <button type="submit" class="ats-btn ats-btn-primary">
                            <?php _e('Create Thread', 'advanced-threads'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            atsInitCreateThreadForm({
                redirect: '<?php echo esc_js($atts['redirect']); ?>',
                showPreview: <?php echo $atts['show_preview'] === 'true' ? 'true' : 'false'; ?>,
                maxLength: <?php echo ats_get_option('max_content_length', 10000); ?>
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Leaderboard shortcode
     */
    public function leaderboard($atts) {
        $atts = shortcode_atts(array(
            'limit' => 50,
            'criteria' => 'reputation',
            'timeframe' => 'all',
            'show_avatars' => 'true'
        ), $atts, 'ats_leaderboard');
        
        $users = $this->user_manager->get_leaderboard(
            $atts['criteria'], 
            intval($atts['limit']), 
            $atts['timeframe']
        );
        
        ob_start();
        
        wp_enqueue_style('ats-frontend');
        
        ?>
        <div class="ats-leaderboard">
            <div class="leaderboard-header">
                <h2><?php _e('Leaderboard', 'advanced-threads'); ?></h2>
                <div class="leaderboard-filters">
                    <select id="leaderboard-criteria">
                        <option value="reputation" <?php selected($atts['criteria'], 'reputation'); ?>>
                            <?php _e('Reputation', 'advanced-threads'); ?>
                        </option>
                        <option value="threads" <?php selected($atts['criteria'], 'threads'); ?>>
                            <?php _e('Threads Created', 'advanced-threads'); ?>
                        </option>
                        <option value="replies" <?php selected($atts['criteria'], 'replies'); ?>>
                            <?php _e('Replies Posted', 'advanced-threads'); ?>
                        </option>
                        <option value="likes" <?php selected($atts['criteria'], 'likes'); ?>>
                            <?php _e('Likes Received', 'advanced-threads'); ?>
                        </option>
                    </select>
                    
                    <select id="leaderboard-timeframe">
                        <option value="all" <?php selected($atts['timeframe'], 'all'); ?>>
                            <?php _e('All Time', 'advanced-threads'); ?>
                        </option>
                        <option value="month" <?php selected($atts['timeframe'], 'month'); ?>>
                            <?php _e('This Month', 'advanced-threads'); ?>
                        </option>
                        <option value="week" <?php selected($atts['timeframe'], 'week'); ?>>
                            <?php _e('This Week', 'advanced-threads'); ?>
                        </option>
                    </select>
                </div>
            </div>
            
            <div class="leaderboard-list" id="leaderboard-list">
                <?php foreach ($users as $user): ?>
                    <div class="leaderboard-item rank-<?php echo $user->rank; ?>">
                        <div class="rank-number">
                            <?php if ($user->rank <= 3): ?>
                                <i class="ats-icon-medal rank-<?php echo $user->rank; ?>"></i>
                            <?php else: ?>
                                #<?php echo $user->rank; ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($atts['show_avatars'] === 'true'): ?>
                            <div class="user-avatar">
                                <img src="<?php echo esc_url($user->avatar ?: ats_get_user_avatar($user->user_id, 48)); ?>" 
                                     alt="<?php echo esc_attr($user->display_name); ?>">
                            </div>
                        <?php endif; ?>
                        
                        <div class="user-info">
                            <div class="user-name">
                                <a href="<?php echo ats_get_user_profile_url($user->user_id); ?>">
                                    <?php echo esc_html($user->display_name); ?>
                                </a>
                                <?php if ($user->badge): ?>
                                    <span class="user-badge badge-<?php echo esc_attr($user->badge); ?>">
                                        <?php echo esc_html(ucfirst($user->badge)); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="user-stats">
                                <?php if ($atts['criteria'] === 'reputation'): ?>
                                    <span class="primary-stat"><?php echo number_format($user->reputation); ?> <?php _e('points', 'advanced-threads'); ?></span>
                                <?php elseif ($atts['criteria'] === 'threads'): ?>
                                    <span class="primary-stat"><?php echo number_format($user->threads_count); ?> <?php _e('threads', 'advanced-threads'); ?></span>
                                <?php elseif ($atts['criteria'] === 'replies'): ?>
                                    <span class="primary-stat"><?php echo number_format($user->replies_count); ?> <?php _e('replies', 'advanced-threads'); ?></span>
                                <?php elseif ($atts['criteria'] === 'likes'): ?>
                                    <span class="primary-stat"><?php echo number_format($user->likes_received); ?> <?php _e('likes', 'advanced-threads'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="no-users-message">
                    <p><?php _e('No users found for this criteria.', 'advanced-threads'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#leaderboard-criteria, #leaderboard-timeframe').on('change', function() {
                atsUpdateLeaderboard();
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Thread categories shortcode
     */
    public function thread_categories($atts) {
        $atts = shortcode_atts(array(
            'layout' => 'grid',
            'show_descriptions' => 'true',
            'show_counts' => 'true'
        ), $atts, 'ats_thread_categories');
        
        $categories = ats_get_categories();
        
        ob_start();
        
        wp_enqueue_style('ats-frontend');
        
        ?>
        <div class="ats-categories layout-<?php echo esc_attr($atts['layout']); ?>">
            <?php foreach ($categories as $category): ?>
                <div class="category-item" style="border-left-color: <?php echo esc_attr($category->color); ?>">
                    <div class="category-header">
                        <?php if ($category->icon): ?>
                            <i class="category-icon ats-icon-<?php echo esc_attr($category->icon); ?>"></i>
                        <?php endif; ?>
                        <h3 class="category-name">
                            <a href="<?php echo ats_get_category_url($category->slug); ?>">
                                <?php echo esc_html($category->name); ?>
                            </a>
                        </h3>
                    </div>
                    
                    <?php if ($atts['show_descriptions'] === 'true' && $category->description): ?>
                        <p class="category-description">
                            <?php echo esc_html($category->description); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_counts'] === 'true'): ?>
                        <div class="category-stats">
                            <span class="thread-count">
                                <?php echo number_format($category->thread_count); ?> 
                                <?php _e('threads', 'advanced-threads'); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Recent replies shortcode
     */
    public function recent_replies($atts) {
        $atts = shortcode_atts(array(
            'limit' => 5,
            'show_thread_title' => 'true',
            'show_avatars' => 'true'
        ), $atts, 'ats_recent_replies');
        
        global $wpdb;
        $replies_table = $wpdb->prefix . 'ats_replies';
        $threads_table = $wpdb->prefix . 'ats_threads';
        $profiles_table = $wpdb->prefix . 'ats_user_profiles';
        
        $sql = "SELECT r.id, r.content, r.created_at, r.author_id,
                       t.title as thread_title, t.post_id as thread_post_id,
                       u.display_name as author_name,
                       up.avatar as author_avatar
                FROM {$replies_table} r
                LEFT JOIN {$threads_table} t ON r.thread_id = t.id
                LEFT JOIN {$wpdb->users} u ON r.author_id = u.ID
                LEFT JOIN {$profiles_table} up ON r.author_id = up.user_id
                WHERE r.status = 'published' AND t.status = 'published'
                ORDER BY r.created_at DESC
                LIMIT %d";
        
        $replies = $wpdb->get_results($wpdb->prepare($sql, intval($atts['limit'])));
        
        ob_start();
        
        wp_enqueue_style('ats-frontend');
        
        ?>
        <div class="ats-recent-replies">
            <h3><?php _e('Recent Replies', 'advanced-threads'); ?></h3>
            
            <?php if ($replies): ?>
                <div class="replies-list">
                    <?php foreach ($replies as $reply): ?>
                        <div class="reply-item">
                            <?php if ($atts['show_avatars'] === 'true'): ?>
                                <div class="reply-avatar">
                                    <img src="<?php echo esc_url($reply->author_avatar ?: ats_get_user_avatar($reply->author_id, 32)); ?>" 
                                         alt="<?php echo esc_attr($reply->author_name); ?>">
                                </div>
                            <?php endif; ?>
                            
                            <div class="reply-content">
                                <div class="reply-excerpt">
                                    <?php echo ats_get_excerpt($reply->content, 80); ?>
                                </div>
                                
                                <div class="reply-meta">
                                    <span class="reply-author">
                                        <a href="<?php echo ats_get_user_profile_url($reply->author_id); ?>">
                                            <?php echo esc_html($reply->author_name); ?>
                                        </a>
                                    </span>
                                    
                                    <?php if ($atts['show_thread_title'] === 'true'): ?>
                                        <span class="reply-thread">
                                            <?php _e('in', 'advanced-threads'); ?>
                                            <a href="<?php echo get_permalink($reply->thread_post_id); ?>">
                                                <?php echo esc_html($reply->thread_title); ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="reply-time">
                                        <?php echo ats_time_ago($reply->created_at); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-replies"><?php _e('No recent replies found.', 'advanced-threads'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * User stats shortcode
     */
    public function user_stats($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'layout' => 'horizontal',
            'show_charts' => 'false'
        ), $atts, 'ats_user_stats');
        
        $user_id = intval($atts['user_id']);
        if (!$user_id) {
            return '<p>' . __('User not found', 'advanced-threads') . '</p>';
        }
        
        $profile = $this->user_manager->get_user_profile($user_id);
        if (!$profile) {
            return '<p>' . __('Profile not found', 'advanced-threads') . '</p>';
        }
        
        ob_start();
        
        wp_enqueue_style('ats-frontend');
        
        ?>
        <div class="ats-user-stats layout-<?php echo esc_attr($atts['layout']); ?>">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ats-icon-award"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo number_format($profile->reputation); ?></div>
                        <div class="stat-label"><?php _e('Reputation', 'advanced-threads'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ats-icon-message-square"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo number_format($profile->threads_count); ?></div>
                        <div class="stat-label"><?php _e('Threads', 'advanced-threads'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ats-icon-message-circle"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo number_format($profile->replies_count); ?></div>
                        <div class="stat-label"><?php _e('Replies', 'advanced-threads'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ats-icon-thumbs-up"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo number_format($profile->likes_received); ?></div>
                        <div class="stat-label"><?php _e('Likes', 'advanced-threads'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ats-icon-users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo number_format($profile->followers_count); ?></div>
                        <div class="stat-label"><?php _e('Followers', 'advanced-threads'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ats-icon-activity"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $profile->activity_score; ?></div>
                        <div class="stat-label"><?php _e('Activity Score', 'advanced-threads'); ?></div>
                    </div>
                </div>
            </div>
            
            <?php if ($atts['show_charts'] === 'true'): ?>
                <div class="stats-charts">
                    <canvas id="user-activity-chart" width="400" height="200"></canvas>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    atsInitUserStatsChart(<?php echo $user_id; ?>);
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Popular threads shortcode
     */
    public function popular_threads($atts) {
        $atts = shortcode_atts(array(
            'limit' => 5,
            'timeframe' => 'week',
            'show_stats' => 'true'
        ), $atts, 'ats_popular_threads');
        
        $threads = $this->thread_manager->get_popular_threads(
            intval($atts['limit']), 
            $atts['timeframe']
        );
        
        ob_start();
        
        wp_enqueue_style('ats-frontend');
        
        ?>
        <div class="ats-popular-threads">
            <h3><?php _e('Popular Threads', 'advanced-threads'); ?></h3>
            
            <?php if ($threads): ?>
                <div class="popular-threads-list">
                    <?php foreach ($threads as $index => $thread): ?>
                        <div class="popular-thread-item">
                            <div class="thread-rank">
                                <?php echo $index + 1; ?>
                            </div>
                            
                            <div class="thread-info">
                                <h4 class="thread-title">
                                    <a href="<?php echo get_permalink($thread->post_id); ?>">
                                        <?php echo esc_html($thread->title); ?>
                                    </a>
                                </h4>
                                
                                <div class="thread-meta">
                                    <span class="thread-author">
                                        <a href="<?php echo ats_get_user_profile_url($thread->author_id); ?>">
                                            <?php echo esc_html($thread->author_name); ?>
                                        </a>
                                    </span>
                                    
                                    <?php if ($atts['show_stats'] === 'true'): ?>
                                        <span class="thread-stats">
                                            <span class="replies"><?php echo number_format($thread->reply_count); ?> replies</span>
                                            <span class="votes"><?php echo number_format($thread->upvotes - $thread->downvotes); ?> votes</span>
                                            <span class="views"><?php echo ats_format_number($thread->view_count); ?> views</span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-threads"><?php _e('No popular threads found.', 'advanced-threads'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Thread search shortcode
     */
    public function thread_search($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => '',
            'show_categories' => 'true',
            'show_results' => 'true',
            'results_limit' => 10
        ), $atts, 'ats_thread_search');
        
        if (empty($atts['placeholder'])) {
            $atts['placeholder'] = __('Search threads...', 'advanced-threads');
        }
        
        ob_start();
        
        wp_enqueue_script('ats-frontend');
        wp_enqueue_style('ats-frontend');
        
        ?>
        <div class="ats-thread-search">
            <form class="search-form" id="thread-search-form">
                <div class="search-input-group">
                    <input type="text" 
                           id="thread-search-input" 
                           name="search_query"
                           placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                           autocomplete="off">
                    
                    <?php if ($atts['show_categories'] === 'true'): ?>
                        <select id="search-category-filter" name="search_category">
                            <option value=""><?php _e('All Categories', 'advanced-threads'); ?></option>
                            <?php
                            $categories = ats_get_categories();
                            foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->slug); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    
                    <button type="submit" class="search-btn">
                        <i class="ats-icon-search"></i>
                    </button>
                </div>
            </form>
            
            <?php if ($atts['show_results'] === 'true'): ?>
                <div class="search-results" id="search-results" style="display: none;">
                    <div class="results-header">
                        <h4 id="results-title"></h4>
                        <button class="close-results" id="close-search-results">
                            <i class="ats-icon-x"></i>
                        </button>
                    </div>
                    <div class="results-list" id="results-list"></div>
                    <div class="search-loading" id="search-loading" style="display: none;">
                        <div class="loading-spinner"></div>
                        <span><?php _e('Searching...', 'advanced-threads'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            atsInitThreadSearch({
                showResults: <?php echo $atts['show_results'] === 'true' ? 'true' : 'false'; ?>,
                resultsLimit: <?php echo intval($atts['results_limit']); ?>
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * User card shortcode
     */
    public function user_card($atts) {
        $atts = shortcode_atts(array(
            'user_id' => '',
            'username' => '',
            'layout' => 'horizontal',
            'show_stats' => 'true',
            'show_follow' => 'true'
        ), $atts, 'ats_user_card');
        
        // Get user ID
        if (!empty($atts['username'])) {
            $user = get_user_by('login', $atts['username']);
            $user_id = $user ? $user->ID : 0;
        } elseif (!empty($atts['user_id'])) {
            $user_id = intval($atts['user_id']);
        } else {
            return '<p>' . __('User not specified', 'advanced-threads') . '</p>';
        }
        
        if (!$user_id) {
            return '<p>' . __('User not found', 'advanced-threads') . '</p>';
        }
        
        $profile = $this->user_manager->get_user_profile($user_id);
        if (!$profile) {
            return '<p>' . __('Profile not found', 'advanced-threads') . '</p>';
        }
        
        ob_start();
        
        wp_enqueue_style('ats-frontend');
        
        ?>
        <div class="ats-user-card layout-<?php echo esc_attr($atts['layout']); ?>">
            <div class="user-avatar">
                <img src="<?php echo esc_url($profile->avatar ?: ats_get_user_avatar($user_id, 64)); ?>" 
                     alt="<?php echo esc_attr($profile->display_name); ?>">
                <?php if ($profile->is_online): ?>
                    <span class="online-indicator"></span>
                <?php endif; ?>
            </div>
            
            <div class="user-details">
                <div class="user-header">
                    <h4 class="user-name">
                        <a href="<?php echo ats_get_user_profile_url($user_id); ?>">
                            <?php echo esc_html($profile->display_name); ?>
                        </a>
                    </h4>
                    <?php if ($profile->badge): ?>
                        <span class="user-badge badge-<?php echo esc_attr($profile->badge); ?>">
                            <?php echo esc_html(ucfirst($profile->badge)); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($profile->title): ?>
                    <p class="user-title"><?php echo esc_html($profile->title); ?></p>
                <?php endif; ?>
                
                <?php if ($atts['show_stats'] === 'true'): ?>
                    <div class="user-mini-stats">
                        <span class="stat-item">
                            <span class="stat-value"><?php echo ats_format_number($profile->reputation); ?></span>
                            <span class="stat-label"><?php _e('rep', 'advanced-threads'); ?></span>
                        </span>
                        <span class="stat-item">
                            <span class="stat-value"><?php echo $profile->threads_count; ?></span>
                            <span class="stat-label"><?php _e('threads', 'advanced-threads'); ?></span>
                        </span>
                        <span class="stat-item">
                            <span class="stat-value"><?php echo $profile->replies_count; ?></span>
                            <span class="stat-label"><?php _e('replies', 'advanced-threads'); ?></span>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['show_follow'] === 'true' && is_user_logged_in() && get_current_user_id() !== $user_id): ?>
                    <div class="user-actions">
                        <button class="ats-btn ats-btn-sm follow-user-btn" 
                                data-user-id="<?php echo $user_id; ?>"
                                data-action="<?php echo $profile->is_followed ? 'unfollow' : 'follow'; ?>">
                            <?php echo $profile->is_followed ? __('Unfollow', 'advanced-threads') : __('Follow', 'advanced-threads'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    // Helper methods
    
    /**
     * Render thread item
     */
    private function render_thread_item($thread, $layout = 'list') {
        ?>
        <div class="thread-item layout-<?php echo esc_attr($layout); ?>" data-thread-id="<?php echo $thread->id; ?>">
            <div class="thread-meta">
                <div class="thread-author">
                    <img src="<?php echo esc_url($thread->author_avatar ?: ats_get_user_avatar($thread->author_id, 32)); ?>" 
                         alt="<?php echo esc_attr($thread->author_name); ?>" class="author-avatar">
                    <div class="author-info">
                        <a href="<?php echo ats_get_user_profile_url($thread->author_id); ?>" class="author-name">
                            <?php echo esc_html($thread->author_name); ?>
                        </a>
                        <div class="thread-date"><?php echo ats_time_ago($thread->created_at); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="thread-content">
                <h3 class="thread-title">
                    <a href="<?php echo get_permalink($thread->post_id); ?>">
                        <?php echo esc_html($thread->title); ?>
                        <?php if ($thread->is_pinned): ?>
                            <i class="ats-icon-pin pinned-indicator" title="<?php _e('Pinned', 'advanced-threads'); ?>"></i>
                        <?php endif; ?>
                        <?php if ($thread->is_locked): ?>
                            <i class="ats-icon-lock locked-indicator" title="<?php _e('Locked', 'advanced-threads'); ?>"></i>
                        <?php endif; ?>
                    </a>
                </h3>
                
                <?php if ($thread->excerpt): ?>
                    <p class="thread-excerpt"><?php echo esc_html($thread->excerpt); ?></p>
                <?php endif; ?>
                
                <?php if ($thread->category): ?>
                    <div class="thread-category">
                        <a href="<?php echo ats_get_category_url($thread->category); ?>" class="category-link">
                            <?php echo esc_html($thread->category); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="thread-stats">
                <div class="stat-item votes">
                    <i class="ats-icon-thumbs-up"></i>
                    <span><?php echo $thread->upvotes - $thread->downvotes; ?></span>
                </div>
                <div class="stat-item replies">
                    <i class="ats-icon-message-circle"></i>
                    <span><?php echo $thread->reply_count; ?></span>
                </div>
                <div class="stat-item views">
                    <i class="ats-icon-eye"></i>
                    <span><?php echo ats_format_number($thread->view_count); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render profile overview
     */
    private function render_profile_overview($profile) {
        $recent_activity = $this->user_manager->get_user_activity($profile->user_id, 5);
        
        ?>
        <div class="profile-overview">
            <div class="overview-stats">
                <div class="stat-grid">
                    <div class="stat-large">
                        <div class="stat-value"><?php echo number_format($profile->reputation); ?></div>
                        <div class="stat-label"><?php _e('Total Reputation', 'advanced-threads'); ?></div>
                        <div class="stat-sublabel">
                            <?php printf(__('Level: %s', 'advanced-threads'), $profile->reputation_level); ?>
                        </div>
                    </div>
                    
                    <div class="stat-medium">
                        <div class="stat-value"><?php echo $profile->recent_activity_count; ?></div>
                        <div class="stat-label"><?php _e('This Week', 'advanced-threads'); ?></div>
                    </div>
                    
                    <div class="stat-medium">
                        <div class="stat-value"><?php echo $profile->activity_score; ?></div>
                        <div class="stat-label"><?php _e('Activity Score', 'advanced-threads'); ?></div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($recent_activity)): ?>
                <div class="recent-activity">
                    <h4><?php _e('Recent Activity', 'advanced-threads'); ?></h4>
                    <div class="activity-list">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item type-<?php echo esc_attr($activity['type']); ?>">
                                <div class="activity-icon">
                                    <i class="ats-icon-<?php echo $activity['type'] === 'thread' ? 'message-square' : 'message-circle'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text">
                                        <?php if ($activity['type'] === 'thread'): ?>
                                            <?php printf(__('Created thread "%s"', 'advanced-threads'), 
                                                esc_html($activity['title'])); ?>
                                        <?php else: ?>
                                            <?php printf(__('Replied in "%s"', 'advanced-threads'), 
                                                esc_html($activity['metadata']['thread_title'])); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo ats_time_ago($activity['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render profile settings
     */
    private function render_profile_settings($profile) {
        ?>
        <div class="profile-settings">
            <form id="profile-settings-form">
                <?php wp_nonce_field('update_profile', 'profile_nonce'); ?>
                
                <div class="settings-section">
                    <h4><?php _e('Basic Information', 'advanced-threads'); ?></h4>
                    
                    <div class="form-group">
                        <label for="display_name"><?php _e('Display Name', 'advanced-threads'); ?></label>
                        <input type="text" id="display_name" name="display_name" 
                               value="<?php echo esc_attr($profile->display_name); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bio"><?php _e('Bio', 'advanced-threads'); ?></label>
                        <textarea id="bio" name="bio" rows="4"><?php echo esc_textarea($profile->bio); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="location"><?php _e('Location', 'advanced-threads'); ?></label>
                        <input type="text" id="location" name="location" 
                               value="<?php echo esc_attr($profile->location); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="website"><?php _e('Website', 'advanced-threads'); ?></label>
                        <input type="url" id="website" name="website" 
                               value="<?php echo esc_url($profile->website); ?>">
                    </div>
                </div>
                
                <div class="settings-section">
                    <h4><?php _e('Notification Preferences', 'advanced-threads'); ?></h4>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="notify_replies" value="1" 
                                   <?php checked(true); ?>>
                            <?php _e('Email me when someone replies to my threads', 'advanced-threads'); ?>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="notify_follows" value="1" 
                                   <?php checked(true); ?>>
                            <?php _e('Email me when someone follows me', 'advanced-threads'); ?>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="notify_upvotes" value="1" 
                                   <?php checked(true); ?>>
                            <?php _e('Email me when my content gets upvoted', 'advanced-threads'); ?>
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="ats-btn ats-btn-primary">
                        <?php _e('Save Settings', 'advanced-threads'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
}

// Initialize shortcodes
ATS_Shortcodes::get_instance();