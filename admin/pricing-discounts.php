<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// PRICING & DISCOUNTS ADMIN PAGE
// ─────────────────────────────────────────────────────────────────────────────
function misaha_pricing_page() {
    global $wpdb;

    // ── Handle pricing save
    if ( isset( $_POST['misaha_pricing_submit'] ) ) {
        check_admin_referer( 'misaha_pricing_nonce' );
        update_option( 'misaha_price_hall_per_hour', floatval( $_POST['price_hall_hour'] ) );
        update_option( 'misaha_price_seat_day',      floatval( $_POST['price_seat_day'] ) );
        update_option( 'misaha_price_seat_week',     floatval( $_POST['price_seat_week'] ) );
        update_option( 'misaha_price_seat_month',    floatval( $_POST['price_seat_month'] ) );
        echo '<div class="notice notice-success is-dismissible"><p>Pricing updated.</p></div>';
    }

    // ── Handle discount save
    if ( isset( $_POST['misaha_discount_submit'] ) ) {
        check_admin_referer( 'misaha_discount_nonce' );

        $edit_id   = absint( $_POST['discount_edit_id'] ?? 0 );
        $disc_data = array(
            'name'              => sanitize_text_field( $_POST['disc_name']     ?? '' ),
            'code'              => strtoupper( sanitize_text_field( $_POST['disc_code'] ?? '' ) ),
            'type'              => in_array( $_POST['disc_type'], array('percentage','fixed'), true ) ? $_POST['disc_type'] : 'percentage',
            'value'             => floatval( $_POST['disc_value']              ?? 0 ),
            'applies_to'        => in_array( $_POST['disc_applies'], array('all','hall','seat'), true ) ? $_POST['disc_applies'] : 'all',
            'discount_category' => sanitize_text_field( $_POST['disc_category'] ?? 'custom' ),
            'min_amount'        => floatval( $_POST['disc_min']               ?? 0 ),
            'usage_limit'       => ( ! empty( $_POST['disc_limit'] ) ) ? absint( $_POST['disc_limit'] ) : null,
            'valid_from'        => ! empty( $_POST['disc_from'] ) ? sanitize_text_field( $_POST['disc_from'] ) : null,
            'valid_until'       => ! empty( $_POST['disc_until'] ) ? sanitize_text_field( $_POST['disc_until'] ) : null,
            'active'            => isset( $_POST['disc_active'] ) ? 1 : 0,
        );

        $formats = array( '%s','%s','%s','%f','%s','%s','%f','%d','%s','%s','%d' );

        if ( $edit_id ) {
            $wpdb->update( $wpdb->prefix . 'misaha_discounts', $disc_data, array('id'=>$edit_id), $formats, array('%d') );
            $msg = 'Discount updated.';
        } else {
            $wpdb->insert( $wpdb->prefix . 'misaha_discounts', $disc_data, $formats );
            $msg = 'Discount created.';
        }
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
    }

    // ── Handle discount delete
    if ( isset( $_GET['delete_discount'] ) && check_admin_referer( 'delete_discount_' . absint($_GET['delete_discount']) ) ) {
        $wpdb->delete( $wpdb->prefix . 'misaha_discounts', array( 'id' => absint( $_GET['delete_discount'] ) ) );
        echo '<div class="notice notice-success is-dismissible"><p>Discount deleted.</p></div>';
    }

    $discounts = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}misaha_discounts ORDER BY id DESC" );
    $currency  = get_option( 'misaha_currency', 'LYD' );

    // Editing existing discount?
    $editing = null;
    if ( isset( $_GET['edit_discount'] ) ) {
        $editing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}misaha_discounts WHERE id = %d LIMIT 1",
            absint( $_GET['edit_discount'] )
        ) );
    }
    ?>
    <div class="wrap misaha-admin-wrap">
        <h1 class="misaha-admin-title">🏷️ Pricing &amp; Discounts</h1>

        <!-- ── Pricing Card ── -->
        <div class="misaha-admin-card">
            <h2>💰 Base Pricing</h2>
            <form method="post">
                <?php wp_nonce_field( 'misaha_pricing_nonce' ); ?>
                <input type="hidden" name="misaha_pricing_submit" value="1">
                <div class="misaha-form-grid">
                    <div class="misaha-form-group">
                        <label>Hall (per hour) — <?php echo esc_html($currency); ?></label>
                        <input type="number" name="price_hall_hour" step="0.01" min="0"
                               value="<?php echo esc_attr( get_option('misaha_price_hall_per_hour',50) ); ?>">
                    </div>
                    <div class="misaha-form-group">
                        <label>Seat — Day Pass — <?php echo esc_html($currency); ?></label>
                        <input type="number" name="price_seat_day" step="0.01" min="0"
                               value="<?php echo esc_attr( get_option('misaha_price_seat_day',15) ); ?>">
                    </div>
                    <div class="misaha-form-group">
                        <label>Seat — Weekly Pass — <?php echo esc_html($currency); ?></label>
                        <input type="number" name="price_seat_week" step="0.01" min="0"
                               value="<?php echo esc_attr( get_option('misaha_price_seat_week',80) ); ?>">
                    </div>
                    <div class="misaha-form-group">
                        <label>Seat — Monthly Pass — <?php echo esc_html($currency); ?></label>
                        <input type="number" name="price_seat_month" step="0.01" min="0"
                               value="<?php echo esc_attr( get_option('misaha_price_seat_month',250) ); ?>">
                    </div>
                </div>
                <?php submit_button( '💾 Update Pricing' ); ?>
            </form>
        </div>

        <!-- ── Discount Form ── -->
        <div class="misaha-admin-card">
            <h2><?php echo $editing ? '✏️ Edit Discount Rule' : '➕ New Discount Rule'; ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'misaha_discount_nonce' ); ?>
                <input type="hidden" name="misaha_discount_submit" value="1">
                <input type="hidden" name="discount_edit_id" value="<?php echo $editing ? esc_attr($editing->id) : 0; ?>">

                <div class="misaha-form-grid">
                    <div class="misaha-form-group">
                        <label>Discount Name *</label>
                        <input type="text" name="disc_name" required placeholder="e.g. Student Discount"
                               value="<?php echo $editing ? esc_attr($editing->name) : ''; ?>">
                    </div>
                    <div class="misaha-form-group">
                        <label>Discount Code *</label>
                        <input type="text" name="disc_code" required placeholder="STUDENT20" style="text-transform:uppercase"
                               value="<?php echo $editing ? esc_attr($editing->code) : ''; ?>">
                    </div>
                    <div class="misaha-form-group">
                        <label>Category</label>
                        <select name="disc_category">
                            <?php
                            $cats = array('student','seasonal','group','custom');
                            foreach ( $cats as $cat ) :
                            ?>
                            <option value="<?php echo esc_attr($cat); ?>"
                                <?php selected( $editing ? $editing->discount_category : '', $cat ); ?>>
                                <?php echo ucfirst($cat); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="misaha-form-group">
                        <label>Discount Type</label>
                        <select name="disc_type">
                            <option value="percentage" <?php selected($editing->type??'','percentage'); ?>>Percentage (%)</option>
                            <option value="fixed"      <?php selected($editing->type??'','fixed'); ?>>Fixed Amount</option>
                        </select>
                    </div>
                    <div class="misaha-form-group">
                        <label>Value *</label>
                        <input type="number" name="disc_value" step="0.01" min="0" required placeholder="20"
                               value="<?php echo $editing ? esc_attr($editing->value) : ''; ?>">
                    </div>
                    <div class="misaha-form-group">
                        <label>Applies To</label>
                        <select name="disc_applies">
                            <option value="all"  <?php selected($editing->applies_to??'','all'); ?>>All</option>
                            <option value="hall" <?php selected($editing->applies_to??'','hall'); ?>>Hall Only</option>
                            <option value="seat" <?php selected($editing->applies_to??'','seat'); ?>>Seats Only</option>
                        </select>
                    </div>
                    <div class="misaha-form-group">
                        <label>Minimum Amount (<?php echo esc_html($currency); ?>)</label>
                        <input type="number" name="disc_min" step="0.01" min="0" placeholder="0"
                               value="<?php echo $editing ? esc_attr($editing->min_amount) : '0'; ?>">
                    </div>
                    <div class="misaha-form-group">
                        <label>Usage Limit (leave blank = unlimited)</label>
                        <input type="number" name="disc_limit" min="1" placeholder="100"
                               value="<?php echo $editing && $editing->usage_limit ? esc_attr($editing->usage_limit) : ''; ?>">
                    </div>
                    <div class="misaha-form-group">
                        <label>Valid From</label>
                        <input type="date" name="disc_from"
                               value="<?php echo $editing ? esc_attr($editing->valid_from) : ''; ?>">
                    </div>
                    <div class="misaha-form-group">
                        <label>Valid Until</label>
                        <input type="date" name="disc_until"
                               value="<?php echo $editing ? esc_attr($editing->valid_until) : ''; ?>">
                    </div>
                    <div class="misaha-form-group misaha-form-group-full">
                        <label>
                            <input type="checkbox" name="disc_active" value="1"
                                   <?php checked($editing ? $editing->active : 1, 1); ?>>
                            Active (visible to users)
                        </label>
                    </div>
                </div>

                <?php submit_button( $editing ? '✏️ Update Discount' : '➕ Create Discount' ); ?>
                <?php if ( $editing ) : ?>
                <a href="?page=misaha-pricing" class="button">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ── Discounts Table ── -->
        <div class="misaha-admin-card">
            <h2>📋 All Discount Rules</h2>
            <table class="misaha-admin-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th><th>Code</th><th>Category</th><th>Type</th><th>Value</th>
                        <th>Applies To</th><th>Usage</th><th>Valid Until</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $discounts as $d ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($d->name); ?></strong></td>
                        <td><code><?php echo esc_html($d->code); ?></code></td>
                        <td><?php echo esc_html(ucfirst($d->discount_category)); ?></td>
                        <td><?php echo $d->type === 'percentage' ? 'Percentage' : 'Fixed ' . $currency; ?></td>
                        <td><?php echo $d->type === 'percentage' ? esc_html($d->value).'%' : $currency.' '.number_format($d->value,2); ?></td>
                        <td><?php echo esc_html(ucfirst($d->applies_to)); ?></td>
                        <td><?php echo esc_html($d->usage_count); ?><?php echo $d->usage_limit ? ' / '.esc_html($d->usage_limit) : ' / ∞'; ?></td>
                        <td><?php echo $d->valid_until ? esc_html($d->valid_until) : '—'; ?></td>
                        <td><span class="misaha-badge misaha-badge-<?php echo $d->active ? 'confirmed' : 'cancelled'; ?>"><?php echo $d->active ? 'Active' : 'Inactive'; ?></span></td>
                        <td>
                            <a href="<?php echo esc_url( add_query_arg( array('page'=>'misaha-pricing','edit_discount'=>$d->id), admin_url('admin.php') ) ); ?>"
                               class="button button-small">✏️</a>
                            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array('page'=>'misaha-pricing','delete_discount'=>$d->id), admin_url('admin.php') ), 'delete_discount_'.$d->id ) ); ?>"
                               class="button button-small button-link-delete"
                               onclick="return confirm('Delete this discount?')">🗑</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( empty($discounts) ) : ?>
                    <tr><td colspan="10" style="text-align:center;padding:20px;">No discount rules yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
