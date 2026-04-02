<?php
/**
 * Admin view – list all territories.
 *
 * @package PSP_Territorial
 * @var PSP_Admin $this
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type        = isset( $_GET['entity_type'] ) ? sanitize_text_field( wp_unslash( $_GET['entity_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
$per_page    = 50;

$valid_types = [ '', 'provincia', 'distrito', 'corregimiento', 'comunidad' ];
if ( ! in_array( $type, $valid_types, true ) ) {
	$type = '';
}

// Fetch data.
if ( $search ) {
	$items = PSP_Database::search( $search, $type );
	$total = count( $items );
} elseif ( $type ) {
	$total = PSP_Database::count_by_type( $type );
	$items = PSP_Database::get_by_type( $type, null, $per_page, $paged );
} else {
	$total = PSP_Database::count_all();
	global $wpdb;
	$table  = PSP_Database::table();
	$offset = ( $paged - 1 ) * $per_page;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$items  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY type, name LIMIT %d OFFSET %d", $per_page, $offset ) );
}

$total_pages = max( 1, (int) ceil( $total / $per_page ) );
$base_url    = admin_url( 'admin.php?page=psp-territorial' );
?>
<div class="wrap psp-territorial-admin">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'PSP Territorial', 'psp-territorial' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=psp-territorial-edit&action=new' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Agregar nuevo', 'psp-territorial' ); ?>
		</a>
	</h1>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['msg'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<?php
		$msg_map = [
			'created' => [ 'success', __( 'Entidad creada exitosamente.', 'psp-territorial' ) ],
			'updated' => [ 'success', __( 'Entidad actualizada.', 'psp-territorial' ) ],
			'deleted' => [ 'success', __( 'Entidad eliminada.', 'psp-territorial' ) ],
			'error'   => [ 'error',   __( 'Ocurrió un error. Intente de nuevo.', 'psp-territorial' ) ],
		];
		$msg_key = sanitize_key( $_GET['msg'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $msg_map[ $msg_key ] ) ) :
			[ $msg_type, $msg_text ] = $msg_map[ $msg_key ];
			?>
			<div class="notice notice-<?php echo esc_attr( $msg_type ); ?> is-dismissible">
				<p><?php echo esc_html( $msg_text ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<!-- Filter bar -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="psp-filter-bar">
		<input type="hidden" name="page" value="psp-territorial">
		<select name="entity_type">
			<option value=""><?php esc_html_e( 'Todos los tipos', 'psp-territorial' ); ?></option>
			<option value="provincia"     <?php selected( $type, 'provincia' ); ?>><?php esc_html_e( 'Provincias', 'psp-territorial' ); ?></option>
			<option value="distrito"      <?php selected( $type, 'distrito' ); ?>><?php esc_html_e( 'Distritos', 'psp-territorial' ); ?></option>
			<option value="corregimiento" <?php selected( $type, 'corregimiento' ); ?>><?php esc_html_e( 'Corregimientos', 'psp-territorial' ); ?></option>
			<option value="comunidad"     <?php selected( $type, 'comunidad' ); ?>><?php esc_html_e( 'Comunidades', 'psp-territorial' ); ?></option>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar...', 'psp-territorial' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'psp-territorial' ); ?></button>
		<?php if ( $search || $type ) : ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Limpiar', 'psp-territorial' ); ?></a>
		<?php endif; ?>
	</form>

	<p class="psp-count">
		<?php
		printf(
			/* translators: %d: number of entities */
			esc_html__( 'Total: %d entidades', 'psp-territorial' ),
			(int) $total
		);
		?>
	</p>

	<table class="wp-list-table widefat fixed striped psp-table">
		<thead>
			<tr>
				<th style="width:60px"><?php esc_html_e( 'ID', 'psp-territorial' ); ?></th>
				<th><?php esc_html_e( 'Nombre', 'psp-territorial' ); ?></th>
				<th style="width:140px"><?php esc_html_e( 'Tipo', 'psp-territorial' ); ?></th>
				<th style="width:100px"><?php esc_html_e( 'ID Padre', 'psp-territorial' ); ?></th>
				<th style="width:160px"><?php esc_html_e( 'Acciones', 'psp-territorial' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $items ) ) : ?>
			<tr>
				<td colspan="5">
					<?php esc_html_e( 'No se encontraron entidades.', 'psp-territorial' ); ?>
				</td>
			</tr>
		<?php else : ?>
			<?php foreach ( $items as $item ) : ?>
			<tr>
				<td><?php echo (int) $item->id; ?></td>
				<td>
					<strong>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=psp-territorial-edit&action=edit&id=' . (int) $item->id ) ); ?>">
							<?php echo esc_html( $item->name ); ?>
						</a>
					</strong>
				</td>
				<td>
					<span class="psp-badge psp-badge--<?php echo esc_attr( $item->type ); ?>">
						<?php echo esc_html( ucfirst( $item->type ) ); ?>
					</span>
				</td>
				<td><?php echo $item->parent_id ? (int) $item->parent_id : '—'; ?></td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=psp-territorial-edit&action=edit&id=' . (int) $item->id ) ); ?>" class="button button-small">
						<?php esc_html_e( 'Editar', 'psp-territorial' ); ?>
					</a>
					<a href="<?php echo esc_url(
						wp_nonce_url(
							admin_url( 'admin.php?page=psp-territorial&action=delete&id=' . (int) $item->id ),
							'psp_delete_' . (int) $item->id
						)
					); ?>"
					class="button button-small button-link-delete psp-delete-btn"
					data-name="<?php echo esc_attr( $item->name ); ?>">
						<?php esc_html_e( 'Eliminar', 'psp-territorial' ); ?>
					</a>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav bottom">
		<div class="tablenav-pages">
			<?php
			$page_links = paginate_links(
				[
					'base'      => add_query_arg( 'paged', '%#%', $base_url ),
					'format'    => '',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'total'     => $total_pages,
					'current'   => $paged,
				]
			);
			echo wp_kses_post( $page_links );
			?>
		</div>
	</div>
	<?php endif; ?>
</div>
