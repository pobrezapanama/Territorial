<?php
/**
 * Admin View: Settings
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Save settings.
if ( isset( $_POST['psp_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['psp_nonce'] ) ), 'psp_territorial_settings' ) ) {
	update_option( 'psp_territorial_cache_ttl', absint( $_POST['cache_ttl'] ?? 3600 ) );
	update_option( 'psp_territorial_per_page', absint( $_POST['per_page'] ?? 50 ) );
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '✅ Configuración guardada.', 'psp-territorial' ) . '</p></div>';
}

$cache_ttl = get_option( 'psp_territorial_cache_ttl', 3600 );
$per_page  = get_option( 'psp_territorial_per_page', 50 );
$db        = psp_territorial()->database;
?>
<div class="wrap psp-territorial-wrap">
	<h1>
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Configuración – PSP Territorial', 'psp-territorial' ); ?>
	</h1>
	<hr class="wp-header-end">

	<form method="post">
		<?php wp_nonce_field( 'psp_territorial_settings', 'psp_nonce' ); ?>
		<table class="form-table">
			<tr>
				<th><label for="cache_ttl"><?php esc_html_e( 'Duración de caché (segundos)', 'psp-territorial' ); ?></label></th>
				<td>
					<input type="number" id="cache_ttl" name="cache_ttl" value="<?php echo esc_attr( $cache_ttl ); ?>" min="0" class="small-text">
					<p class="description"><?php esc_html_e( 'Tiempo en segundos para cachear los resultados de la API. 0 = sin caché.', 'psp-territorial' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="per_page"><?php esc_html_e( 'Elementos por página', 'psp-territorial' ); ?></label></th>
				<td>
					<input type="number" id="per_page" name="per_page" value="<?php echo esc_attr( $per_page ); ?>" min="10" max="200" class="small-text">
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Guardar Configuración', 'psp-territorial' ); ?>">
		</p>
	</form>

	<hr>
	<h2><?php esc_html_e( 'Información del Plugin', 'psp-territorial' ); ?></h2>
	<table class="widefat fixed striped" style="max-width:500px">
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Versión', 'psp-territorial' ); ?></td>
				<td><?php echo esc_html( PSP_TERRITORIAL_VERSION ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Tabla principal', 'psp-territorial' ); ?></td>
				<td><?php echo esc_html( $db->get_table_name() ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Tabla de meta', 'psp-territorial' ); ?></td>
				<td><?php echo esc_html( $db->get_meta_table_name() ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Namespace REST API', 'psp-territorial' ); ?></td>
				<td><code><?php echo esc_html( rest_url( 'psp/v1' ) ); ?></code></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Versión requerida WP', 'psp-territorial' ); ?></td>
				<td>5.9+</td>
			</tr>
		</tbody>
	</table>

	<hr>
	<h2><?php esc_html_e( 'Herramientas', 'psp-territorial' ); ?></h2>
	<p>
		<a href="<?php echo esc_url( add_query_arg( 'psp_flush_cache', '1', admin_url( 'admin.php?page=psp-territorial-settings' ) ) ); ?>" class="button">
			<?php esc_html_e( '🗑 Limpiar Caché', 'psp-territorial' ); ?>
		</a>
	</p>
	<?php
	if ( isset( $_GET['psp_flush_cache'] ) && '1' === $_GET['psp_flush_cache'] ) {
		PSP_Territorial_Utils::clear_cache();
		echo '<p class="notice notice-success inline"><strong>' . esc_html__( '✅ Caché limpiado correctamente.', 'psp-territorial' ) . '</strong></p>';
	}
	?>
</div>
