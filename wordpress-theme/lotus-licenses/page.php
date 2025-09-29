<?php
/**
 * Page template.
 *
 * @package Lotus_Licenses
 */

get_header();
?>
<div class="site-container content-area">
    <?php while ( have_posts() ) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class( 'entry entry--page' ); ?>>
            <header class="entry__header">
                <?php the_title( '<h1 class="entry__title">', '</h1>' ); ?>
            </header>
            <div class="entry__content">
                <?php the_content(); ?>
            </div>
        </article>
        <?php
        if ( comments_open() || get_comments_number() ) {
            comments_template();
        }
        ?>
    <?php endwhile; ?>
</div>
<?php
get_footer();
