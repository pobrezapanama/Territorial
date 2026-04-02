<?php
/**
 * Admin View: Import / Stats – PSP Territorial V2
 *
 * @package PSP_Territorial_V2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db  = pspv2()->database;
$msg = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';

$stats      = $db->table_exists() ? $db->get_stats() : array();
$total      = array_sum( $stats );
$orphans    = $db->table_exists() ? $db->get_orphans()         : array();
$invalid    = $db->table_exists() ? $db->get_invalid_parents() : array();

$type_labels = array(
	'province'      => 'Provincias',
	'district'      => 'Distritos',
	'corregimiento' => 'Corregimientos',
	'community'     => 'Comunidades',
);
?>
<div class="wrap pspv2-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-database-import"></span>
		<?php esc_html_e( 'PSP Territorial V2 – Importar / Estadísticas', 'psp-territorial-v2' ); ?>
	</h1>
	<hr class="wp-header-end">

	<?php if ( 'imported' === $msg ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( '✅ Importación completada correctamente.', 'psp-territorial-v2' ); ?></p>
	</div>
	<?php elseif ( 'error' === $msg ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><?php esc_html_e( '❌ Hubo un error durante la importación.', 'psp-territorial-v2' ); ?></p>
	</div>
	<?php endif; ?>

	<div class="pspv2-two-col">

		<!-- Import Box -->
		<div class="pspv2-card">
			<h2><?php esc_html_e( 'Importar datos territoriales', 'psp-territorial-v2' ); ?></h2>
			<p>
				<?php esc_html_e( 'Fuente:', 'psp-territorial-v2' ); ?>
				<code>assets/data/panama_full_geography.clean.json</code>
				(<?php echo number_format_i18n( 12967 ); ?> registros)
			</p>

			<div class="pspv2-import-options">
				<label>
					<input type="checkbox" id="pspv2-truncate-check">
					<strong><?php esc_html_e( 'Truncate + Reimport', 'psp-territorial-v2' ); ?></strong>
					— <?php esc_html_e( 'Borra todos los datos existentes y reimporta desde cero. Recomendado para corregir padres incorrectos.', 'psp-territorial-v2' ); ?>
				</label>
			</div>

			<br>
			<button id="pspv2-import-btn" class="button button-primary button-hero">
				<?php esc_html_e( '▶ Iniciar Importación', 'psp-territorial-v2' ); ?>
			</button>

			<div id="pspv2-import-progress" style="display:none; margin-top:1em;">
				<span class="spinner is-active" style="float:none;"></span>
				<span id="pspv2-import-status"><?php esc_html_e( 'Importando…', 'psp-territorial-v2' ); ?></span>
			</div>

			<div id="pspv2-import-result" style="display:none; margin-top:1em;" class="notice"></div>

			<hr>

			<!-- Fallback form -->
			<details>
				<summary><?php esc_html_e( 'Importar sin JavaScript (form POST)', 'psp-territorial-v2' ); ?></summary>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em;">
					<input type="hidden" name="action" value="pspv2_import">
					<?php wp_nonce_field( 'pspv2_import_action', 'pspv2_import_nonce' ); ?>
					<label>
						<input type="checkbox" name="truncate" value="1">
						<?php esc_html_e( 'Truncate antes de importar', 'psp-territorial-v2' ); ?>
					</label>
					<br><br>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Importar', 'psp-territorial-v2' ); ?></button>
				</form>
			</details>

			<hr>

			<!-- Export -->
			<h3><?php esc_html_e( 'Exportar JSON', 'psp-territorial-v2' ); ?></h3>
			<p><?php esc_html_e( 'Descarga todos los datos actualmente en la base de datos.', 'psp-territorial-v2' ); ?></p>
			<button id="pspv2-export-btn" class="button">
				<?php esc_html_e( '⬇ Exportar JSON', 'psp-territorial-v2' ); ?>
			</button>
		</div>

		<!-- Stats Box -->
		<div class="pspv2-card">
			<h2><?php esc_html_e( 'Estadísticas actuales', 'psp-territorial-v2' ); ?></h2>

			<?php if ( ! $db->table_exists() ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'La tabla no existe aún. Activa el plugin para crearla.', 'psp-territorial-v2' ); ?></p>
			</div>
			<?php else : ?>

			<table class="widefat pspv2-stats-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Tipo', 'psp-territorial-v2' ); ?></th>
						<th><?php esc_html_e( 'Cantidad', 'psp-territorial-v2' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $type_labels as $type => $label ) : ?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><strong><?php echo number_format_i18n( $stats[ $type ] ?? 0 ); ?></strong></td>
					</tr>
					<?php endforeach; ?>
					<tr class="pspv2-total-row">
						<td><strong><?php esc_html_e( 'TOTAL', 'psp-territorial-v2' ); ?></strong></td>
						<td><strong><?php echo number_format_i18n( $total ); ?></strong></td>
					</tr>
				</tbody>
			</table>

			<!-- Integrity -->
			<h3 style="margin-top:1.5em;"><?php esc_html_e( 'Integridad de jerarquía', 'psp-territorial-v2' ); ?></h3>

			<?php if ( empty( $orphans ) && empty( $invalid ) ) : ?>
			<div class="notice notice-success inline">
				<p>✅ <?php esc_html_e( 'Jerarquía intacta — sin padres rotos.', 'psp-territorial-v2' ); ?></p>
			</div>
			<?php else : ?>
				<?php if ( ! empty( $orphans ) ) : ?>
				<div class="notice notice-error inline">
					<p>⚠️ <?php printf(
						/* translators: count */
						esc_html__( '%d elemento(s) huérfanos (parent_id apunta a un id inexistente).', 'psp-territorial-v2' ),
						count( $orphans )
					); ?></p>
				</div>
				<?php endif; ?>
				<?php if ( ! empty( $invalid ) ) : ?>
				<div class="notice notice-warning inline">
					<p>⚠️ <?php printf(
						/* translators: count */
						esc_html__( '%d elemento(s) con tipo de padre incorrecto.', 'psp-territorial-v2' ),
						count( $invalid )
					); ?></p>
				</div>
				<?php endif; ?>
				<p><em><?php esc_html_e( 'Usa "Truncate + Reimport" para limpiar los datos corruptos.', 'psp-territorial-v2' ); ?></em></p>
			<?php endif; ?>

			<!-- API reference -->
			<h3 style="margin-top:1.5em;"><?php esc_html_e( 'REST API endpoints', 'psp-territorial-v2' ); ?></h3>
			<ul class="pspv2-api-list">
				<?php
				$base = rest_url( 'psp-territorial/v2' );
				$endpoints = array(
					'/provincias'              => 'Listado de provincias',
					'/distritos?parent_id=1'   => 'Distritos de una provincia',
					'/corregimientos?parent_id=1001' => 'Corregimientos de un distrito',
					'/comunidades?parent_id=10001'   => 'Comunidades de un corregimiento',
					'/search?q=bocas&type=province'  => 'Búsqueda por nombre',
					'/path/100317'             => 'Ruta jerárquica de un item',
					'/item/100317'             => 'Detalle de un item',
				);
				foreach ( $endpoints as $ep => $desc ) :
					$url = $base . $ep;
				?>
				<li>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
						<code><?php echo esc_html( $ep ); ?></code>
					</a>
					— <?php echo esc_html( $desc ); ?>
				</li>
				<?php endforeach; ?>
			</ul>

			<!-- WP-CLI reference -->
			<h3 style="margin-top:1.5em;"><?php esc_html_e( 'WP-CLI', 'psp-territorial-v2' ); ?></h3>
			<pre class="pspv2-cli-box">wp psp territorial-v2 import --truncate
wp psp territorial-v2 verify
wp psp territorial-v2 stats</pre>

			<?php endif; ?>
		</div>

	</div><!-- /.pspv2-two-col -->
</div>
