<?php
/**
 * Admin View: Territories List – PSP Territorial V2
 *
 * @package PSP_Territorial_V2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = pspv2()->database;

// Query params.
$current_type = isset( $_GET['type'] )      ? sanitize_text_field( wp_unslash( $_GET['type'] ) )      : '';
$search       = isset( $_GET['s'] )         ? sanitize_text_field( wp_unslash( $_GET['s'] ) )         : '';
$paged        = isset( $_GET['paged'] )     ? max( 1, (int) $_GET['paged'] )                          : 1;
$per_page     = 50;
$offset       = ( $paged - 1 ) * $per_page;

$query_args = array(
	'limit'  => $per_page,
	'offset' => $offset,
	'search' => $search,
	'orderby' => 'id',
	'order'  => 'ASC',
);

if ( ! empty( $current_type ) && in_array( $current_type, PSPV2_Database::TYPES, true ) ) {
	$query_args['type'] = $current_type;
}

$items       = $db->get_items( $query_args );
$count_args  = $query_args;
unset( $count_args['limit'], $count_args['offset'] );
$total       = $db->count_filtered( $count_args );
$total_pages = max( 1, ceil( $total / $per_page ) );

// Per-type counts.
$type_counts = array();
foreach ( PSPV2_Database::TYPES as $t ) {
	$type_counts[ $t ] = $db->count_items( array( 'type' => $t ) );
}

$type_labels = array(
	'province'      => 'Provincias',
	'district'      => 'Distritos',
	'corregimiento' => 'Corregimientos',
	'community'     => 'Comunidades',
);

// Pre-fetch ids with invalid parents for flag display.
$invalid_ids = array();
foreach ( $db->get_invalid_parents() as $inv ) {
	$invalid_ids[ (int) $inv->id ] = true;
}
?>
<div class="wrap pspv2-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-location-alt"></span>
		<?php esc_html_e( 'PSP Territorial V2 – Territorios', 'psp-territorial-v2' ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=psp-territorial-import' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Importar / Stats', 'psp-territorial-v2' ); ?>
	</a>
	<hr class="wp-header-end">

	<!-- Stats bar -->
	<div class="pspv2-stats-bar">
		<div class="pspv2-stat-item <?php echo empty( $current_type ) ? 'active' : ''; ?>">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=psp-territorial' ) ); ?>">
				<span class="pspv2-stat-count"><?php echo esc_html( number_format_i18n( array_sum( $type_counts ) ) ); ?></span>
				<span class="pspv2-stat-label"><?php esc_html_e( 'Total', 'psp-territorial-v2' ); ?></span>
			</a>
		</div>
		<?php foreach ( PSPV2_Database::TYPES as $t ) : ?>
		<div class="pspv2-stat-item <?php echo $current_type === $t ? 'active' : ''; ?>">
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'psp-territorial', 'type' => $t ), admin_url( 'admin.php' ) ) ); ?>">
				<span class="pspv2-stat-count"><?php echo esc_html( number_format_i18n( $type_counts[ $t ] ) ); ?></span>
				<span class="pspv2-stat-label"><?php echo esc_html( $type_labels[ $t ] ?? $t ); ?></span>
			</a>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Search form -->
	<form method="get" action="">
		<input type="hidden" name="page" value="psp-territorial">
		<?php if ( $current_type ) : ?>
		<input type="hidden" name="type" value="<?php echo esc_attr( $current_type ); ?>">
		<?php endif; ?>
		<p class="search-box">
			<label for="pspv2-search" class="screen-reader-text"><?php esc_html_e( 'Buscar', 'psp-territorial-v2' ); ?></label>
			<input type="search" id="pspv2-search" name="s" value="<?php echo esc_attr( $search ); ?>"
				placeholder="<?php esc_attr_e( 'Buscar por nombre, slug o código…', 'psp-territorial-v2' ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Buscar', 'psp-territorial-v2' ); ?></button>
			<?php if ( $search ) : ?>
			<a href="<?php echo esc_url( remove_query_arg( 's' ) ); ?>" class="button"><?php esc_html_e( 'Limpiar', 'psp-territorial-v2' ); ?></a>
			<?php endif; ?>
		</p>
	</form>

	<!-- Results info -->
	<p class="pspv2-results-info">
		<?php
		printf(
			/* translators: 1: start, 2: end, 3: total */
			esc_html__( 'Mostrando %1$d–%2$d de %3$d', 'psp-territorial-v2' ),
			$offset + 1,
			min( $offset + $per_page, $total ),
			$total
		);
		?>
	</p>

	<!-- Table -->
	<table class="wp-list-table widefat fixed striped pspv2-table">
		<thead>
			<tr>
				<th class="column-id"><?php esc_html_e( 'ID', 'psp-territorial-v2' ); ?></th>
				<th class="column-name"><?php esc_html_e( 'Nombre', 'psp-territorial-v2' ); ?></th>
				<th class="column-type"><?php esc_html_e( 'Tipo', 'psp-territorial-v2' ); ?></th>
				<th class="column-code"><?php esc_html_e( 'Código', 'psp-territorial-v2' ); ?></th>
				<th class="column-parent"><?php esc_html_e( 'Padre (ID)', 'psp-territorial-v2' ); ?></th>
				<th class="column-level"><?php esc_html_e( 'Nivel', 'psp-territorial-v2' ); ?></th>
				<th class="column-status"><?php esc_html_e( 'Estado', 'psp-territorial-v2' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $items ) ) : ?>
			<tr>
				<td colspan="7">
					<?php esc_html_e( 'No se encontraron territorios. Usa "Importar / Stats" para cargar los datos.', 'psp-territorial-v2' ); ?>
				</td>
			</tr>
		<?php else : ?>
			<?php foreach ( $items as $item ) :
				$has_invalid_parent = isset( $invalid_ids[ (int) $item->id ] );
				$parent_label       = '';
				if ( $item->parent_id ) {
					$parent = $db->get_by_id( (int) $item->parent_id );
					$parent_label = $parent ? esc_html( $parent->name ) . ' (#' . (int) $parent->id . ')' : '#' . (int) $item->parent_id . ' ⚠️';
				}
			?>
			<tr class="<?php echo $has_invalid_parent ? 'pspv2-row-invalid' : ''; ?>">
				<td class="column-id"><code><?php echo (int) $item->id; ?></code></td>
				<td class="column-name">
					<strong><?php echo esc_html( $item->name ); ?></strong>
					<br><small><?php echo esc_html( $item->slug ); ?></small>
					<?php if ( $has_invalid_parent ) : ?>
					<br><span class="pspv2-badge-warning">⚠️ <?php esc_html_e( 'Padre incorrecto', 'psp-territorial-v2' ); ?></span>
					<?php endif; ?>
				</td>
				<td class="column-type">
					<span class="pspv2-type-badge pspv2-type-<?php echo esc_attr( $item->type ); ?>">
						<?php echo esc_html( $type_labels[ $item->type ] ?? $item->type ); ?>
					</span>
				</td>
				<td class="column-code"><code><?php echo esc_html( $item->code ); ?></code></td>
				<td class="column-parent">
					<?php echo $parent_label ? wp_kses_post( $parent_label ) : '<em>—</em>'; ?>
				</td>
				<td class="column-level"><?php echo (int) $item->level; ?></td>
				<td class="column-status">
					<?php if ( $item->is_active ) : ?>
					<span class="pspv2-badge-active">✅ <?php esc_html_e( 'Activo', 'psp-territorial-v2' ); ?></span>
					<?php else : ?>
					<span class="pspv2-badge-inactive">❌ <?php esc_html_e( 'Inactivo', 'psp-territorial-v2' ); ?></span>
					<?php endif; ?>
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
			$pagination_args = array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'total'     => $total_pages,
				'current'   => $paged,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			);
			echo wp_kses_post( paginate_links( $pagination_args ) );
			?>
		</div>
	</div>
	<?php endif; ?>
</div>
