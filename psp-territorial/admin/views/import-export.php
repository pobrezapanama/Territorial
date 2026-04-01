<?php
/**
 * Admin View: Import / Export
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$import_msg = isset( $_GET['import_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['import_msg'] ) ) : '';
$success    = isset( $_GET['success'] ) ? ( '1' === $_GET['success'] ) : null;

$db = psp_territorial()->database;

// Count current data.
$counts = array();
foreach ( PSP_Territorial_Utils::$types as $t ) {
	$counts[ $t ] = $db->count_territories( array( 'type' => $t ) );
}
$total = array_sum( $counts );
?>
<div class="wrap psp-territorial-wrap">
	<h1>
		<span class="dashicons dashicons-upload"></span>
		<?php esc_html_e( 'Importar / Exportar', 'psp-territorial' ); ?>
	</h1>
	<hr class="wp-header-end">

	<?php if ( $import_msg ) : ?>
		<div class="notice notice-<?php echo $success ? 'success' : 'error'; ?> is-dismissible">
			<p><?php echo esc_html( urldecode( $import_msg ) ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Current data summary -->
	<div class="psp-card">
		<h2><?php esc_html_e( 'Estado Actual de los Datos', 'psp-territorial' ); ?></h2>
		<table class="widefat fixed striped" style="max-width:500px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Tipo', 'psp-territorial' ); ?></th>
					<th><?php esc_html_e( 'Registros', 'psp-territorial' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $counts as $type => $count ) : ?>
					<tr>
						<td><span class="psp-type-badge psp-type-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( PSP_Territorial_Utils::get_type_label( $type ) ); ?></span></td>
						<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<td><strong><?php esc_html_e( 'Total', 'psp-territorial' ); ?></strong></td>
					<td><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="psp-two-columns">

		<!-- IMPORT -->
		<div class="psp-card">
			<h2><?php esc_html_e( 'Importar Datos', 'psp-territorial' ); ?></h2>
			<p><?php esc_html_e( 'Importa los datos territoriales de Panamá desde los archivos incluidos en el plugin o sube tu propio archivo CSV.', 'psp-territorial' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="psp-import-form">
				<input type="hidden" name="action" value="psp_territorial_import">
				<?php wp_nonce_field( 'psp_territorial_import', 'psp_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Fuente', 'psp-territorial' ); ?></label></th>
						<td>
							<label>
								<input type="radio" name="source" value="json" checked> 
								<?php esc_html_e( 'JSON incluido (recomendado)', 'psp-territorial' ); ?>
							</label><br>
							<label>
								<input type="radio" name="source" value="csv">
								<?php esc_html_e( 'CSV incluido', 'psp-territorial' ); ?>
							</label><br>
							<label>
								<input type="radio" name="source" value="csv" id="source-upload">
								<?php esc_html_e( 'Subir archivo CSV', 'psp-territorial' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="csv_file"><?php esc_html_e( 'Archivo CSV', 'psp-territorial' ); ?></label></th>
						<td>
							<input type="file" id="csv_file" name="csv_file" accept=".csv">
							<p class="description"><?php esc_html_e( 'Solo necesario si seleccionas "Subir archivo CSV".', 'psp-territorial' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Opciones', 'psp-territorial' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="truncate" value="1">
								<strong><?php esc_html_e( 'Eliminar datos existentes antes de importar', 'psp-territorial' ); ?></strong>
							</label>
							<p class="description" style="color:#c0392b">
								<?php esc_html_e( '⚠️ Esta opción borrará TODOS los territorios actuales. Úsala solo si quieres reimportar desde cero.', 'psp-territorial' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( '⬆ Importar Datos', 'psp-territorial' ); ?>">
				</p>
			</form>
		</div>

		<!-- EXPORT -->
		<div class="psp-card">
			<h2><?php esc_html_e( 'Exportar Datos', 'psp-territorial' ); ?></h2>
			<p><?php esc_html_e( 'Descarga los datos territoriales en el formato que prefieras.', 'psp-territorial' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="psp_territorial_export">
				<?php wp_nonce_field( 'psp_territorial_export', 'psp_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="export-type"><?php esc_html_e( 'Tipo', 'psp-territorial' ); ?></label></th>
						<td>
							<select id="export-type" name="type" class="regular-text">
								<option value=""><?php esc_html_e( 'Todos los tipos', 'psp-territorial' ); ?></option>
								<?php foreach ( PSP_Territorial_Utils::$types as $t ) : ?>
									<option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( PSP_Territorial_Utils::get_type_label( $t ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Formato', 'psp-territorial' ); ?></label></th>
						<td>
							<label><input type="radio" name="format" value="json" checked> JSON</label>&nbsp;&nbsp;
							<label><input type="radio" name="format" value="csv"> CSV</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" class="button button-secondary" value="<?php esc_attr_e( '⬇ Exportar Datos', 'psp-territorial' ); ?>">
				</p>
			</form>
		</div>

	</div><!-- .psp-two-columns -->
</div>
