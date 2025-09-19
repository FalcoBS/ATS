<?php
/* Template Single Thread */
get_header();
the_post(); ?>

<article <?php post_class('ats-thread-single'); ?>>
  <header>
    <h1><?php the_title(); ?></h1>
    <div class="ats-meta">
      <?php echo get_avatar(get_the_author_meta('ID'), 40); ?>
      <span><?php the_author_posts_link(); ?></span>
      <span><?php echo get_the_date(); ?></span>
    </div>
  </header>

  <?php if (has_post_thumbnail()) the_post_thumbnail('large'); ?>

  <div class="ats-thread-body">
    <?php the_content(); ?>
  </div>

  <div class="ats-thread-actions">
    <button class="ats-follow-thread" data-id="<?php the_ID(); ?>">Segui</button>
    <button class="ats-vote-thread" data-id="<?php the_ID(); ?>" data-delta="1">▲</button>
    <span class="ats-score"><?php echo get_post_meta(get_the_ID(),'score',true) ?: 0; ?></span>
    <button class="ats-vote-thread" data-id="<?php the_ID(); ?>" data-delta="-1">▼</button>
  </div>

  <section class="ats-replies">
    <h2>Risposte</h2>
    <?php comments_template(); ?>
  </section>
</article>

<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"DiscussionForumPosting",
  "headline":<?= wp_json_encode(get_the_title()); ?>,
  "datePublished":"<?= get_the_date('c'); ?>",
  "author":{"@type":"Person","name":<?= wp_json_encode(get_the_author()); ?>},
  "articleBody":<?= wp_json_encode(wp_strip_all_tags(get_the_content())); ?>
}
</script>

<?php get_footer(); ?>
