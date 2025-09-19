<?php
/**
 * Threads Archive Template
 *
 * @package AdvancedThreadsSystem
 * @subpackage Templates
 */

get_header(); ?>

<div class="ats-threads-archive-wrapper">
    <div class="container">
        
        <header class="archive-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="archive-title">
                        <?php
                        if (is_post_type_archive('ats_thread')) {
                            _e('All Threads', 'advanced-threads');
                        } elseif (get_query_var('ats_category')) {
                            $category_slug = get_query_var('ats_category');
                            $category = ats_get_category_by_slug($category_slug);
                            if ($category) {
                                printf(__('Threads in %s', 'advanced-threads'), esc_html($category->name));
                            }
                        } elseif (get_query_var('s')) {
                            printf(__('Search Results for: %s', 'advanced-threads'), '<span class="search-term">' . esc_html(get_query_var('s')) . '</span>');
                        }
                        ?>
                    </h1>
                    
                    <?php
                    // Show category description if viewing category
                    if (get_query_var('ats_category')) {
                        $category_slug = get_query_var('ats_category');
                        $category = ats_get_category_by_slug($category_slug);
                        if ($category && !empty($category->description)) {
                            echo '<p class="archive-description">' . esc_html($category->description) . '</p>';
                        }
                    }
                    ?>
                </div>
                
                <div class="col-md-4 text-md-end">
                    <?php if (is_user_logged_in()): ?>
                        <a href="<?php echo esc_url(home_url('/create-thread/')); ?>" 
                           class="ats-btn ats-btn-primary">
                            <i class="ats-icon-plus"></i>
                            <?php _e('Create Thread', 'advanced-threads'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <div class="row">
            <div class="col-lg-9">
                
                <!-- Filters and sorting -->
                <div class="threads-controls">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="threads-filters">
                                <select id="thread-filter" class="form-select">
                                    <option value="all"><?php _e('All Threads', 'advanced-threads'); ?></option>
                                    <option value="unanswered"><?php _e('Unanswered', 'advanced-threads'); ?></option>
                                    <option value="solved"><?php _e('Solved', 'advanced-threads'); ?></option>
                                    <option value="pinned"><?php _e('Pinned', 'advanced-threads'); ?></option>
                                    <option value="locked"><?php _e('Locked', 'advanced-threads'); ?></option>
                                </select>
                                
                                <select id="category-filter" class="form-select">
                                    <option value=""><?php _e('All Categories', 'advanced-threads'); ?></option>
                                    <?php
                                    $categories = ats_get_categories();
                                    foreach ($categories as $cat) {
                                        $selected = (get_query_var('ats_category') === $cat->slug) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($cat->slug) . '" ' . $selected . '>';
                                        echo esc_html($cat->name);
                                        echo '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="threads-sorting">
                                <select id="thread-sort" class="form-select">
                                    <option value="latest"><?php _e('Latest Activity', 'advanced-threads'); ?></option>
                                    <option value="newest"><?php _e('Newest First', 'advanced-threads'); ?></option>
                                    <option value="oldest"><?php _e('Oldest First', 'advanced-threads'); ?></option>
                                    <option value="most_replies"><?php _e('Most Replies', 'advanced-threads'); ?></option>
                                    <option value="most_views"><?php _e('Most Views', 'advanced-threads'); ?></option>
                                    <?php if (ats_get_option('enable_voting', 1)): ?>
                                        <option value="most_voted"><?php _e('Most Voted', 'advanced-threads'); ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search form -->
                <div class="threads-search">
                    <form role="search" method="get" class="ats-search-form" action="<?php echo esc_url(home_url('/')); ?>">
                        <div class="search-field-wrapper">
                            <input type="search" class="form-control search-field" 
                                   placeholder="<?php esc_attr_e('Search threads...', 'advanced-threads'); ?>" 
                                   value="<?php echo esc_attr(get_search_query()); ?>" name="s" />
                            <input type="hidden" name="post_type" value="ats_thread" />
                            <button type="submit" class="search-submit">
                                <i class="ats-icon-search"></i>
                                <span class="sr-only"><?php _e('Search', 'advanced-threads'); ?></span>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Threads list -->
                <div class="threads-list" id="threads-list">
                    
                    <?php if (have_posts()): ?>
                        
                        <div class="threads-header">
                            <div class="threads-count">
                                <?php
                                global $wp_query;
                                $total = $wp_query->found_posts;
                                printf(_n('%d thread found', '%d threads found', $total, 'advanced-threads'), number_format($total));
                                ?>
                            </div>
                            
                            <?php if (get_query_var('s')): ?>
                                <div class="search-summary">
                                    <?php printf(__('Showing results for "%s"', 'advanced-threads'), '<strong>' . esc_html(get_query_var('s')) . '</strong>'); ?>
                                    <a href="<?php echo esc_url(get_post_type_archive_link('ats_thread')); ?>" 
                                       class="clear-search">
                                        <?php _e('Clear search', 'advanced-threads'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="threads-grid">
                            <?php 
                            $thread_manager = new ATS_Thread_Manager();
                            
                            while (have_posts()): the_post(); 
                                $thread = $thread_manager->get_thread_by_post_id(get_the_ID());
                                if (!$thread) continue;
                                ?>
                                
                                <article class="thread-item <?php echo $thread->is_pinned ? 'pinned' : ''; ?> <?php echo $thread->is_locked ? 'locked' : ''; ?>">
                                    
                                    <div class="thread-avatar">
                                        <a href="<?php echo ats_get_user_profile_url($thread->author_id); ?>">
                                            <?php echo get_avatar($thread->author_id, 48); ?>
                                        </a>
                                    </div>
                                    
                                    <div class="thread-content">
                                        <div class="thread-header">
                                            <h3 class="thread-title">
                                                <a href="<?php the_permalink(); ?>" class="thread-link">
                                                    <?php if ($thread->is_pinned): ?>
                                                        <i class="ats-icon-pin thread-icon pinned" title="<?php esc_attr_e('Pinned', 'advanced-threads'); ?>"></i>
                                                    <?php endif; ?>
                                                    <?php if ($thread->is_locked): ?>
                                                        <i class="ats-icon-lock thread-icon locked" title="<?php esc_attr_e('Locked', 'advanced-threads'); ?>"></i>
                                                    <?php endif; ?>
                                                    <?php the_title(); ?>
                                                </a>
                                            </h3>
                                            
                                            <?php if ($thread->category): ?>
                                                <?php $category = ats_get_category_by_slug($thread->category); ?>
                                                <?php if ($category): ?>
                                                    <a href="<?php echo ats_get_category_url($thread->category); ?>" 
                                                       class="thread-category"
                                                       style="color: <?php echo esc_attr($category->color); ?>">
                                                        <?php if ($category->icon): ?>
                                                            <i class="ats-icon-<?php echo esc_attr($category->icon); ?>"></i>
                                                        <?php endif; ?>
                                                        <?php echo esc_html($category->name); ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="thread-excerpt">
                                            <?php echo wp_trim_words(get_the_excerpt(), 25); ?>
                                        </div>
                                        
                                        <div class="thread-meta">
                                            <span class="thread-author">
                                                <?php _e('by', 'advanced-threads'); ?>
                                                <a href="<?php echo ats_get_user_profile_url($thread->author_id); ?>">
                                                    <?php echo esc_html(get_the_author_meta('display_name', $thread->author_id)); ?>
                                                </a>
                                            </span>
                                            
                                            <span class="thread-date">
                                                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                                    <?php echo ats_time_ago(get_the_date('Y-m-d H:i:s')); ?>
                                                </time>
                                            </span>
                                            
                                            <?php if ($thread->tags): ?>
                                                <div class="thread-tags">
                                                    <?php
                                                    $tags = array_slice(explode(',', $thread->tags), 0, 3);
                                                    foreach ($tags as $tag):
                                                        $tag = trim($tag);
                                                        if (!empty($tag)): ?>
                                                            <a href="<?php echo home_url('/threads/tag/' . urlencode($tag) . '/'); ?>" 
                                                               class="thread-tag">#<?php echo esc_html($tag); ?></a>
                                                        <?php endif;
                                                    endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="thread-stats">
                                        <div class="stat-item replies">
                                            <i class="ats-icon-message-circle"></i>
                                            <span class="stat-value"><?php echo number_format($thread->reply_count); ?></span>
                                            <span class="stat-label"><?php _e('replies', 'advanced-threads'); ?></span>
                                        </div>
                                        
                                        <div class="stat-item views">
                                            <i class="ats-icon-eye"></i>
                                            <span class="stat-value"><?php echo ats_format_number($thread->view_count); ?></span>
                                            <span class="stat-label"><?php _e('views', 'advanced-threads'); ?></span>
                                        </div>
                                        
                                        <?php if (ats_get_option('enable_voting', 1) && ($thread->upvotes > 0 || $thread->downvotes > 0)): ?>
                                            <div class="stat-item votes">
                                                <i class="ats-icon-thumbs-up"></i>
                                                <span class="stat-value"><?php echo $thread->upvotes - $thread->downvotes; ?></span>
                                                <span class="stat-label"><?php _e('score', 'advanced-threads'); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="thread-activity">
                                        <div class="last-activity">
                                            <span class="activity-label"><?php _e('Last activity:', 'advanced-threads'); ?></span>
                                            <time datetime="<?php echo esc_attr($thread->last_activity_at); ?>">
                                                <?php echo ats_time_ago($thread->last_activity_at); ?>
                                            </time>
                                        </div>
                                    </div>
                                    
                                </article>
                                
                            <?php endwhile; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="threads-pagination">
                            <?php
                            $pagination = paginate_links(array(
                                'type' => 'array',
                                'prev_text' => '<i class="ats-icon-chevron-left"></i> ' . __('Previous', 'advanced-threads'),
                                'next_text' => __('Next', 'advanced-threads') . ' <i class="ats-icon-chevron-right"></i>',
                                'mid_size' => 2,
                                'end_size' => 1
                            ));
                            
                            if ($pagination) {
                                echo '<nav class="pagination-nav" aria-label="' . esc_attr__('Threads pagination', 'advanced-threads') . '">';
                                echo '<ul class="pagination">';
                                foreach ($pagination as $page) {
                                    echo '<li class="page-item">' . $page . '</li>';
                                }
                                echo '</ul>';
                                echo '</nav>';
                            }
                            ?>
                        </div>
                        
                    <?php else: ?>
                        
                        <div class="no-threads-found">
                            <div class="empty-state">
                                <i class="ats-icon-message-square"></i>
                                <h3>
                                    <?php 
                                    if (get_query_var('s')) {
                                        _e('No threads found for your search', 'advanced-threads');
                                    } else {
                                        _e('No threads found', 'advanced-threads');
                                    }
                                    ?>
                                </h3>
                                <p>
                                    <?php 
                                    if (get_query_var('s')) {
                                        _e('Try adjusting your search terms or browse all threads.', 'advanced-threads');
                                    } else {
                                        _e('Be the first to start a discussion in this community!', 'advanced-threads');
                                    }
                                    ?>
                                </p>
                                
                                <div class="empty-actions">
                                    <?php if (is_user_logged_in()): ?>
                                        <a href="<?php echo esc_url(home_url('/create-thread/')); ?>" 
                                           class="ats-btn ats-btn-primary">
                                            <i class="ats-icon-plus"></i>
                                            <?php _e('Create First Thread', 'advanced-threads'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (get_query_var('s')): ?>
                                        <a href="<?php echo esc_url(get_post_type_archive_link('ats_thread')); ?>" 
                                           class="ats-btn ats-btn-outline">
                                            <i class="ats-icon-grid"></i>
                                            <?php _e('Browse All Threads', 'advanced-threads'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                    <?php endif; ?>
                    
                </div>
                
            </div>
            
            <aside class="col-lg-3">
                <div class="ats-sidebar">
                    
                    <?php if (is_active_sidebar('ats-archive-sidebar')): ?>
                        <?php dynamic_sidebar('ats-archive-sidebar'); ?>
                    <?php else: ?>
                        
                        <!-- Featured threads -->
                        <div class="widget ats-widget ats-featured-threads">
                            <h3 class="widget-title"><?php _e('Featured Threads', 'advanced-threads'); ?></h3>
                            <div class="widget-content">
                                <?php
                                $thread_manager = new ATS_Thread_Manager();
                                $featured_threads = $thread_manager->get_featured_threads(5);
                                if (!empty($featured_threads)): ?>
                                    <ul class="featured-threads-list">
                                        <?php foreach ($featured_threads as $featured): ?>
                                            <li class="featured-thread-item">
                                                <a href="<?php echo get_permalink($featured->post_id); ?>" class="thread-link">
                                                    <i class="ats-icon-star"></i>
                                                    <?php echo esc_html($featured->title); ?>
                                                </a>
                                                <div class="thread-meta">
                                                    <span class="reply-count"><?php echo $featured->reply_count; ?> <?php _e('replies', 'advanced-threads'); ?></span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p><?php _e('No featured threads yet.', 'advanced-threads'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Categories -->
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
                                                   class="category-link <?php echo (get_query_var('ats_category') === $category->slug) ? 'active' : ''; ?>"
                                                   style="border-left-color: <?php echo esc_attr($category->color); ?>">
                                                    <div class="category-info">
                                                        <?php if ($category->icon): ?>
                                                            <i class="ats-icon-<?php echo esc_attr($category->icon); ?>"></i>
                                                        <?php endif; ?>
                                                        <span class="category-name"><?php echo esc_html($category->name); ?></span>
                                                    </div>
                                                    <span class="thread-count"><?php echo number_format($category->thread_count); ?></span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Popular tags -->
                        <div class="widget ats-widget ats-popular-tags">
                            <h3 class="widget-title"><?php _e('Popular Tags', 'advanced-threads'); ?></h3>
                            <div class="widget-content">
                                <?php
                                $popular_tags = ats_get_popular_tags(15);
                                if (!empty($popular_tags)): ?>
                                    <div class="tags-cloud">
                                        <?php foreach ($popular_tags as $tag): ?>
                                            <a href="<?php echo home_url('/threads/tag/' . urlencode($tag->name) . '/'); ?>" 
                                               class="tag-link"
                                               style="font-size: <?php echo min(1.2, 0.8 + ($tag->count / 20)); ?>em;">
                                                #<?php echo esc_html($tag->name); ?>
                                                <span class="tag-count">(<?php echo $tag->count; ?>)</span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p><?php _e('No tags found.', 'advanced-threads'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Community stats -->
                        <div class="widget ats-widget ats-community-stats">
                            <h3 class="widget-title"><?php _e('Community Stats', 'advanced-threads'); ?></h3>
                            <div class="widget-content">
                                <?php
                                $stats = ats_get_community_stats();
                                ?>
                                <ul class="stats-list">
                                    <li>
                                        <span class="stat-label"><?php _e('Total Threads:', 'advanced-threads'); ?></span>
                                        <span class="stat-value"><?php echo number_format($stats['total_threads'] ?? 0); ?></span>
                                    </li>
                                    <li>
                                        <span class="stat-label"><?php _e('Total Replies:', 'advanced-threads'); ?></span>
                                        <span class="stat-value"><?php echo number_format($stats['total_replies'] ?? 0); ?></span>
                                    </li>
                                    <li>
                                        <span class="stat-label"><?php _e('Active Members:', 'advanced-threads'); ?></span>
                                        <span class="stat-value"><?php echo number_format($stats['active_users'] ?? 0); ?></span>
                                    </li>
                                    <li>
                                        <span class="stat-label"><?php _e('Online Now:', 'advanced-threads'); ?></span>
                                        <span class="stat-value"><?php echo number_format($stats['online_users'] ?? 0); ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Recent activity -->
                        <div class="widget ats-widget ats-recent-activity">
                            <h3 class="widget-title"><?php _e('Recent Activity', 'advanced-threads'); ?></h3>
                            <div class="widget-content">
                                <?php
                                $recent_activity = ats_get_recent_activity(5);
                                if (!empty($recent_activity)): ?>
                                    <ul class="activity-list">
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <li class="activity-item">
                                                <div class="activity-avatar">
                                                    <?php echo get_avatar($activity->user_id, 24); ?>
                                                </div>
                                                <div class="activity-content">
                                                    <a href="<?php echo ats_get_user_profile_url($activity->user_id); ?>">
                                                        <?php echo esc_html(get_the_author_meta('display_name', $activity->user_id)); ?>
                                                    </a>
                                                    <?php echo ats_format_activity_text($activity); ?>
                                                    <div class="activity-time">
                                                        <?php echo ats_time_ago($activity->created_at); ?>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p><?php _e('No recent activity.', 'advanced-threads'); ?></p>
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