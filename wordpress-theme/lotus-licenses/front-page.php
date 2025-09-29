<?php
/**
 * Front page template.
 *
 * @package Lotus_Licenses
 */

get_header();
?>
<div class="lotus-front-page">
    <?php get_template_part( 'template-parts/hero' ); ?>
    <?php get_template_part( 'template-parts/features' ); ?>
    <?php get_template_part( 'template-parts/product-showcase' ); ?>
    <?php get_template_part( 'template-parts/testimonials' ); ?>
    <?php get_template_part( 'template-parts/cta' ); ?>
</div>
<?php
get_footer();
