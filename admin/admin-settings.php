<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Admin Menu ──────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'misaha_admin_menu' );
function misaha_admin_menu() {
    add_menu_page(
        'Misaha Booking',
        'Misaha Booking',
        'manage_options',
        'misaha-booking',
        'misaha_dashboard_page',
        'dashicons-calendar-alt',
        30
    );

    add_submenu_page( 'misaha-booking', 'Dashboard',          'Dashboard',          'manage_options', 'misaha-booking',          'misaha_dashboard_page' );
    add_submenu_page( 'misaha-booking', 'All Bookings',       'All Bookings',       'manage_options', 'misaha-bookings',         'misaha_bookings_page' );
    add_submenu_page( 'misaha-booking', 'Seat Passes',        'Seat Passes',        'manage_options', 'misaha-passes',           'misaha_passes_page' );
    add_submenu_page( 'misaha-booking', 'Halls & Seats',      'Halls & Seats',      'manage_options', 'misaha-halls',            'misaha_halls_page' );
    add_submenu_page( 'misaha-booking', 'Pricing & Discounts','Pricing & Discounts','manage_options', 'misaha-pricing',          'misaha_pricing_page' );
    add_submenu_page( 'misaha-booking', 'Settings',           'Settings',           'manage_options', 'misaha-settings',         'misaha_settings_page' );
}

// ─── Register Settings ───────────────────────────────────────────────────────
add_action( 'admin_init', 'misaha_register_settings' );
function misaha_register_settings() {
    $options = array(
        'misaha_pluto_api_key',
        'misaha_currency',
        'misaha_seats_per_hall',
        'misaha_price_hall_per_hour',
        'misaha_price_seat_day',
        'misaha_price_seat_week',
        'misaha_price_seat_month',
    );
    foreach ( $options as $opt ) {
        register_setting( 'misaha_settings_group', $opt, 'sanitize_text_field' );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// DASHBOARD PAGE
// ─────────────────────────────────────────────────────────────────────────────
function misaha_dashboard_page() {
    global $wpdb;

    $total_bookings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}misaha_bookings" );
    $total_passes   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}misaha_passes" );
    $total_revenue  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(final_price),0) FROM {$wpdb->prefix}misaha_bookings WHERE payment_status='paid'" );
    $total_revenue += (float) $wpdb->get_var( "SELECT COALESCE(SUM(final_price),0) FROM {$wpdb->prefix}misaha_passes WHERE payment_status='paid'" );
    $pending        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}misaha_bookings WHERE status='pending'" );
    $currency       = get_option( 'misaha_currency', 'LYD' );

    $recent = $wpdb->get_results(
        "SELECT b.*, h.name AS hall_name, u.display_name AS user_name
         FROM {$wpdb->prefix}misaha_bookings b
         LEFT JOIN {$wpdb->prefix}misaha_halls h ON b.hall_id = h.id
         LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
         ORDER BY b.created_at DESC LIMIT 10"
    );
    ?>
    <div class="wrap misaha-admin-wrap">
        <h1 class="misaha-admin-title">
            <span class="misaha-logo-icon">📅</span> Misaha Booking — Dashboard
        </h1>

        <div class="misaha-stat-cards">
            <div class="misaha-stat-card misaha-stat-blue">
                <div class="misaha-stat-icon">🏢</div>
                <div class="misaha-stat-value"><?php echo esc_html( $total_bookings ); ?></div>
                <div class="misaha-stat-label">Total Hall Bookings</div>
            </div>
            <div class="misaha-stat-card misaha-stat-green">
                <div class="misaha-stat-icon">💺</div>
                <div class="misaha-stat-value"><?php echo esc_html( $total_passes ); ?></div>
                <div class="misaha-stat-label">Total Seat Passes</div>
            </div>
            <div class="misaha-stat-card misaha-stat-purple">
                <div class="misaha-stat-icon">💰</div>
                <div class="misaha-stat-value"><?php echo esc_html( $currency ); ?> <?php echo number_format( $total_revenue, 2 ); ?></div>
                <div class="misaha-stat-label">Total Revenue (Paid)</div>
            </div>
            <div class="misaha-stat-card misaha-stat-orange">
                <div class="misaha-stat-icon">⏳</div>
                <div class="misaha-stat-value"><?php echo esc_html( $pending ); ?></div>
                <div class="misaha-stat-label">Pending Bookings</div>
            </div>
        </div>

        <div class="misaha-admin-card">
            <h2>Recent Bookings</h2>
            <table class="misaha-admin-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th><th>User</th><th>Hall</th><th>Date</th><th>Slot</th><th>Price</th><th>Payment</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent as $b ) : ?>
                    <tr>
                        <td>#<?php echo esc_html( $b->id ); ?></td>
                        <td><?php echo esc_html( $b->user_name ); ?></td>
                        <td><?php echo esc_html( $b->hall_name ); ?></td>
                        <td><?php echo esc_html( $b->booking_date ); ?></td>
                        <td><?php echo esc_html( substr($b->start_time,0,5) . ' – ' . substr($b->end_time,0,5) ); ?></td>
                        <td><?php echo esc_html( $currency . ' ' . number_format($b->final_price,2) ); ?></td>
                        <td><span class="misaha-badge misaha-badge-<?php echo esc_attr( $b->payment_status ); ?>"><?php echo esc_html( ucfirst($b->payment_status) ); ?></span></td>
                        <td><span class="misaha-badge misaha-badge-<?php echo esc_attr( $b->status ); ?>"><?php echo esc_html( ucfirst($b->status) ); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $recent ) ) : ?>
                    <tr><td colspan="8" style="text-align:center;padding:20px;">No bookings yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// ALL BOOKINGS PAGE
// ─────────────────────────────────────────────────────────────────────────────
function misaha_bookings_page() {
    global $wpdb;
    $currency = get_option( 'misaha_currency', 'LYD' );

    $status_filter = isset( $_GET['bstatus'] ) ? sanitize_text_field( $_GET['bstatus'] ) : '';
    $date_filter   = isset( $_GET['bdate'] )   ? sanitize_text_field( $_GET['bdate'] )   : '';

    $where  = '1=1';
    $params = array();
    if ( $status_filter ) { $where .= ' AND b.status = %s'; $params[] = $status_filter; }
    if ( $date_filter )   { $where .= ' AND b.booking_date = %s'; $params[] = $date_filter; }

    $sql = "SELECT b.*, h.name AS hall_name, u.display_name AS user_name
            FROM {$wpdb->prefix}misaha_bookings b
            LEFT JOIN {$wpdb->prefix}misaha_halls h ON b.hall_id = h.id
            LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
            WHERE $where ORDER BY b.created_at DESC";

    $bookings = $params ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) : $wpdb->get_results( $sql );
    ?>
    <div class="wrap misaha-admin-wrap">
        <h1 class="misaha-admin-title">🏢 All Hall Bookings</h1>

        <div class="misaha-filter-bar">
            <form method="get">
                <input type="hidden" name="page" value="misaha-bookings">
                <select name="bstatus">
                    <option value="">All Statuses</option>
                    <?php foreach ( array('pending','confirmed','cancelled') as $s ) : ?>
                    <option value="<?php echo esc_attr($s); ?>" <?php selected($status_filter,$s); ?>><?php echo ucfirst($s); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="bdate" value="<?php echo esc_attr($date_filter); ?>">
                <button type="submit" class="button button-primary">Filter</button>
                <a href="?page=misaha-bookings" class="button">Reset</a>
            </form>
        </div>

        <div class="misaha-admin-card">
            <table class="misaha-admin-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th><th>User</th><th>Hall</th><th>Date</th><th>Slot</th>
                        <th>Base</th><th>Discount</th><th>Final</th><th>Payment</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $bookings as $b ) : ?>
                    <tr id="booking-row-<?php echo esc_attr($b->id); ?>">
                        <td>#<?php echo esc_html($b->id); ?></td>
                        <td><?php echo esc_html($b->user_name); ?></td>
                        <td><?php echo esc_html($b->hall_name); ?></td>
                        <td><?php echo esc_html($b->booking_date); ?></td>
                        <td><?php echo esc_html( substr($b->start_time,0,5).' – '.substr($b->end_time,0,5) ); ?></td>
                        <td><?php echo esc_html($currency.' '.number_format($b->base_price,2)); ?></td>
                        <td><?php echo esc_html($currency.' '.number_format($b->discount_amount,2)); ?></td>
                        <td><strong><?php echo esc_html($currency.' '.number_format($b->final_price,2)); ?></strong></td>
                        <td><span class="misaha-badge misaha-badge-<?php echo esc_attr($b->payment_status); ?>"><?php echo ucfirst($b->payment_status); ?></span></td>
                        <td><span class="misaha-badge misaha-badge-<?php echo esc_attr($b->status); ?>"><?php echo ucfirst($b->status); ?></span></td>
                        <td>
                            <button class="button misaha-delete-booking" data-id="<?php echo esc_attr($b->id); ?>" 
                                    onclick="return confirm('Delete this booking?')">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( empty($bookings) ) : ?>
                    <tr><td colspan="11" style="text-align:center;padding:20px;">No bookings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// PASSES PAGE
// ─────────────────────────────────────────────────────────────────────────────
function misaha_passes_page() {
    global $wpdb;
    $currency = get_option( 'misaha_currency', 'LYD' );
    $passes   = $wpdb->get_results(
        "SELECT p.*, s.seat_number, h.name AS hall_name, u.display_name AS user_name
         FROM {$wpdb->prefix}misaha_passes p
         LEFT JOIN {$wpdb->prefix}misaha_seats s ON p.seat_id = s.id
         LEFT JOIN {$wpdb->prefix}misaha_halls h ON p.hall_id = h.id
         LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
         ORDER BY p.created_at DESC"
    );
    ?>
    <div class="wrap misaha-admin-wrap">
        <h1 class="misaha-admin-title">💺 Seat Passes</h1>
        <div class="misaha-admin-card">
            <table class="misaha-admin-table wp-list-table widefat fixed striped">
                <thead>
                    <tr><th>ID</th><th>User</th><th>Hall</th><th>Seat</th><th>Type</th><th>Start</th><th>End</th><th>Price</th><th>Payment</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ( $passes as $p ) : ?>
                    <tr>
                        <td>#<?php echo esc_html($p->id); ?></td>
                        <td><?php echo esc_html($p->user_name); ?></td>
                        <td><?php echo esc_html($p->hall_name); ?></td>
                        <td><?php echo esc_html($p->seat_number); ?></td>
                        <td><span class="misaha-badge misaha-badge-<?php echo esc_attr($p->pass_type);?>"><?php echo ucfirst($p->pass_type); ?></span></td>
                        <td><?php echo esc_html($p->start_date); ?></td>
                        <td><?php echo esc_html($p->end_date); ?></td>
                        <td><?php echo esc_html($currency.' '.number_format($p->final_price,2)); ?></td>
                        <td><span class="misaha-badge misaha-badge-<?php echo esc_attr($p->payment_status);?>"><?php echo ucfirst($p->payment_status); ?></span></td>
                        <td><span class="misaha-badge misaha-badge-<?php echo esc_attr($p->status);?>"><?php echo ucfirst($p->status); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( empty($passes) ) : ?>
                    <tr><td colspan="10" style="text-align:center;">No passes yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// HALLS MANAGEMENT PAGE
// ─────────────────────────────────────────────────────────────────────────────
function misaha_halls_page() {
    global $wpdb;
    $halls = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}misaha_halls ORDER BY id ASC" );
    ?>
    <div class="wrap misaha-admin-wrap">
        <h1 class="misaha-admin-title">🏢 Halls & Seats Management</h1>

        <div class="misaha-admin-card">
            <h2>Add / Edit Hall</h2>
            <form id="misaha-hall-form" class="misaha-admin-form">
                <input type="hidden" id="hall-edit-id" value="0">
                <div class="misaha-form-grid">
                    <div class="misaha-form-group">
                        <label>Hall Name *</label>
                        <input type="text" id="hall-name" placeholder="e.g. Main Conference Hall" required>
                    </div>
                    <div class="misaha-form-group">
                        <label>Capacity *</label>
                        <input type="number" id="hall-capacity" min="1" placeholder="50" required>
                    </div>
                    <div class="misaha-form-group">
                        <label>Price / Hour (<?php echo esc_html(get_option('misaha_currency','LYD')); ?>) *</label>
                        <input type="number" id="hall-price" step="0.01" min="0" placeholder="50.00" required>
                    </div>
                    <div class="misaha-form-group">
                        <label>Status</label>
                        <select id="hall-status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="misaha-form-group misaha-form-group-full">
                        <label>Description</label>
                        <textarea id="hall-description" rows="3" placeholder="Brief description of the hall..."></textarea>
                    </div>
                </div>
                <button type="submit" class="button button-primary">💾 Save Hall</button>
                <button type="button" id="misaha-hall-cancel" class="button" style="display:none;">Cancel</button>
                <div id="misaha-hall-msg" class="misaha-admin-msg" style="display:none;"></div>
            </form>
        </div>

        <div class="misaha-admin-card">
            <h2>All Halls</h2>
            <table class="misaha-admin-table wp-list-table widefat fixed striped">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Capacity</th><th>Price/Hour</th><th>Status</th><th>Seats</th><th>Actions</th></tr>
                </thead>
                <tbody id="misaha-halls-tbody">
                    <?php foreach ( $halls as $hall ) :
                        $seat_count = (int) $wpdb->get_var( $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}misaha_seats WHERE hall_id = %d", $hall->id
                        ) );
                    ?>
                    <tr id="hall-row-<?php echo esc_attr($hall->id); ?>"
                        data-id="<?php echo esc_attr($hall->id); ?>"
                        data-name="<?php echo esc_attr($hall->name); ?>"
                        data-capacity="<?php echo esc_attr($hall->capacity); ?>"
                        data-price="<?php echo esc_attr($hall->price_per_hour); ?>"
                        data-status="<?php echo esc_attr($hall->status); ?>"
                        data-description="<?php echo esc_attr($hall->description); ?>">
                        <td>#<?php echo esc_html($hall->id); ?></td>
                        <td><strong><?php echo esc_html($hall->name); ?></strong></td>
                        <td><?php echo esc_html($hall->capacity); ?></td>
                        <td><?php echo esc_html(number_format($hall->price_per_hour,2)); ?></td>
                        <td><span class="misaha-badge misaha-badge-<?php echo esc_attr($hall->status);?>"><?php echo ucfirst($hall->status);?></span></td>
                        <td><?php echo esc_html($seat_count); ?> seats</td>
                        <td>
                            <button class="button misaha-edit-hall" data-id="<?php echo esc_attr($hall->id);?>">✏️ Edit</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// SETTINGS PAGE
// ─────────────────────────────────────────────────────────────────────────────
function misaha_settings_page() {
    if ( isset( $_POST['misaha_settings_submit'] ) ) {
        check_admin_referer( 'misaha_settings_nonce' );
        $fields = array(
            'misaha_pluto_api_key'       => 'sanitize_text_field',
            'misaha_currency'            => 'sanitize_text_field',
            'misaha_seats_per_hall'      => 'absint',
        );
        foreach ( $fields as $key => $san ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_option( $key, call_user_func( $san, $_POST[ $key ] ) );
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }
    ?>
    <div class="wrap misaha-admin-wrap">
        <h1 class="misaha-admin-title">⚙️ Settings</h1>
        <div class="misaha-admin-card">
            <form method="post">
                <?php wp_nonce_field( 'misaha_settings_nonce' ); ?>
                <input type="hidden" name="misaha_settings_submit" value="1">
                <table class="form-table">
                    <tr>
                        <th><label for="misaha_pluto_api_key">Pluto API Key</label></th>
                        <td>
                            <input type="text" id="misaha_pluto_api_key" name="misaha_pluto_api_key"
                                   value="<?php echo esc_attr( get_option('misaha_pluto_api_key', MISAHA_PLUTO_KEY) ); ?>"
                                   class="regular-text">
                            <p class="description">Your Pluto payment gateway API key.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="misaha_currency">Currency</label></th>
                        <td>
                            <input type="text" id="misaha_currency" name="misaha_currency"
                                   value="<?php echo esc_attr( get_option('misaha_currency','LYD') ); ?>"
                                   class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="misaha_seats_per_hall">Default Seats per Hall</label></th>
                        <td>
                            <input type="number" id="misaha_seats_per_hall" name="misaha_seats_per_hall"
                                   value="<?php echo esc_attr( get_option('misaha_seats_per_hall',20) ); ?>"
                                   min="1" max="200" class="small-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>

        <div class="misaha-admin-card">
            <h2>🔌 Shortcode Reference</h2>
            <table class="misaha-admin-table widefat fixed">
                <thead><tr><th>Shortcode</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>[misaha_booking_calendar]</code></td><td>Display the hall booking calendar on any page.</td></tr>
                    <tr><td><code>[misaha_seat_map]</code></td><td>Display the interactive seat selection map.</td></tr>
                    <tr><td><code>[misaha_user_dashboard]</code></td><td>Show the logged-in user's booking history &amp; passes.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
