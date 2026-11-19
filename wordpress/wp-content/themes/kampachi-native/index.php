<?php
declare(strict_types=1);
get_header();
echo '<main class="kampachi-native-wrap">';
while (have_posts()) { the_post(); echo '<h2><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h2>'; the_excerpt(); }
the_posts_pagination();
echo '</main>';
get_footer();
