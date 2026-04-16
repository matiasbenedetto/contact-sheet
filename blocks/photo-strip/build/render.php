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

$images    = photo_strip_extract_images( $post->post_content );
$permalink = get_permalink( $post_id );

if ( empty( $images ) ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'photo-strip-item' ) ); ?>>
	<div class="photo-strip-images" style="--ps-height: <?php echo intval( $size_value ); ?>px">
		<?php foreach ( $images as $index => $image ) : ?>
			<div
				class="photo-strip-image"
				style="border-radius: <?php echo intval( $border_radius ); ?>px; animation-delay: <?php printf( '%.2f', $index * 0.06 ); ?>s"
			>
				<a href="<?php echo esc_url( $permalink ); ?>">
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
