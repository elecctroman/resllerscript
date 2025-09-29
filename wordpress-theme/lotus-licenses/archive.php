<?php
/**
 * Archive template.
 *
 * @package Lotus_Licenses
 */

get_header();
?>
<div class="site-container content-area">
    <header class="archive-header">
        <?php the_archive_title( '<h1 class="archive-title">', '</h1>' ); ?>
        <?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
    </header>
    <?php if ( have_posts() ) : ?>
        <div class="archive-grid">
            <?php while ( have_posts() ) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class( 'entry entry--archive' ); ?>>
                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="entry__media">
                            <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'medium_large' ); ?></a>
                        </div>
                    <?php endif; ?>
                    <header class="entry__header">
                        <?php the_title( '<h2 class="entry__title">', '</h2>' ); ?>
                    </header>
                    <div class="entry__content">
                        <?php the_excerpt(); ?>
                    </div>
                    <footer class="entry__footer">
                        <a class="button button--ghost" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read more', 'lotus-licenses' ); ?></a>
                    </footer>
                </article>
            <?php endwhile; ?>
        </div>
        <div class="pagination">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e( 'There is no content to display yet. Publish posts or products to populate this feed.', 'lotus-licenses' ); ?></p>
    <?php endif; ?>
</div>
<?php
get_footer();
