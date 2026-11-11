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
<script>document.body.classList.add('acf-no-post-types');</script>
<div class="acf-no-post-types-wrapper">
	<div class="acf-no-post-types-inner">
		<img src="<?php echo esc_url( acf_get_url( 'assets/images/empty-post-types.svg' ) ); ?>" />
		<h2><?php esc_html_e( 'Add Your First Post Type', 'acf' ); ?></h2>
		<p><?php esc_html_e( 'Expand the functionality of WordPress beyond standard posts and pages with custom post types.', 'acf' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=acf-post-type' ) ); ?>" class="acf-btn"><i class="acf-icon acf-icon-plus"></i> <?php esc_html_e( 'Add Post Type', 'acf' ); ?></a>
	</div>
</div>
