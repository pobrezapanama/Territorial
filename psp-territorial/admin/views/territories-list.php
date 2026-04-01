<?php
/**
 * Admin View: Territories List
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle message notices.
$msg = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';

// Build query args.
$current_type   = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
$search         = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged          = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page       = 50;
$offset         = ( $paged - 1 ) * $per_page;

global $psp_territorial;
$db = psp_territorial()->database;

$query_args = array(
	'limit'  => $per_page,
	'offset' => $offset,
	'search' => $search,
);
if ( ! empty( $current_type ) && PSP_Territorial_Utils::is_valid_type( $current_type ) ) {
	$query_args['type'] = $current_type;
}

$territories = $db->get_territories( $query_args );
$total       = $db->count_territories( $query_args );
$total_pages = ceil( $total / $per_page );

// Type counts for filter tabs.
$type_counts = array();
foreach ( PSP_Territorial_Utils::$types as $t ) {
	$type_counts[ $t ] = $db->count_territories( array( 'type' => $t ) );
}
?>
<div class="wrap psp-territorial-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-location-alt"></span>
		<?php esc_html_e( 'PSP Territorial – Territorios', 'psp-territorial' ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=psp-territorial-add' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '+ Agregar Nuevo', 'psp-territorial' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( $msg ) : ?>
		<div class="notice notice-<?php echo 'error' === $msg ? 'error' : 'success'; ?> is-dismissible">
			<p>
				<?php
				$messages = array(
					'created' => __( '✅ Territorio creado correctamente.', 'psp-territorial' ),
					'updated' => __( '✅ Territorio actualizado correctamente.', 'psp-territorial' ),
					'deleted' => __( '✅ Territorio eliminado correctamente.', 'psp-territorial' ),
					'error'   => __( '❌ Hubo un error al procesar la solicitud.', 'psp-territorial' ),
				);
				echo esc_html( $messages[ $msg ] ?? $msg );
				?>
			</p>
		</div>
	<?php endif; ?>

	<!-- Summary Stats -->
	<div class="psp-stats-bar">
		<?php foreach ( PSP_Territorial_Utils::$types as $t ) : ?>
			<div class="psp-stat-item <?php echo $current_type === $t ? 'active' : ''; ?>">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'psp-territorial', 'type' => $t ), admin_url( 'admin.php' ) ) ); ?>">
					<span class="psp-stat-count"><?php echo esc_html( number_format_i18n( $type_counts[ $t ] ) ); ?></span>
					<span class="psp-stat-label"><?php echo esc_html( PSP_Territorial_Utils::get_type_label( $t ) ); ?></span>
				</a>
			</div>
		<?php endforeach; ?>
		<div class="psp-stat-item <?php echo empty( $current_type ) ? 'active' : ''; ?>">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=psp-territorial' ) ); ?>">
				<span class="psp-stat-count"><?php echo esc_html( number_format_i18n( array_sum( $type_counts ) ) ); ?></span>
				<span class="psp-stat-label"><?php esc_html_e( 'Total', 'psp-territorial' ); ?></span>
			</a>
		</div>
	</div>

	<!-- Search form -->
	<form method="get" class="psp-search-form">
		<input type="hidden" name="page" value="psp-territorial">
		<?php if ( $current_type ) : ?>
			<input type="hidden" name="type" value="<?php echo esc_attr( $current_type ); ?>">
		<?php endif; ?>
		<p class="search-box">
			<label for="psp-search-input" class="screen-reader-text"><?php esc_html_e( 'Buscar territorios', 'psp-territorial' ); ?></label>
			<input type="search" id="psp-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar por nombre...', 'psp-territorial' ); ?>">
			<input type="submit" class="button" value="<?php esc_attr_e( 'Buscar', 'psp-territorial' ); ?>">
		</p>
	</form>

	<!-- Results count -->
	<div class="tablenav top">
		<div class="alignleft actions">
			<span class="displaying-num">
				<?php
				printf(
					/* translators: %s: Number of items */
					esc_html( _n( '%s elemento', '%s elementos', $total, 'psp-territorial' ) ),
					esc_html( number_format_i18n( $total ) )
				);
				?>
			</span>
		</div>
		<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav-pages">
			<?php
			echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'total'     => $total_pages,
				'current'   => $paged,
			) );
			?>
		</div>
		<?php endif; ?>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'psp-territorial' ); ?></th>
				<th><?php esc_html_e( 'Nombre', 'psp-territorial' ); ?></th>
				<th><?php esc_html_e( 'Tipo', 'psp-territorial' ); ?></th>
				<th><?php esc_html_e( 'Código', 'psp-territorial' ); ?></th>
				<th><?php esc_html_e( 'Padre', 'psp-territorial' ); ?></th>
				<th><?php esc_html_e( 'Hijos', 'psp-territorial' ); ?></th>
				<th><?php esc_html_e( 'Estado', 'psp-territorial' ); ?></th>
				<th><?php esc_html_e( 'Acciones', 'psp-territorial' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $territories ) ) : ?>
				<tr>
					<td colspan="8" style="text-align:center;">
						<?php esc_html_e( 'No se encontraron territorios.', 'psp-territorial' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $territories as $territory ) :
					$parent = $territory->parent_id ? $db->get_by_id( $territory->parent_id ) : null;
					$children_count = $db->count_children( $territory->id );
					$edit_url   = add_query_arg( array( 'page' => 'psp-territorial', 'action' => 'edit', 'id' => $territory->id ), admin_url( 'admin.php' ) );
					$delete_url = wp_nonce_url(
						add_query_arg(
							array( 'action' => 'psp_territorial_delete', 'id' => $territory->id ),
							admin_url( 'admin-post.php' )
						),
						'psp_territorial_delete_' . $territory->id
					);
				?>
				<tr>
					<td><?php echo esc_html( $territory->id ); ?></td>
					<td>
						<strong>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $territory->name ); ?></a>
						</strong>
						<br><small><?php echo esc_html( $territory->slug ); ?></small>
					</td>
					<td>
						<span class="psp-type-badge psp-type-<?php echo esc_attr( $territory->type ); ?>">
							<?php echo esc_html( PSP_Territorial_Utils::get_type_label( $territory->type ) ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $territory->code ); ?></td>
					<td>
						<?php if ( $parent ) : ?>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'psp-territorial', 'action' => 'edit', 'id' => $parent->id ), admin_url( 'admin.php' ) ) ); ?>">
								<?php echo esc_html( $parent->name ); ?>
							</a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $children_count ); ?></td>
					<td>
						<?php if ( $territory->is_active ) : ?>
							<span class="psp-status-active">✅ <?php esc_html_e( 'Activo', 'psp-territorial' ); ?></span>
						<?php else : ?>
							<span class="psp-status-inactive">⛔ <?php esc_html_e( 'Inactivo', 'psp-territorial' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
							<?php esc_html_e( 'Editar', 'psp-territorial' ); ?>
						</a>
						<a href="<?php echo esc_url( $delete_url ); ?>"
							class="button button-small button-link-delete psp-confirm-delete"
							data-children="<?php echo esc_attr( $children_count ); ?>">
							<?php esc_html_e( 'Eliminar', 'psp-territorial' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
