<?php
/**
 * Main template file.
 *
 * @package Lotus_Licenses
 */

get_header();
?>
<div class="site-container content-area">
    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>
                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="entry__media">
                        <a href="<?php the_permalink(); ?>">
                            <?php the_post_thumbnail( 'large' ); ?>
                        </a>
                    </div>
                <?php endif; ?>
                <header class="entry__header">
                    <?php the_title( '<h1 class="entry__title">', '</h1>' ); ?>
                </header>
                <div class="entry__content">
                    <?php the_excerpt(); ?>
                </div>
                <footer class="entry__footer">
                    <a class="button button--primary" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Continue reading', 'lotus-licenses' ); ?></a>
                </footer>
            </article>
        <?php endwhile; ?>
        <div class="pagination">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e( 'We could not find any content. Start publishing to showcase your catalog and resources.', 'lotus-licenses' ); ?></p>
    <?php endif; ?>
</div>
<?php
get_footer();
