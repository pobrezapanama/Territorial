<?php
/**
 * Admin view – Create / Edit a territorial entity.
 *
 * @package PSP_Territorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'new'; // phpcs:ignore WordPress.Security.NonceVerification
$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

$entity    = null;
$is_edit   = ( 'edit' === $action && $id > 0 );
$page_title = $is_edit ? __( 'Editar entidad', 'psp-territorial' ) : __( 'Agregar nueva entidad', 'psp-territorial' );

// Pre-fill form values.
$form = [
	'name'      => '',
	'type'      => 'provincia',
	'parent_id' => '',
];

if ( $is_edit ) {
	$entity = PSP_Database::get_by_id( $id );
	if ( ! $entity ) {
		wp_die( esc_html__( 'Entidad no encontrada.', 'psp-territorial' ) );
	}
	$form['name']      = $entity->name;
	$form['type']      = $entity->type;
	$form['parent_id'] = (string) ( $entity->parent_id ?? '' );
}

// Handle POST.
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['psp_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['psp_nonce'] ) ), 'psp_save_entity' ) ) {
		wp_die( esc_html__( 'Verificación de seguridad fallida.', 'psp-territorial' ) );
	}

	$posted_name      = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$posted_type      = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
	$posted_parent_id = absint( $_POST['parent_id'] ?? 0 );

	$valid_types = [ 'provincia', 'distrito', 'corregimiento', 'comunidad' ];

	$errors = [];
	if ( empty( $posted_name ) ) {
		$errors[] = __( 'El nombre es obligatorio.', 'psp-territorial' );
	}
	if ( ! in_array( $posted_type, $valid_types, true ) ) {
		$errors[] = __( 'Tipo de entidad inválido.', 'psp-territorial' );
	}
	if ( in_array( $posted_type, [ 'distrito', 'corregimiento', 'comunidad' ], true ) && ! $posted_parent_id ) {
		$errors[] = __( 'Debe seleccionar un padre para este tipo de entidad.', 'psp-territorial' );
	}

	/**
	 * Filter the validation of an entity before saving.
	 *
	 * @param array  $errors        Current validation errors.
	 * @param array  $data          Posted form data.
	 * @param bool   $is_edit       Whether this is an edit or create.
	 */
	$errors = apply_filters(
		'psp_territorial_validate_entity',
		$errors,
		[
			'name'      => $posted_name,
			'type'      => $posted_type,
			'parent_id' => $posted_parent_id,
		],
		$is_edit
	);

	if ( empty( $errors ) ) {
		$data = [
			'name'      => $posted_name,
			'type'      => $posted_type,
			'parent_id' => $posted_parent_id ?: null,
		];

		if ( $is_edit ) {
			$ok = PSP_Database::update( $id, $data );
			/**
			 * Fires after a territorial entity is updated via the admin.
			 *
			 * @param int   $id   Entity ID.
			 * @param array $data Updated data.
			 */
			do_action( 'psp_territorial_entity_updated', $id, $data );
			wp_safe_redirect( admin_url( 'admin.php?page=psp-territorial&msg=' . ( $ok ? 'updated' : 'error' ) ) );
			exit;
		} else {
			$new_id = PSP_Database::insert( $data );
			if ( $new_id ) {
				do_action( 'psp_territorial_entity_created', $new_id, $posted_name, $posted_type, $posted_parent_id ?: null );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=psp-territorial&msg=' . ( $new_id ? 'created' : 'error' ) ) );
			exit;
		}
	}
}

// Build parent options per type.
$parent_map = [
	'distrito'      => 'provincia',
	'corregimiento' => 'distrito',
	'comunidad'     => 'corregimiento',
];
?>
<div class="wrap psp-territorial-admin">
	<h1><?php echo esc_html( $page_title ); ?></h1>
	<hr class="wp-header-end">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=psp-territorial' ) ); ?>" class="button">
		&larr; <?php esc_html_e( 'Volver al listado', 'psp-territorial' ); ?>
	</a>
	<br><br>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error">
			<ul>
				<?php foreach ( $errors as $err ) : ?>
					<li><?php echo esc_html( $err ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="" class="psp-entity-form">
		<?php wp_nonce_field( 'psp_save_entity', 'psp_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="name"><?php esc_html_e( 'Nombre', 'psp-territorial' ); ?> *</label></th>
				<td>
					<input type="text" id="name" name="name" class="regular-text"
						value="<?php echo esc_attr( $form['name'] ); ?>" required>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="type"><?php esc_html_e( 'Tipo', 'psp-territorial' ); ?> *</label></th>
				<td>
					<select id="entity-type" name="type" <?php echo $is_edit ? 'disabled' : ''; ?>>
						<option value="provincia"     <?php selected( $form['type'], 'provincia' ); ?>><?php esc_html_e( 'Provincia', 'psp-territorial' ); ?></option>
						<option value="distrito"      <?php selected( $form['type'], 'distrito' ); ?>><?php esc_html_e( 'Distrito', 'psp-territorial' ); ?></option>
						<option value="corregimiento" <?php selected( $form['type'], 'corregimiento' ); ?>><?php esc_html_e( 'Corregimiento', 'psp-territorial' ); ?></option>
						<option value="comunidad"     <?php selected( $form['type'], 'comunidad' ); ?>><?php esc_html_e( 'Comunidad', 'psp-territorial' ); ?></option>
					</select>
					<?php if ( $is_edit ) : ?>
						<input type="hidden" name="type" value="<?php echo esc_attr( $form['type'] ); ?>">
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'No se puede cambiar el tipo después de creado.', 'psp-territorial' ); ?></p>
				</td>
			</tr>
			<tr id="parent-row" <?php echo 'provincia' === $form['type'] ? 'style="display:none"' : ''; ?>>
				<th scope="row"><label for="parent_id"><?php esc_html_e( 'Entidad padre', 'psp-territorial' ); ?></label></th>
				<td>
					<select id="parent-select" name="parent_id">
						<option value=""><?php esc_html_e( '— Seleccionar —', 'psp-territorial' ); ?></option>
						<?php
						$parent_type    = $parent_map[ $form['type'] ] ?? '';
						$parent_options = $parent_type ? PSP_Database::get_by_type( $parent_type ) : [];
						foreach ( $parent_options as $opt ) :
							?>
							<option value="<?php echo (int) $opt->id; ?>"
								<?php selected( (string) $opt->id, $form['parent_id'] ); ?>>
								<?php echo esc_html( $opt->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Actualizar', 'psp-territorial' ) : __( 'Crear', 'psp-territorial' ) ); ?>
	</form>
</div>

<script>
(function(){
	const typeSelect   = document.getElementById('entity-type');
	const parentRow    = document.getElementById('parent-row');
	const parentSelect = document.getElementById('parent-select');
	const parentMap    = <?php echo wp_json_encode( $parent_map ); ?>;

	if ( ! typeSelect ) return;

	typeSelect.addEventListener('change', function(){
		const type = this.value;
		if ( type === 'provincia' ) {
			parentRow.style.display = 'none';
			parentSelect.innerHTML  = '<option value=""><?php esc_html_e( '— Seleccionar —', 'psp-territorial' ); ?></option>';
			return;
		}
		parentRow.style.display = '';
		const parentType = parentMap[ type ] || '';

		fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
			method : 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body   : new URLSearchParams({
				action    : 'psp_get_parents',
				type      : parentType,
				_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'psp_get_parents' ) ); ?>'
			})
		} )
		.then( r => r.json() )
		.then( data => {
			if ( data.success ) {
				let html = '<option value=""><?php echo esc_js( __( '— Seleccionar —', 'psp-territorial' ) ); ?></option>';
				data.data.forEach( item => {
					html += `<option value="${item.id}">${item.name}</option>`;
				} );
				parentSelect.innerHTML = html;
			}
		} );
	} );
}());
</script>
