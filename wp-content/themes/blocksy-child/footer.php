<?php
/**
 * Custom footer for B Active (Overrides Blocksy)
 */
blocksy_after_current_template();
do_action('blocksy:content:bottom');
?>
	</main>

	<?php
		do_action('blocksy:content:after');
		do_action('blocksy:footer:before');

		// Load custom B Active Footer
		get_template_part('template-parts/footer');

		do_action('blocksy:footer:after');
	?>
</div>

<?php wp_footer(); ?>

</body>
</html>