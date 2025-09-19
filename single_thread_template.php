<?php
/**
 * Single Thread Template
 *
 * @package AdvancedThreadsSystem
 * @subpackage Templates
 */

get_header(); ?>

<div class="ats-single-thread-wrapper">
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-lg-9">
                <?php while (have_posts()) : the_post(); ?>
                    
                    <div class="ats-thread-navigation">
                        <nav aria-label="<?php esc_attr_e('Thread navigation', 'advanced-threads'); ?>">
                            <?php
                            // Get thread categories for breadcrumb
                            global $post;
                            $thread_manager = new ATS_Thread_Manager();
                            $thread = $thread_manager->get_thread_by_post_id($post->ID);
                            
                            if ($thread && $thread->category) {
                                $category = ats_get_category_by_slug($thread->category);
                                if ($category) {
                                    echo '<div class="ats-breadcrumb">';
                                    echo '<a href="' . esc_url(home_url('/threads/')) . '">' . __('Threads', 'advanced-threads') . '</a>';
                                    echo ' <span class="separator">›</span> ';
                                    echo '<a href="' . esc_url(ats_get_category_url($thread->category)) . '">' . esc_html($category->name) . '</a>';
                                    echo ' <span class="separator">›</span> ';
                                    echo '<span class="current">' . esc_html(get_the_title()) . '</span>';
                                    echo '</div>';
                                }
                            }
                            ?>
                            
                            <div class="ats-thread-actions-top">
                                <?php if (is_user_logged_in()): ?>
                                    <a href="<?php echo esc_url(home_url('/create-thread/')); ?>" 
                                       class="ats-btn ats-btn-primary ats-btn-sm">
                                        <i class="ats-icon-plus"></i>
                                        <?php _e('New Thread', 'advanced-threads'); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <button class="ats-btn ats-btn-outline ats-btn-sm" onclick="window.print()">
                                    <i class="ats-icon-printer"></i>
                                    <?php _e('Print', 'advanced-threads'); ?>
                                </button>
                                
                                <button class="ats-btn ats-btn-outline ats-btn-sm share-thread-btn" 
                                        data-url="<?php echo esc_url(get_permalink()); ?>"
                                        data-title="<?php echo esc_attr(get_the_title()); ?>">
                                    <i class="ats-icon-share-2"></i>
                                    <?php _e('Share', 'advanced-threads'); ?>
                                </button>
                            </div>
                        </nav>
                    </div>
                    
                    <article id="post-<?php the_ID(); ?>" <?php post_class('ats-single-thread'); ?>>
                        
                        <header class="ats-thread-header">
                            <h1 class="ats-thread-title"><?php the_title(); ?></h1>
                            
                            <?php if ($thread): ?>
                                <div class="ats-thread-meta">
                                    <div class="ats-thread-author">
                                        <div class="author-avatar">
                                            <?php echo get_avatar($thread->author_id, 48); ?>
                                        </div>
                                        <div class="author-info">
                                            <div class="author-name">
                                                <a href="<?php echo ats_get_user_profile_url($thread->author_id); ?>">
                                                    <?php echo esc_html(get_the_author_meta('display_name', $thread->author_id)); ?>
                                                </a>
                                                <?php
                                                // Show user badge if available
                                                $user_manager = new ATS_User_Manager();
                                                $user_profile = $user_manager->get_user_profile($thread->author_id);
                                                if ($user_profile && $user_profile->reputation_points > 1000): ?>
                                                    <span class="user-badge expert"><?php _e('Expert', 'advanced-threads'); ?></span>
                                                <?php elseif ($user_profile && $user_profile->reputation_points > 500): ?>
                                                    <span class="user-badge contributor"><?php _e('Contributor', 'advanced-threads'); ?></span>
                                                <?php elseif ($user_profile && $user_profile->reputation_points > 100): ?>
                                                    <span class="user-badge member"><?php _e('Active Member', 'advanced-threads'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="thread-date">
                                                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                                    <?php printf(__('Posted %s ago', 'advanced-threads'), ats_time_ago(get_the_date('Y-m-d H:i:s'))); ?>
                                                </time>
                                                <?php if (get_the_modified_date() !== get_the_date()): ?>
                                                    <span class="modified-date">
                                                        (<?php printf(__('Updated %s ago', 'advanced-threads'), ats_time_ago(get_the_modified_date('Y-m-d H:i:s'))); ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="ats-thread-stats">
                                        <div class="stat-item views">
                                            <i class="ats-icon-eye"></i>
                                            <span class="stat-value"><?php echo ats_format_number($thread->view_count); ?></span>
                                            <span class="stat-label"><?php _e('views', 'advanced-threads'); ?></span>
                                        </div>
                                        <div class="stat-item replies">
                                            <i class="ats-icon-message-circle"></i>
                                            <span class="stat-value"><?php echo number_format($thread->reply_count); ?></span>
                                            <span class="stat-label"><?php _e('replies', 'advanced-threads'); ?></span>
                                        </div>
                                        <?php if (ats_get_option('enable_voting', 1)): ?>
                                            <div class="stat-item votes">
                                                <i class="ats-icon-thumbs-up"></i>
                                                <span class="stat-value"><?php echo $thread->upvotes - $thread->downvotes; ?></span>
                                                <span class="stat-label"><?php _e('score', 'advanced-threads'); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($thread->category): ?>
                                    <div class="ats-thread-category">
                                        <?php
                                        $category = ats_get_category_by_slug($thread->category);
                                        if ($category): ?>
                                            <a href="<?php echo ats_get_category_url($thread->category); ?>" 
                                               class="category-badge"
                                               style="background-color: <?php echo esc_attr($category->color); ?>">
                                                <?php if ($category->icon): ?>
                                                    <i class="ats-icon-<?php echo esc_attr($category->icon); ?>"></i>
                                                <?php endif; ?>
                                                <?php echo esc_html($category->name); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($thread->tags): ?>
                                    <div class="ats-thread-tags">
                                        <?php
                                        $tags = explode(',', $thread->tags);
                                        foreach ($tags as $tag):
                                            $tag = trim($tag);
                                            if (!empty($tag)): ?>
                                                <a href="<?php echo home_url('/threads/tag/' . urlencode($tag) . '/'); ?>" 
                                                   class="thread-tag">
                                                    #<?php echo esc_html($tag); ?>
                                                </a>
                                            <?php endif;
                                        endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($thread->is_pinned || $thread->is_locked): ?>
                                    <div class="ats-thread-status">
                                        <?php if ($thread->is_pinned): ?>
                                            <span class="status-badge pinned">
                                                <i class="ats-icon-pin"></i>
                                                <?php _e('Pinned', 'advanced-threads'); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($thread->is_locked): ?>
                                            <span class="status-badge locked">
                                                <i class="ats-icon-lock"></i>
                                                <?php _e('Locked', 'advanced-threads'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </header>
                        
                        <div class="ats-thread-content">
                            <?php
                            the_content();
                            
                            wp_link_pages(array(
                                'before' => '<div class="page-links">' . esc_html__('Pages:', 'advanced-threads'),
                                'after'  => '</div>',
                            ));
                            ?>
                        </div>
                        
                        <?php if ($thread): ?>
                            <footer class="ats-thread-footer">
                                <?php if (ats_get_option('enable_voting', 1)): ?>
                                    <div class="ats-thread-voting">
                                        <div class="voting-buttons" data-thread-id="<?php echo $thread->id; ?>">
                                            <?php
                                            $user_vote = '';
                                            if (is_user_logged_in()) {
                                                $vote_manager = new ATS_Vote_Manager();
                                                $user_vote = $vote_manager->get_user_vote(get_current_user_id(), $thread->id, null);
                                            }
                                            ?>
                                            
                                            <button class="vote-btn upvote <?php echo $user_vote === 'up' ? 'active' : ''; ?>"
                                                    data-vote="up" 
                                                    <?php echo !is_user_logged_in() ? 'disabled title="' . esc_attr__('Please log in to vote', 'advanced-threads') . '"' : ''; ?>>
                                                <i class="ats-icon-chevron-up"></i>
                                                <span class="vote-count"><?php echo number_format($thread->upvotes); ?></span>
                                            </button>
                                            
                                            <div class="vote-score">
                                                <?php echo $thread->upvotes - $thread->downvotes; ?>
                                            </div>
                                            
                                            <button class="vote-btn downvote <?php echo $user_vote === 'down' ? 'active' : ''; ?>"
                                                    data-vote="down"
                                                    <?php echo !is_user_logged_in() ? 'disabled title="' . esc_attr__('Please log in to vote', 'advanced-threads') . '"' : ''; ?>>
                                                <i class="ats-icon-chevron-down"></i>
                                                <span class="vote-count"><?php echo number_format($thread->downvotes); ?></span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="ats-thread-actions">
                                    <?php if (is_user_logged_in()): ?>
                                        <div class="social-actions">
                                            <?php if (ats_get_option('enable_following', 1)): ?>
                                                <button class="action-btn follow-thread" 
                                                        data-thread-id="<?php echo $thread->id; ?>"
                                                        data-action="follow">
                                                    <i class="ats-icon-bell"></i>
                                                    <span><?php _e('Follow', 'advanced-threads'); ?></span>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="action-btn bookmark-thread" 
                                                    data-thread-id="<?php echo $thread->id; ?>">
                                                <i class="ats-icon-bookmark"></i>
                                                <span><?php _e('Bookmark', 'advanced-threads'); ?></span>
                                            </button>
                                            
                                            <button class="action-btn report-thread" 
                                                    data-thread-id="<?php echo $thread->id; ?>"
                                                    data-type="thread">
                                                <i class="ats-icon-flag"></i>
                                                <span><?php _e('Report', 'advanced-threads'); ?></span>
                                            </button>
                                        </div>
                                        
                                        <?php if (get_current_user_id() === $thread->author_id || current_user_can('edit_posts')): ?>
                                            <div class="author-actions">
                                                <button class="action-btn edit-thread" 
                                                        data-thread-id="<?php echo $thread->id; ?>">
                                                    <i class="ats-icon-edit-2"></i>
                                                    <span><?php _e('Edit', 'advanced-threads'); ?></span>
                                                </button>
                                                
                                                <?php if (current_user_can('delete_posts')): ?>
                                                    <button class="action-btn delete-thread" 
                                                            data-thread-id="<?php echo $thread->id; ?>">
                                                        <i class="ats-icon-trash-2"></i>
                                                        <span><?php _e('Delete', 'advanced-threads'); ?></span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (current_user_can('moderate_comments')): ?>
                                            <div class="moderation-actions">
                                                <button class="action-btn pin-thread <?php echo $thread->is_pinned ? 'active' : ''; ?>" 
                                                        data-thread-id="<?php echo $thread->id; ?>">
                                                    <i class="ats-icon-pin"></i>
                                                    <span><?php echo $thread->is_pinned ? __('Unpin', 'advanced-threads') : __('Pin', 'advanced-threads'); ?></span>
                                                </button>
                                                
                                                <button class="action-btn lock-thread <?php echo $thread->is_locked ? 'active' : ''; ?>" 
                                                        data-thread-id="<?php echo $thread->id; ?>">
                                                    <i class="ats-icon-lock"></i>
                                                    <span><?php echo $thread->is_locked ? __('Unlock', 'advanced-threads') : __('Lock', 'advanced-threads'); ?></span>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </footer>
                        <?php endif; ?>
                    </article>
                    
                    <?php
                    // Display replies section
                    if ($thread) {
                        $replies = $thread_manager->get_thread_replies($thread->id, 1, ats_get_option('replies_per_page', 50), 'oldest');
                        ?>
                        
                        <section class="ats-thread-replies" id="thread-replies">
                            <div class="replies-header">
                                <h3 class="replies-title">
                                    <?php printf(_n('%d Reply', '%d Replies', $thread->reply_count, 'advanced-threads'), $thread->reply_count); ?>
                                </h3>
                                
                                <?php if ($thread->reply_count > 0): ?>
                                    <div class="replies-sort">
                                        <label for="replies-sort-order"><?php _e('Sort by:', 'advanced-threads'); ?></label>
                                        <select id="replies-sort-order" data-thread-id="<?php echo $thread->id; ?>">
                                            <option value="oldest"><?php _e('Oldest First', 'advanced-threads'); ?></option>
                                            <option value="newest"><?php _e('Newest First', 'advanced-threads'); ?></option>
                                            <option value="most_voted"><?php _e('Most Voted', 'advanced-threads'); ?></option>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="replies-list" id="replies-list">
                                <?php if (!empty($replies['data'])): ?>
                                    <?php foreach ($replies['data'] as $reply): ?>
                                        <?php ats_render_reply_item($reply); ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-replies-message">
                                        <div class="empty-state">
                                            <i class="ats-icon-message-circle"></i>
                                            <h4><?php _e('No replies yet', 'advanced-threads'); ?></h4>
                                            <p><?php _e('Be the first to respond to this thread!', 'advanced-threads'); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($replies['total'] > ats_get_option('replies_per_page', 50)): ?>
                                <div class="replies-pagination">
                                    <button class="ats-btn ats-btn-outline load-more-replies" 
                                            data-thread-id="<?php echo $thread->id; ?>"
                                            data-page="2">
                                        <?php _e('Load More Replies', 'advanced-threads'); ?>
                                        <span class="loading-spinner" style="display: none;"></span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </section>
                        
                        <?php
                        // Display reply form or login prompt
                        if (is_user_logged_in() && !$thread->is_locked) {
                            ?>
                            <section class="ats-reply-form-section" id="reply-form-section">
                                <h4><?php _e('Post a Reply', 'advanced-threads'); ?></h4>
                                
                                <form class="ats-reply-form" id="reply-form" data-thread-id="<?php echo $thread->id; ?>">
                                    <?php wp_nonce_field('ats_add_reply', 'reply_nonce'); ?>
                                    
                                    <div class="form-group">
                                        <label for="reply-content" class="sr-only"><?php _e('Reply content', 'advanced-threads'); ?></label>
                                        
                                        <?php if (ats_get_option('enable_rich_editor', 1)): ?>
                                            <div class="reply-editor-container">
                                                <div id="reply-editor" class="reply-editor" 
                                                     data-placeholder="<?php esc_attr_e('Write your reply...', 'advanced-threads'); ?>"></div>
                                            </div>
                                            <textarea id="reply-content" name="reply_content" style="display: none;" required></textarea>
                                        <?php else: ?>
                                            <textarea id="reply-content" name="reply_content" 
                                                      placeholder="<?php esc_attr_e('Write your reply...', 'advanced-threads'); ?>"
                                                      rows="6" required></textarea>
                                        <?php endif; ?>
                                        
                                        <div class="form-footer">
                                            <div class="form-help">
                                                <div class="character-counter">
                                                    <span id="char-count">0</span> / 
                                                    <?php echo number_format(ats_get_option('max_content_length', 10000)); ?>
                                                </div>
                                                
                                                <div class="formatting-help">
                                                    <button type="button" class="help-toggle" aria-expanded="false">
                                                        <i class="ats-icon-help-circle"></i>
                                                        <?php _e('Formatting Help', 'advanced-threads'); ?>
                                                    </button>
                                                    <div class="help-content" style="display: none;">
                                                        <p><?php _e('You can use basic HTML tags and markdown formatting.', 'advanced-threads'); ?></p>
                                                        <ul>
                                                            <li><code>**bold**</code> → <strong>bold</strong></li>
                                                            <li><code>*italic*</code> → <em>italic</em></li>
                                                            <li><code>`code`</code> → <code>code</code></li>
                                                            <li><code>[link](url)</code> → <a href="#">link</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-actions">
                                                <?php if (ats_get_option('enable_image_uploads', 1)): ?>
                                                    <button type="button" class="ats-btn ats-btn-outline ats-btn-sm" id="add-image-btn">
                                                        <i class="ats-icon-image"></i>
                                                        <?php _e('Add Image', 'advanced-threads'); ?>
                                                    </button>
                                                    <input type="file" id="reply-image" name="reply_image" 
                                                           accept="image/*" style="display: none;">
                                                <?php endif; ?>
                                                
                                                <button type="button" class="ats-btn ats-btn-outline ats-btn-sm" id="preview-reply-btn">
                                                    <i class="ats-icon-eye"></i>
                                                    <?php _e('Preview', 'advanced-threads'); ?>
                                                </button>
                                                
                                                <button type="submit" class="ats-btn ats-btn-primary" id="submit-reply-btn">
                                                    <i class="ats-icon-send"></i>
                                                    <?php _e('Post Reply', 'advanced-threads'); ?>
                                                    <span class="loading-spinner" style="display: none;"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                
                                <div class="reply-preview" id="reply-preview" style="display: none;">
                                    <h5><?php _e('Preview', 'advanced-threads'); ?></h5>
                                    <div class="preview-content"></div>
                                    <div class="preview-actions">
                                        <button type="button" class="ats-btn ats-btn-outline ats-btn-sm" id="edit-reply-btn">
                                            <?php _e('Continue Editing', 'advanced-threads'); ?>
                                        </button>
                                    </div>
                                </div>
                            </section>
                        <?php } elseif (!is_user_logged_in()) { ?>
                            <section class="ats-login-prompt">
                                <div class="login-message">
                                    <i class="ats-icon-user"></i>
                                    <h4><?php _e('Join the Discussion', 'advanced-threads'); ?></h4>
                                    <p><?php _e('You need to be logged in to participate in this thread.', 'advanced-threads'); ?></p>
                                    
                                    <div class="login-actions">
                                        <a href="<?php echo wp_login_url(get_permalink()); ?>" 
                                           class="ats-btn ats-btn-primary">
                                            <?php _e('Log In', 'advanced-threads'); ?>
                                        </a>
                                        
                                        <?php if (get_option('users_can_register')): ?>
                                            <a href="<?php echo wp_registration_url(); ?>" 
                                               class="ats-btn ats-btn-outline">
                                                <?php _e('Register', 'advanced-threads'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </section>
                        <?php } elseif ($thread->is_locked) { ?>
                            <section class="ats-locked-notice">
                                <div class="locked-message">
                                    <i class="ats-icon-lock"></i>
                                    <h4><?php _e('Thread Locked', 'advanced-threads'); ?></h4>
                                    <p><?php _e('This thread has been locked and no longer accepts new replies.', 'advanced-threads'); ?></p>
                                </div>
                            </section>
                        <?php } ?>
                    <?php } ?>
                    
                <?php endwhile; ?>
            </div>
            
            <aside class="col-md-4 col-lg-3">
                <div class="ats-sidebar">
                    <?php if (is_active_sidebar('ats-thread-sidebar')): ?>
                        <?php dynamic_sidebar('ats-thread-sidebar'); ?>
                    <?php else: ?>
                        
                        <!-- Related threads widget -->
                        <?php if ($thread): ?>
                            <div class="widget ats-widget ats-related-threads">
                                <h3 class="widget-title"><?php _e('Related Threads', 'advanced-threads'); ?></h3>
                                <div class="widget-content">
                                    <?php
                                    $related_threads = $thread_manager->get_related_threads($thread->id, 5);
                                    if (!empty($related_threads)): ?>
                                        <ul class="related-threads-list">
                                            <?php foreach ($related_threads as $related): ?>
                                                <li class="related-thread-item">
                                                    <a href="<?php echo get_permalink($related->post_id); ?>" class="thread-link">
                                                        <?php echo esc_html($related->title); ?>
                                                    </a>
                                                    <div class="thread-meta">
                                                        <span class="reply-count"><?php echo $related->reply_count; ?> <?php _e('replies', 'advanced-threads'); ?></span>
                                                        <span class="thread-date"><?php echo ats_time_ago($related->created_at); ?></span>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p><?php _e('No related threads found.', 'advanced-threads'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Thread stats widget -->
                        <?php if ($thread): ?>
                            <div class="widget ats-widget ats-thread-stats">
                                <h3 class="widget-title"><?php _e('Thread Statistics', 'advanced-threads'); ?></h3>
                                <div class="widget-content">
                                    <ul class="stats-list">
                                        <li>
                                            <span class="stat-label"><?php _e('Created:', 'advanced-threads'); ?></span>
                                            <span class="stat-value"><?php echo ats_time_ago($thread->created_at); ?></span>
                                        </li>
                                        <li>
                                            <span class="stat-label"><?php _e('Last activity:', 'advanced-threads'); ?></span>
                                            <span class="stat-value"><?php echo ats_time_ago($thread->last_activity_at); ?></span>
                                        </li>
                                        <li>
                                            <span class="stat-label"><?php _e('Views:', 'advanced-threads'); ?></span>
                                            <span class="stat-value"><?php echo ats_format_number($thread->view_count); ?></span>
                                        </li>
                                        <li>
                                            <span class="stat-label"><?php _e('Replies:', 'advanced-threads'); ?></span>
                                            <span class="stat-value"><?php echo number_format($thread->reply_count); ?></span>
                                        </li>
                                        <?php if (ats_get_option('enable_voting', 1)): ?>
                                            <li>
                                                <span class="stat-label"><?php _e('Upvotes:', 'advanced-threads'); ?></span>
                                                <span class="stat-value"><?php echo number_format($thread->upvotes); ?></span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Categories widget -->
                        <div class="widget ats-widget ats-categories">
                            <h3 class="widget-title"><?php _e('Categories', 'advanced-threads'); ?></h3>
                            <div class="widget-content">
                                <?php
                                $categories = ats_get_categories();
                                if (!empty($categories)): ?>
                                    <ul class="categories-list">
                                        <?php foreach ($categories as $category): ?>
                                            <li class="category-item">
                                                <a href="<?php echo ats_get_category_url($category->slug); ?>" 
                                                   class="category-link"
                                                   style="border-left-color: <?php echo esc_attr($category->color); ?>">
                                                    <?php if ($category->icon): ?>
                                                        <i class="ats-icon-<?php echo esc_attr($category->icon); ?>"></i>
                                                    <?php endif; ?>
                                                    <span class="category-name"><?php echo esc_html($category->name); ?></span>
                                                    <span class="thread-count"><?php echo number_format($category->thread_count); ?></span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</div>

<?php get_footer(); ?>
                