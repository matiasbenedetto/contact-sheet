<?php
/**
 * Photo Strip block — server-side render template.
 *
 * Available variables (set by WordPress before this file is included):
 *   $attributes (array)    – Block attributes.
 *   $content    (string)   – Inner block content (empty for this block).
 *   $block      (WP_Block) – Block instance, including context.
 */

$post_id = isset( $block->context['postId'] ) ? $block->context['postId'] : get_the_ID();

if ( ! $post_id ) {
	return;
}

$post = get_post( $post_id );
if ( ! $post ) {
	return;
}

$size_value    = isset( $attributes['sizeValue'] )    ? intval( $attributes['sizeValue'] )    : 200;
$border_radius = isset( $attributes['borderRadius'] ) ? intval( $attributes['borderRadius'] ) : 4;

$images    = contact_sheet_photo_strip_extract_images( $post->post_content );
$permalink = get_permalink( $post_id );

// Inside a Query Loop, core/post-template provides the postId context.
// (queryId is not forwarded to grandchildren on the server side.)
$in_query_loop = isset( $block->context['postId'] );

if ( empty( $images ) && ! $in_query_loop ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'photo-strip-item' ) ); ?>>
	<div class="photo-strip-images" style="--ps-height: <?php echo intval( $size_value ); ?>px">
		<?php if ( empty( $images ) ) : ?>
			<div
				class="photo-strip-image is-placeholder"
				style="border-radius: <?php echo max( intval( $border_radius ), 3 ); ?>px;"
			>
				<a href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( get_the_title( $post_id ) ); ?>"></a>
			</div>
		<?php endif; ?>
		<?php foreach ( $images as $index => $image ) : ?>
			<div
				class="photo-strip-image"
				style="border-radius: <?php echo max( intval( $border_radius ), 3 ); ?>px; animation-delay: <?php printf( '%.2f', $index * 0.06 ); ?>s; --vignette-opacity: <?php printf( '%.2f', mt_rand( 30, 100 ) / 100 ); ?>"
			>
				<?php // Every image links to the same post — expose only the first
				// link to keyboard/AT users; the rest stay mouse-clickable. ?>
				<a
					href="<?php echo esc_url( $permalink ); ?>"
					<?php if ( 0 === $index ) : ?>
						aria-label="<?php echo esc_attr( get_the_title( $post_id ) ); ?>"
					<?php else : ?>
						tabindex="-1" aria-hidden="true"
					<?php endif; ?>
				>
					<img
						src="<?php echo esc_url( $image['src'] ); ?>"
						alt="<?php echo esc_attr( $image['alt'] ); ?>"
						loading="lazy"
					/>
				</a>
			</div>
		<?php endforeach; ?>
	</div>
</div>
