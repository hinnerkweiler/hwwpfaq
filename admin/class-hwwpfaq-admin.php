<?php
/**
 * Admin UI for the HW WP FAQ plugin.
 *
 * Provides:
 *   - A "FAQ" entry under Tools in the WP admin menu.
 *   - A paginated list of all FAQ entries with Edit / Activate-Deactivate / Delete actions.
 *   - An Add / Edit form with all fields including scheduled publication.
 *   - Form submission handling via admin-post.php (hwwpfaq_save action).
 *   - Inline nonce-protected GET actions for delete and toggle.
 */

defined( 'ABSPATH' ) || exit;

class HWWPFAQ_Admin {

	/** Rows displayed per page in the list view. */
	const PER_PAGE = 20;

	public function __construct() {
		add_action( 'admin_menu',              array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts',   array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_hwwpfaq_save', array( $this, 'handle_save' ) );
	}

	// =========================================================================
	// Menu & asset registration
	// =========================================================================

	public function register_menu() {
		add_management_page(
			__( 'FAQ Manager', 'hwwpfaq' ),
			__( 'FAQ', 'hwwpfaq' ),
			'manage_options',
			'hwwpfaq',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		// 'tools_page_hwwpfaq' is the hook suffix for a Tools sub-page named 'hwwpfaq'.
		if ( 'tools_page_hwwpfaq' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'hwwpfaq-admin',
			HWWPFAQ_URL . 'admin/admin.css',
			array(),
			HWWPFAQ_VERSION
		);
	}

	// =========================================================================
	// Page router
	// =========================================================================

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'hwwpfaq' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		switch ( $action ) {
			case 'delete':
				$this->handle_delete( $id );
				return;
			case 'toggle':
				$this->handle_toggle( $id );
				return;
			case 'add':
				$this->render_form( 0 );
				return;
			case 'edit':
				$this->render_form( $id );
				return;
			default:
				$this->render_list();
		}
	}

	// =========================================================================
	// Action handlers (delete / toggle / save)
	// =========================================================================

	/**
	 * Deletes a FAQ entry after nonce verification.
	 * Called from the list-page action link; redirects back on completion.
	 *
	 * @param int $id Row ID to delete.
	 */
	private function handle_delete( $id ) {
		check_admin_referer( 'hwwpfaq_delete_' . $id );

		HWWPFAQ_DB::delete( $id );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'hwwpfaq', 'hwwpfaq_msg' => 'deleted' ),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Toggles is_active for a FAQ entry after nonce verification.
	 * Redirects back to the list on completion.
	 *
	 * @param int $id Row ID to toggle.
	 */
	private function handle_toggle( $id ) {
		check_admin_referer( 'hwwpfaq_toggle_' . $id );

		$item = HWWPFAQ_DB::get_item( $id );
		if ( $item ) {
			HWWPFAQ_DB::update(
				array( 'is_active' => $item->is_active ? 0 : 1, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $id )
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'hwwpfaq', 'hwwpfaq_msg' => 'toggled' ),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Handles the Add / Edit form POST (routed via admin-post.php).
	 * Validates capability + nonce, sanitizes all inputs, persists to DB,
	 * then redirects back to the list.
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'hwwpfaq' ) );
		}

		check_admin_referer( 'hwwpfaq_save' );

		$id        = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$category  = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$question  = sanitize_textarea_field( wp_unslash( $_POST['question'] ?? '' ) );
		$answer    = wp_kses_post( wp_unslash( $_POST['answer'] ?? '' ) );
		$pub_date  = sanitize_text_field( wp_unslash( $_POST['pub_date'] ?? '' ) );
		$author_id = absint( $_POST['author_id'] ?? get_current_user_id() );
		$is_active = isset( $_POST['is_active'] ) ? 1 : 0;

		// Normalise datetime-local value (format: "Y-m-d\TH:i") to MySQL datetime.
		if ( $pub_date ) {
			$timestamp = strtotime( $pub_date );
			$pub_date  = $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : current_time( 'mysql' );
		} else {
			$pub_date = current_time( 'mysql' );
		}

		$now  = current_time( 'mysql' );
		$data = array(
			'category'   => $category,
			'question'   => $question,
			'answer'     => $answer,
			'pub_date'   => $pub_date,
			'author_id'  => $author_id,
			'is_active'  => $is_active,
			'updated_at' => $now,
		);

		if ( $id ) {
			HWWPFAQ_DB::update( $data, array( 'id' => $id ) );
			$msg = 'updated';
		} else {
			$data['created_at'] = $now;
			HWWPFAQ_DB::insert( $data );
			$msg = 'created';
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'hwwpfaq', 'hwwpfaq_msg' => $msg ),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	// =========================================================================
	// List view
	// =========================================================================

	private function render_list() {
		$paged       = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$offset      = ( $paged - 1 ) * self::PER_PAGE;
		$total       = HWWPFAQ_DB::count_items();
		$items       = HWWPFAQ_DB::get_items( array(
			'limit'   => self::PER_PAGE,
			'offset'  => $offset,
			'orderby' => 'id',
			'order'   => 'DESC',
		) );
		$total_pages = (int) ceil( $total / self::PER_PAGE );
		$add_url     = admin_url( 'tools.php?page=hwwpfaq&action=add' );
		?>
		<div class="wrap hwwpfaq-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'FAQ Manager', 'hwwpfaq' ); ?></h1>
			<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'hwwpfaq' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php $this->display_notices(); ?>

			<table class="wp-list-table widefat fixed striped hwwpfaq-table">
				<thead>
					<tr>
						<th class="column-id"><?php esc_html_e( 'ID', 'hwwpfaq' ); ?></th>
						<th class="column-category"><?php esc_html_e( 'Category', 'hwwpfaq' ); ?></th>
						<th class="column-question"><?php esc_html_e( 'Question', 'hwwpfaq' ); ?></th>
						<th class="column-author"><?php esc_html_e( 'Author', 'hwwpfaq' ); ?></th>
						<th class="column-pubdate"><?php esc_html_e( 'Publication Date', 'hwwpfaq' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'hwwpfaq' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'hwwpfaq' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr>
						<td colspan="7"><?php esc_html_e( 'No FAQ entries found. Add one above.', 'hwwpfaq' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $items as $item ) :
						$edit_url   = admin_url( 'tools.php?page=hwwpfaq&action=edit&id=' . $item->id );
						$delete_url = wp_nonce_url(
							admin_url( 'tools.php?page=hwwpfaq&action=delete&id=' . $item->id ),
							'hwwpfaq_delete_' . $item->id
						);
						$toggle_url = wp_nonce_url(
							admin_url( 'tools.php?page=hwwpfaq&action=toggle&id=' . $item->id ),
							'hwwpfaq_toggle_' . $item->id
						);
						$author       = get_userdata( (int) $item->author_id );
						$author_name  = $author ? $author->display_name : '—';
						$toggle_label = $item->is_active
							? __( 'Deactivate', 'hwwpfaq' )
							: __( 'Activate', 'hwwpfaq' );
					?>
					<tr>
						<td><?php echo absint( $item->id ); ?></td>
						<td><?php echo esc_html( $item->category ?: '—' ); ?></td>
						<td>
							<strong>
								<a href="<?php echo esc_url( $edit_url ); ?>">
									<?php echo esc_html( wp_trim_words( $item->question, 12, '…' ) ); ?>
								</a>
							</strong>
						</td>
						<td><?php echo esc_html( $author_name ); ?></td>
						<td><?php echo esc_html( $item->pub_date ); ?></td>
						<td>
							<?php if ( $item->is_active ) : ?>
								<span class="hwwpfaq-status hwwpfaq-status--active">
									<?php esc_html_e( 'Active', 'hwwpfaq' ); ?>
								</span>
							<?php else : ?>
								<span class="hwwpfaq-status hwwpfaq-status--inactive">
									<?php esc_html_e( 'Inactive', 'hwwpfaq' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td class="hwwpfaq-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'hwwpfaq' ); ?></a>
							&nbsp;|&nbsp;
							<a href="<?php echo esc_url( $toggle_url ); ?>"><?php echo esc_html( $toggle_label ); ?></a>
							&nbsp;|&nbsp;
							<a href="<?php echo esc_url( $delete_url ); ?>"
							   class="hwwpfaq-delete-link"
							   onclick="return confirm( <?php echo wp_json_encode( __( 'Permanently delete this FAQ entry?', 'hwwpfaq' ) ); ?> )">
								<?php esc_html_e( 'Delete', 'hwwpfaq' ); ?>
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php echo paginate_links( array(  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => $total_pages,
						'current'   => $paged,
					) ); ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// =========================================================================
	// Add / Edit form
	// =========================================================================

	/**
	 * Renders the add/edit form.
	 *
	 * @param int $id Row ID to edit; 0 for a new entry.
	 */
	private function render_form( $id ) {
		$item = $id ? HWWPFAQ_DB::get_item( $id ) : null;

		// Populate field values – use existing item data or safe defaults.
		$category  = $item ? $item->category : '';
		$question  = $item ? $item->question : '';
		$answer    = $item ? $item->answer   : '';
		$author_id = $item ? (int) $item->author_id : get_current_user_id();
		$is_active = $item ? (bool) $item->is_active : true;

		// Convert stored MySQL datetime → HTML datetime-local value (Y-m-d\TH:i).
		if ( $item && '2000-01-01 00:00:00' !== $item->pub_date ) {
			$pub_date = gmdate( 'Y-m-d\TH:i', strtotime( $item->pub_date ) );
		} else {
			$pub_date = gmdate( 'Y-m-d\TH:i' );
		}

		$users       = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );
		$page_title  = $id ? __( 'Edit FAQ Entry', 'hwwpfaq' ) : __( 'Add FAQ Entry', 'hwwpfaq' );
		$submit_text = $id ? __( 'Update Entry', 'hwwpfaq' ) : __( 'Add Entry', 'hwwpfaq' );
		?>
		<div class="wrap hwwpfaq-wrap">
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=hwwpfaq' ) ); ?>" class="page-title-action">
				&larr; <?php esc_html_e( 'Back to List', 'hwwpfaq' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hwwpfaq_save' ); ?>
				<input type="hidden" name="action" value="hwwpfaq_save">
				<?php if ( $id ) : ?>
					<input type="hidden" name="id" value="<?php echo absint( $id ); ?>">
				<?php endif; ?>

				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">
							<label for="hwwpfaq_category"><?php esc_html_e( 'Category', 'hwwpfaq' ); ?></label>
						</th>
						<td>
							<input type="text"
							       id="hwwpfaq_category"
							       name="category"
							       value="<?php echo esc_attr( $category ); ?>"
							       class="regular-text"
							       maxlength="100">
							<p class="description">
								<?php esc_html_e( 'Short label used to group FAQs (e.g. "Shipping", "Returns"). Leave blank for uncategorised.', 'hwwpfaq' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="hwwpfaq_question">
								<?php esc_html_e( 'Question', 'hwwpfaq' ); ?>
								<span class="required" aria-hidden="true">*</span>
							</label>
						</th>
						<td>
							<textarea id="hwwpfaq_question"
							          name="question"
							          rows="4"
							          class="large-text"
							          required><?php echo esc_textarea( $question ); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="hwwpfaq_answer">
								<?php esc_html_e( 'Answer', 'hwwpfaq' ); ?>
								<span class="required" aria-hidden="true">*</span>
							</label>
						</th>
						<td>
							<?php
							// Note: wp_editor() ID must be lowercase alphanumeric + underscores only.
							wp_editor(
								$answer,
								'hwwpfaqanswer',
								array(
									'textarea_name' => 'answer',
									'media_buttons' => true,
									'teeny'         => false,
									'textarea_rows' => 10,
								)
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="hwwpfaq_pub_date"><?php esc_html_e( 'Publication Date', 'hwwpfaq' ); ?></label>
						</th>
						<td>
							<input type="datetime-local"
							       id="hwwpfaq_pub_date"
							       name="pub_date"
							       value="<?php echo esc_attr( $pub_date ); ?>">
							<p class="description">
								<?php esc_html_e( 'The entry will only appear on the frontend on or after this date/time (scheduled publication).', 'hwwpfaq' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="hwwpfaq_author"><?php esc_html_e( 'Author', 'hwwpfaq' ); ?></label>
						</th>
						<td>
							<select id="hwwpfaq_author" name="author_id">
								<?php foreach ( $users as $user ) : ?>
									<option value="<?php echo absint( $user->ID ); ?>"
									        <?php selected( $author_id, $user->ID ); ?>>
										<?php echo esc_html( $user->display_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Active', 'hwwpfaq' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="is_active" value="1" <?php checked( $is_active ); ?>>
								<?php esc_html_e( 'Entry is active and eligible for display (subject to publication date)', 'hwwpfaq' ); ?>
							</label>
						</td>
					</tr>

				</table>

				<?php submit_button( $submit_text ); ?>
			</form>
		</div>
		<?php
	}

	// =========================================================================
	// Admin notices
	// =========================================================================

	private function display_notices() {
		$msg_map = array(
			'created' => __( 'FAQ entry created successfully.', 'hwwpfaq' ),
			'updated' => __( 'FAQ entry updated successfully.', 'hwwpfaq' ),
			'deleted' => __( 'FAQ entry deleted.', 'hwwpfaq' ),
			'toggled' => __( 'FAQ entry status changed.', 'hwwpfaq' ),
		);

		$key = isset( $_GET['hwwpfaq_msg'] ) ? sanitize_key( $_GET['hwwpfaq_msg'] ) : '';
		if ( $key && isset( $msg_map[ $key ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $msg_map[ $key ] )
			);
		}
	}
}
