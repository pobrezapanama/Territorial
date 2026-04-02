<?php
/**
 * Admin view – Plugin settings page.
 *
 * @package PSP_Territorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle import / export actions.
if ( isset( $_POST['psp_action'] ) && check_admin_referer( 'psp_settings_action' ) ) {
	$psp_action = sanitize_text_field( wp_unslash( $_POST['psp_action'] ) );

	if ( 'reimport' === $psp_action ) {
		$importer = new PSP_Importer();
		$stats    = $importer->import( true );
		$notice   = sprintf(
			/* translators: %d: number of imported entities */
			__( 'Reimportación completada. %d entidades importadas.', 'psp-territorial' ),
			(int) $stats['imported']
		);
		$notice_type = 'success';
	} elseif ( 'export' === $psp_action ) {
		$importer = new PSP_Importer();
		$json     = $importer->export_json();

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="psp-territorial-export-' . gmdate( 'Y-m-d' ) . '.json"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $json;
		exit;
	}
}

$total   = PSP_Database::count_all();
$db_ver  = get_option( 'psp_territorial_db_version', '—' );
$imported = get_option( 'psp_territorial_imported', false );
?>
<div class="wrap psp-territorial-admin">
	<h1><?php esc_html_e( 'PSP Territorial – Ajustes', 'psp-territorial' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Stats card -->
	<div class="psp-settings-card">
		<h2><?php esc_html_e( 'Estado actual', 'psp-territorial' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Versión del plugin', 'psp-territorial' ); ?></th>
				<td><?php echo esc_html( PSP_TERRITORIAL_VERSION ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Versión de la BD', 'psp-territorial' ); ?></th>
				<td><?php echo esc_html( $db_ver ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Total de entidades', 'psp-territorial' ); ?></th>
				<td><?php echo (int) $total; ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Provincias', 'psp-territorial' ); ?></th>
				<td><?php echo (int) PSP_Database::count_by_type( 'provincia' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Distritos', 'psp-territorial' ); ?></th>
				<td><?php echo (int) PSP_Database::count_by_type( 'distrito' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Corregimientos', 'psp-territorial' ); ?></th>
				<td><?php echo (int) PSP_Database::count_by_type( 'corregimiento' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Comunidades', 'psp-territorial' ); ?></th>
				<td><?php echo (int) PSP_Database::count_by_type( 'comunidad' ); ?></td>
			</tr>
		</table>
	</div>

	<!-- Actions card -->
	<div class="psp-settings-card">
		<h2><?php esc_html_e( 'Acciones de datos', 'psp-territorial' ); ?></h2>

		<form method="post" action="">
			<?php wp_nonce_field( 'psp_settings_action' ); ?>
			<p>
				<strong><?php esc_html_e( 'Reimportar datos', 'psp-territorial' ); ?></strong><br>
				<span class="description">
					<?php esc_html_e( 'Borra todos los datos actuales y vuelve a importar desde el archivo JSON original. Esta acción no se puede deshacer.', 'psp-territorial' ); ?>
				</span>
			</p>
			<button type="submit" name="psp_action" value="reimport" class="button button-secondary psp-danger-btn"
				onclick="return confirm('<?php esc_attr_e( '¿Está seguro? Se eliminarán todos los datos actuales.', 'psp-territorial' ); ?>')">
				<?php esc_html_e( 'Reimportar datos', 'psp-territorial' ); ?>
			</button>
		</form>

		<hr>

		<form method="post" action="">
			<?php wp_nonce_field( 'psp_settings_action' ); ?>
			<p>
				<strong><?php esc_html_e( 'Exportar datos a JSON', 'psp-territorial' ); ?></strong><br>
				<span class="description">
					<?php esc_html_e( 'Descarga todos los datos territoriales en formato JSON estructurado jerárquicamente.', 'psp-territorial' ); ?>
				</span>
			</p>
			<button type="submit" name="psp_action" value="export" class="button button-primary">
				<?php esc_html_e( 'Exportar JSON', 'psp-territorial' ); ?>
			</button>
		</form>
	</div>

	<!-- REST API card -->
	<div class="psp-settings-card">
		<h2><?php esc_html_e( 'REST API', 'psp-territorial' ); ?></h2>
		<p><?php esc_html_e( 'Endpoints disponibles:', 'psp-territorial' ); ?></p>
		<ul class="psp-api-list">
			<?php
			$endpoints = [
				'/provincias',
				'/provincias/{id}',
				'/distritos',
				'/distritos/{id}',
				'/corregimientos',
				'/corregimientos/{id}',
				'/comunidades',
				'/comunidades/{id}',
				'/jerarquia',
			];
			foreach ( $endpoints as $ep ) :
				$url = rest_url( 'psp-territorial/v1' . $ep );
				?>
				<li>
					<code>GET <a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></code>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<!-- Credits -->
	<div class="psp-settings-card psp-credits">
		<h2><?php esc_html_e( 'Créditos', 'psp-territorial' ); ?></h2>
		<p>
			<strong>PSP Territorial</strong> v<?php echo esc_html( PSP_TERRITORIAL_VERSION ); ?><br>
			<?php esc_html_e( 'División Político Administrativa de la República de Panamá', 'psp-territorial' ); ?><br>
			<?php esc_html_e( 'Desarrollado para PSP – Panamá', 'psp-territorial' ); ?>
		</p>
	</div>
</div>
