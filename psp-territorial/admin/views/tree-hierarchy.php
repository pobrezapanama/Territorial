<?php
/**
 * Admin View: Tree Hierarchy
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = psp_territorial()->database;
$provinces = $db->get_by_type( 'province', array( 'limit' => 500 ) );
?>
<div class="wrap psp-territorial-wrap">
	<h1>
		<span class="dashicons dashicons-networking"></span>
		<?php esc_html_e( 'Vista de Árbol Jerárquico', 'psp-territorial' ); ?>
	</h1>
	<hr class="wp-header-end">

	<p>
		<button class="button" id="psp-expand-all"><?php esc_html_e( '➕ Expandir Todo', 'psp-territorial' ); ?></button>
		<button class="button" id="psp-collapse-all"><?php esc_html_e( '➖ Colapsar Todo', 'psp-territorial' ); ?></button>
		<input type="text" id="psp-tree-search" class="regular-text" placeholder="<?php esc_attr_e( 'Filtrar en árbol...', 'psp-territorial' ); ?>">
	</p>

	<div class="psp-hierarchy-tree" id="psp-hierarchy-tree" data-rest-url="<?php echo esc_url( rest_url( 'psp/v1' ) ); ?>">
		<ul class="psp-tree-root">
			<?php foreach ( $provinces as $province ) : ?>
				<li class="psp-tree-node psp-tree-province" data-id="<?php echo esc_attr( $province->id ); ?>" data-type="province">
					<span class="psp-tree-toggle">▶</span>
					<span class="psp-tree-label">
						<span class="psp-type-badge psp-type-province"><?php esc_html_e( 'Provincia', 'psp-territorial' ); ?></span>
						<strong><?php echo esc_html( $province->name ); ?></strong>
					</span>
					<span class="psp-tree-actions">
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'psp-territorial', 'action' => 'edit', 'id' => $province->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Editar', 'psp-territorial' ); ?>
						</a>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'psp-territorial-add', 'type' => 'district', 'parent_id' => $province->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
							<?php esc_html_e( '+ Distrito', 'psp-territorial' ); ?>
						</a>
					</span>
					<!-- Districts are loaded lazily via JS -->
					<ul class="psp-tree-children" style="display:none" data-loaded="false"></ul>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<p class="description">
		<?php esc_html_e( 'Haz clic en la flecha ▶ para expandir y cargar los elementos hijos.', 'psp-territorial' ); ?>
	</p>
</div>
