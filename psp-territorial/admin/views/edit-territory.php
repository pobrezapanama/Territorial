<?php
/**
 * Admin View: Edit/Add Territory
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = psp_territorial()->database;

$action      = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'add';
$territory_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$territory   = $territory_id ? $db->get_by_id( $territory_id ) : null;
$error       = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';

$is_edit = 'edit' === $action && $territory;
$title   = $is_edit ? __( 'Editar Territorio', 'psp-territorial' ) : __( 'Agregar Territorio', 'psp-territorial' );

// Pre-fill values.
$name      = $is_edit ? $territory->name : '';
$slug      = $is_edit ? $territory->slug : '';
$code      = $is_edit ? $territory->code : '';
$type      = $is_edit ? $territory->type : ( isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : 'province' );
$parent_id = $is_edit ? $territory->parent_id : ( isset( $_GET['parent_id'] ) ? absint( $_GET['parent_id'] ) : '' );
$is_active = $is_edit ? (bool) $territory->is_active : true;

// Get parent entity if applicable.
$parent_entity = $parent_id ? $db->get_by_id( $parent_id ) : null;
?>
<div class="wrap psp-territorial-wrap">
	<h1>
		<span class="dashicons dashicons-location-alt"></span>
		<?php echo esc_html( $title ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=psp-territorial' ) ); ?>" class="page-title-action">
		&larr; <?php esc_html_e( 'Volver a la lista', 'psp-territorial' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( $error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( urldecode( $error ) ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="psp-edit-form">
		<input type="hidden" name="action" value="psp_territorial_save">
		<input type="hidden" name="territory_id" value="<?php echo esc_attr( $territory_id ); ?>">
		<?php wp_nonce_field( 'psp_territorial_save', 'psp_nonce' ); ?>

		<div class="psp-form-layout">
			<div class="psp-form-main">
				<div class="postbox">
					<div class="postbox-header">
						<h2><?php esc_html_e( 'Información del Territorio', 'psp-territorial' ); ?></h2>
					</div>
					<div class="inside">
						<table class="form-table">
							<tr>
								<th><label for="name"><?php esc_html_e( 'Nombre *', 'psp-territorial' ); ?></label></th>
								<td>
									<input type="text" id="name" name="name" value="<?php echo esc_attr( $name ); ?>"
										class="regular-text" required placeholder="<?php esc_attr_e( 'Nombre del territorio', 'psp-territorial' ); ?>">
								</td>
							</tr>
							<tr>
								<th><label for="slug"><?php esc_html_e( 'Slug', 'psp-territorial' ); ?></label></th>
								<td>
									<input type="text" id="slug" name="slug" value="<?php echo esc_attr( $slug ); ?>"
										class="regular-text" placeholder="<?php esc_attr_e( 'Se genera automáticamente', 'psp-territorial' ); ?>">
									<p class="description"><?php esc_html_e( 'Identificador único URL-friendly. Se genera automáticamente si se deja vacío.', 'psp-territorial' ); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="code"><?php esc_html_e( 'Código', 'psp-territorial' ); ?></label></th>
								<td>
									<input type="text" id="code" name="code" value="<?php echo esc_attr( $code ); ?>"
										class="regular-text" placeholder="Ej: PRV-0001">
								</td>
							</tr>
							<tr>
								<th><label for="type"><?php esc_html_e( 'Tipo *', 'psp-territorial' ); ?></label></th>
								<td>
									<select id="type" name="type" required class="regular-text" data-psp-type-selector>
										<?php foreach ( PSP_Territorial_Utils::$types as $t ) : ?>
											<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $type, $t ); ?>>
												<?php echo esc_html( PSP_Territorial_Utils::get_type_label( $t ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr id="row-parent-id" <?php echo 'province' === $type ? 'style="display:none"' : ''; ?>>
								<th><label for="parent_id"><?php esc_html_e( 'Padre', 'psp-territorial' ); ?></label></th>
								<td>
									<select id="parent_id" name="parent_id" class="regular-text" data-psp-parent-select>
										<option value=""><?php esc_html_e( '— Seleccionar padre —', 'psp-territorial' ); ?></option>
										<?php
										// Show parents of the appropriate type.
										$parent_type = PSP_Territorial_Utils::get_parent_type( $type );
										if ( $parent_type ) {
											$parents = $db->get_by_type( $parent_type, array( 'limit' => 2000 ) );
											foreach ( $parents as $p ) :
												?>
												<option value="<?php echo esc_attr( $p->id ); ?>" <?php selected( $parent_id, $p->id ); ?>>
													<?php echo esc_html( $p->name ); ?>
												</option>
											<?php endforeach;
										}
										?>
									</select>
									<?php if ( $parent_entity ) : ?>
										<p class="description">
											<?php
											printf(
												/* translators: 1: Type label, 2: Parent name */
												esc_html__( 'Padre actual: %1$s – %2$s', 'psp-territorial' ),
												esc_html( PSP_Territorial_Utils::get_type_label( $parent_entity->type ) ),
												esc_html( $parent_entity->name )
											);
											?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th><label for="is_active"><?php esc_html_e( 'Estado', 'psp-territorial' ); ?></label></th>
								<td>
									<label>
										<input type="checkbox" id="is_active" name="is_active" value="1" <?php checked( $is_active ); ?>>
										<?php esc_html_e( 'Activo', 'psp-territorial' ); ?>
									</label>
								</td>
							</tr>
						</table>
					</div>
				</div><!-- .postbox -->
			</div><!-- .psp-form-main -->

			<div class="psp-form-sidebar">
				<div class="postbox">
					<div class="postbox-header">
						<h2><?php esc_html_e( 'Guardar', 'psp-territorial' ); ?></h2>
					</div>
					<div class="inside">
						<div class="submitbox">
							<div id="publishing-action">
								<input type="submit" class="button button-primary button-large" value="<?php echo $is_edit ? esc_attr__( 'Actualizar Territorio', 'psp-territorial' ) : esc_attr__( 'Crear Territorio', 'psp-territorial' ); ?>">
							</div>
							<?php if ( $is_edit ) : ?>
							<div id="delete-action">
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'psp_territorial_delete', 'id' => $territory_id ), admin_url( 'admin-post.php' ) ), 'psp_territorial_delete_' . $territory_id ) ); ?>"
									class="submitdelete psp-confirm-delete"
									data-children="<?php echo esc_attr( $db->count_children( $territory_id ) ); ?>">
									<?php esc_html_e( 'Eliminar Territorio', 'psp-territorial' ); ?>
								</a>
							</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<?php if ( $is_edit ) :
					$path = $db->get_path( $territory_id );
				?>
				<div class="postbox">
					<div class="postbox-header">
						<h2><?php esc_html_e( 'Ruta Jerárquica', 'psp-territorial' ); ?></h2>
					</div>
					<div class="inside">
						<ol class="psp-breadcrumb">
							<?php foreach ( $path as $step ) : ?>
								<li>
									<span class="psp-type-badge psp-type-<?php echo esc_attr( $step->type ); ?>">
										<?php echo esc_html( PSP_Territorial_Utils::get_type_label( $step->type ) ); ?>
									</span>
									<?php echo esc_html( $step->name ); ?>
								</li>
							<?php endforeach; ?>
						</ol>
					</div>
				</div>
				<?php endif; ?>
			</div><!-- .psp-form-sidebar -->
		</div><!-- .psp-form-layout -->
	</form>
</div>
