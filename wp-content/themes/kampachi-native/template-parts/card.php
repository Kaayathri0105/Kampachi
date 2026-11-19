<?php
declare(strict_types=1);
?><article class="kampachi-native-card"><a href="<?php the_permalink(); ?>">
<?php if (has_post_thumbnail()) { the_post_thumbnail('medium_large', ['loading' => 'lazy']); } ?>
<h2><?php echo esc_html(get_the_title()); ?></h2></a><p><?php echo esc_html(get_the_excerpt()); ?></p>
<p><a class="kc-discover" href="<?php the_permalink(); ?>"><?php echo esc_html(kampachi_native_label('discover')); ?></a></p></article>
