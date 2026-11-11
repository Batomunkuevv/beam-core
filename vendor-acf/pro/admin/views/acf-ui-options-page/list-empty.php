<?php
/**
 * @package ACF
 * @author  WP Engine
 *
 * © 2025 Advanced Custom Fields (ACF®). All rights reserved.
 * "ACF" is a trademark of WP Engine.
 * Licensed under the GNU General Public License v2 or later.
 * https://www.gnu.org/licenses/gpl-2.0.html
 */
?>
<script>document.body.classList.add('acf-no-options-pages');</script>
<div class="acf-no-options-pages-wrapper">
	<div class="acf-no-options-pages-inner">
		<img src="<?php echo esc_url( acf_get_url( 'assets/images/empty-post-types.svg' ) ); ?>" />
		<h2><?php esc_html_e( 'Add Your First Options Page', 'acf' ); ?></h2>
		<p><?php esc_html_e( 'Options pages are custom admin pages for managing global settings via fields.', 'acf' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=acf-ui-options-page' ) ); ?>" class="acf-btn"><i class="acf-icon acf-icon-plus"></i> <?php esc_html_e( 'Add Options Page', 'acf' ); ?></a>
	</div>
</div>
