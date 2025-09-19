<?php
/* Template Archive Threads */
get_header(); ?>

<main class="ats-archive">
  <h1><?php post_type_archive_title(); ?></h1>

  <form class="ats-filters" method="get">
    <input type="search" name="s" value="<?= esc_attr(get_search_query()); ?>" placeholder="Cerca thread">
    <select name="sort">
      <option value="">Recenti</option>
      <option value="popular" <?= selected($_GET['sort']??'', 'popular'); ?>>Popolari</option>
    </select>
    <button type="submit">Applica</button>
  </form>

  <?php if (have_posts()): ?>
    <div class="ats-thread-list">
      <?php while (have_posts()): the_post(); ?>
        <article <?php post_class('ats-thread-card'); ?>>
          <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
          <p class="ats-meta">
            <?php echo get_avatar(get_the_author_meta('ID'), 32); ?>
            <?php the_author_posts_link(); ?> · <?php echo get_the_date(); ?>
          </p>
          <p><?php echo wp_trim_words(get_the_excerpt(), 24); ?></p>
        </article>
      <?php endwhile; ?>
    </div>
    <?php the_posts_pagination(); ?>
  <?php else: ?>
    <p>Nessun thread trovato.</p>
  <?php endif; ?>
</main>

<?php get_footer(); ?>
