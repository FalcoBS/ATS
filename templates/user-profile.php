<?php
/* Template User Profile */
get_header();

$username = get_query_var('ats_profile');
$user = get_user_by('login',$username);

if (!$user) {
  echo '<p>Profilo non trovato.</p>';
  get_footer();
  exit;
}

$karma = intval(get_user_meta($user->ID,'ats_karma',true));
$threads = new WP_Query(['post_type'=>'thread','author'=>$user->ID,'posts_per_page'=>5]);
$comments = get_comments(['user_id'=>$user->ID,'number'=>5,'status'=>'approve']);
?>

<main class="ats-profile">
  <header>
    <?php echo get_avatar($user->ID,80); ?>
    <h1><?php echo esc_html($user->display_name); ?></h1>
    <p>@<?php echo esc_html($user->user_login); ?> · Karma: <?php echo $karma; ?></p>
    <button class="ats-follow-user" data-id="<?php echo $user->ID; ?>">Segui</button>
  </header>

  <section>
    <h2>Threads Recenti</h2>
    <?php if ($threads->have_posts()): ?>
      <ul>
        <?php while ($threads->have_posts()): $threads->the_post(); ?>
          <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
        <?php endwhile; wp_reset_postdata(); ?>
      </ul>
    <?php else: ?>
      <p>Nessun thread aperto.</p>
    <?php endif; ?>
  </section>

  <section>
    <h2>Ultime Risposte</h2>
    <?php if ($comments): ?>
      <ul>
        <?php foreach ($comments as $c): ?>
          <li>
            <a href="<?php echo esc_url(get_comment_link($c)); ?>">
              Su: <?php echo get_the_title($c->comment_post_ID); ?>
            </a>
            <div><?php echo wp_trim_words($c->comment_content, 16); ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>Nessuna risposta recente.</p>
    <?php endif; ?>
  </section>
</main>

<?php get_footer(); ?>
