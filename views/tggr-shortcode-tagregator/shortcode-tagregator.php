<div
	id="<?php echo esc_attr( Tagregator::CSS_PREFIX ); ?>media-item-container"
	class="<?php echo esc_attr( $attributes['layout'] ); ?>"
	data-hashtag="<?php echo esc_attr( $attributes['hashtag'] ); ?>"
	aria-live="polite">

	<p>Loading new posts.</p>
</div> <!-- end media-item-container -->

<script type="text/javascript">
	var tggrData = {
		ApiUrl:          '<?php echo esc_url( get_json_url() );        ?>',
		mediaTypes:       <?php echo wp_json_encode( $media_sources ); ?>,
		logos:            <?php echo wp_json_encode( $logos );         ?>,
		hashtag:         '<?php echo esc_js( $attributes['hashtag'] ); ?>',
		layout:          '<?php echo esc_js( $attributes['layout'] );  ?>',
		refreshInterval: <?php echo esc_js( $this->refresh_interval ); ?>
	};
</script>
