<?php
/**
 * Server-side render callback for the hwwpfaq/faq Gutenberg block.
 *
 * Variables available (injected by WP's block rendering engine):
 *
 * @var array    $attributes  Block attributes (e.g. $attributes['category']).
 * @var string   $content     Inner block content (unused – this block has no inner blocks).
 * @var WP_Block $block       The current WP_Block object.
 *
 * Output structure (BEM-style class names, ready for theme styling):
 *
 *   .hwwpfaq                       – outer wrapper
 *     .hwwpfaq__category           – one section per category
 *       .hwwpfaq__category-title   – <h2> category heading
 *       .hwwpfaq__list             – <dl> list of items
 *         .hwwpfaq__item           – <div> wrapping dt + dd
 *           .hwwpfaq__question     – <dt> the question
 *           .hwwpfaq__answer       – <dd> the answer (may contain HTML)
 */

defined( 'ABSPATH' ) || exit;

$category = isset( $attributes['category'] ) ? sanitize_text_field( $attributes['category'] ) : '';

$items = HWWPFAQ_DB::get_items( array(
	'category'    => $category,
	'active_only' => true,
	'published'   => true,
	'orderby'     => 'pub_date',
	'order'       => 'ASC',
) );

if ( empty( $items ) ) {
	echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'hwwpfaq hwwpfaq--empty' ) ) . '>'  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. '<p>' . esc_html__( 'No FAQ entries found.', 'hwwpfaq' ) . '</p>'
		. '</div>';
	return;
}

// Group entries by category while preserving insertion order.
$grouped = array();
foreach ( $items as $item ) {
	$grouped[ $item->category ][] = $item;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'hwwpfaq' ) ); ?>>  <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php foreach ( $grouped as $cat => $faq_items ) : ?>
		<section class="hwwpfaq__category">

			<?php if ( '' !== $cat ) : ?>
				<h2 class="hwwpfaq__category-title"><?php echo esc_html( $cat ); ?></h2>
			<?php endif; ?>

			<dl class="hwwpfaq__list">
				<?php foreach ( $faq_items as $faq ) : ?>
					<div class="hwwpfaq__item">
						<dt class="hwwpfaq__question"><?php echo esc_html( $faq->question ); ?></dt>
						<dd class="hwwpfaq__answer"><?php echo wp_kses_post( $faq->answer ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>

		</section>
	<?php endforeach; ?>

</div>
