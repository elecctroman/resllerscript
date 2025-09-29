<?php
/**
 * Single post template.
 *
 * @package Lotus_Licenses
 */

get_header();
?>
<div class="site-container content-area">
    <?php while ( have_posts() ) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class( 'entry entry--single' ); ?>>
            <header class="entry__header">
                <?php the_title( '<h1 class="entry__title">', '</h1>' ); ?>
                <div class="entry__meta">
                    <span><?php echo esc_html( get_the_date() ); ?></span>
                    <span><?php esc_html_e( 'by', 'lotus-licenses' ); ?> <?php the_author_posts_link(); ?></span>
                </div>
            </header>
            <div class="entry__content">
                <?php the_content(); ?>
            </div>
            <footer class="entry__footer">
                <?php the_tags( '<div class="entry__tags">' . esc_html__( 'Tags: ', 'lotus-licenses' ), ', ', '</div>' ); ?>
            </footer>
        </article>
        <?php
        if ( comments_open() || get_comments_number() ) {
            comments_template();
        }
        ?>
        <nav class="post-navigation">
            <?php the_post_navigation(); ?>
        </nav>
    <?php endwhile; ?>
</div>
<?php
get_footer();
