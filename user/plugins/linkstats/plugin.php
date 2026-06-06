<?php
/*
Plugin Name:  Link Status Manager
Plugin URI:   https://github.com/deafingov-org/DIG-YOURLS-linklist
Description:  Adds per-link status control (active, archived, trashed) and display toggle to the admin, filters the public page to show only active links, and provides configurable public page settings.
Version:      1.1
Author:       Deaf In Government Tech Team
Author URI:   https://deafingov.org
*/

// No direct call
if ( !defined( 'YOURLS_ABSPATH' ) ) die();

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

yourls_add_action( 'plugins_loaded', 'linkstats_init' );

function linkstats_init() {
    linkstats_create_table();
    yourls_register_plugin_page( 'linkstats', 'Link Status Manager', 'linkstats_admin_page' );
}

// ---------------------------------------------------------------------------
// Database table
// ---------------------------------------------------------------------------

function linkstats_create_table() {
    $table = YOURLS_DB_PREFIX . 'linkstat';
    $sql   = "CREATE TABLE IF NOT EXISTS `$table` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `keyword`     VARCHAR(100) NOT NULL,
        `display`     TINYINT(1)   NOT NULL DEFAULT 1,
        `status`      ENUM('active','archived','trashed') NOT NULL DEFAULT 'active',
        `modified_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `keyword` (`keyword`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    yourls_get_db()->fetchAffected( $sql, array() );
}

// ---------------------------------------------------------------------------
// Admin table: add Status column header
// ---------------------------------------------------------------------------

yourls_add_filter( 'table_head_cells', 'linkstats_table_head' );

function linkstats_table_head( $cells ) {
    $cells['linkstatus'] = yourls__( 'Status' );
    return $cells;
}

// ---------------------------------------------------------------------------
// Admin table: add Status cell per row
// ---------------------------------------------------------------------------

yourls_add_filter( 'table_add_row_cell_array', 'linkstats_table_cell', 10, 7 );

function linkstats_table_cell( $cells, $keyword, $url, $title, $ip, $clicks, $timestamp ) {
    $stat    = linkstats_get( $keyword );
    $status  = $stat ? $stat->status  : 'active';
    $display = $stat ? (int) $stat->display : 1;

    $nonce      = yourls_create_nonce( 'linkstats_update_' . $keyword );
    $action_url = yourls_admin_url( 'admin-ajax.php' );

    $status_options = '';
    foreach ( array( 'active', 'archived', 'trashed' ) as $s ) {
        $selected        = selected( $status, $s, false );
        $status_options .= "<option value=\"$s\" $selected>$s</option>";
    }

    $display_checked = $display ? 'checked' : '';

    $html  = '<form class="linkstats-form" style="font-size:0.85em;" onsubmit="return false;">';
    $html .= "<input type='hidden' name='action'  value='linkstats_update' />";
    $html .= "<input type='hidden' name='keyword' value='" . yourls_esc_attr( $keyword ) . "' />";
    $html .= "<input type='hidden' name='nonce'   value='$nonce' />";
    $html .= "<select name='status' onchange='linkstats_save(this.form)'>$status_options</select> ";
    $html .= "<label title='Show on public page'><input type='checkbox' name='display' value='1' $display_checked onchange='linkstats_save(this.form)' /> Show</label>";
    $html .= '</form>';

    $cells['linkstatus'] = array(
        'template' => '%html%',
        'html'     => $html,
    );

    return $cells;
}

// ---------------------------------------------------------------------------
// Handle inline form submission via AJAX
// ---------------------------------------------------------------------------

yourls_add_action( 'yourls_ajax_linkstats_update', 'linkstats_ajax_update' );

function linkstats_ajax_update() {
    $keyword = isset( $_POST['keyword'] ) ? yourls_sanitize_keyword( $_POST['keyword'] ) : '';

    yourls_verify_nonce( 'linkstats_update_' . $keyword );

    $status  = isset( $_POST['status'] ) && in_array( $_POST['status'], array( 'active', 'archived', 'trashed' ) )
               ? $_POST['status'] : 'active';
    $display = isset( $_POST['display'] ) && $_POST['display'] == '1' ? 1 : 0;

    linkstats_save( $keyword, $status, $display );

    yourls_ajax_return( array( 'success' => true, 'message' => 'Status updated.' ) );
}

// ---------------------------------------------------------------------------
// Wire up AJAX save JS on the admin page
// ---------------------------------------------------------------------------

yourls_add_action( 'admin_page_before_table', 'linkstats_admin_js' );

function linkstats_admin_js() {
    $ajax_url = yourls_admin_url( 'admin-ajax.php' );
    echo <<<HTML
<script>
function linkstats_save(form) {
    var keyword = form.querySelector('[name=keyword]').value;
    var nonce   = form.querySelector('[name=nonce]').value;
    var status  = form.querySelector('[name=status]').value;
    var display = form.querySelector('[name=display]').checked ? '1' : '0';

    var data = new FormData();
    data.append('action',  'linkstats_update');
    data.append('keyword', keyword);
    data.append('nonce',   nonce);
    data.append('status',  status);
    data.append('display', display);

    fetch('{$ajax_url}', { method: 'POST', body: data })
        .then(r => r.json())
        .then(function(res) {
            if (res.success) {
                var sel = form.querySelector('select[name=status]');
                sel.style.outline = '2px solid green';
                setTimeout(function(){ sel.style.outline = ''; }, 1500);
            }
        });
}
</script>
HTML;
}

// ---------------------------------------------------------------------------
// Plugin admin page — two sections: Page Settings + Link Status Manager
// ---------------------------------------------------------------------------

function linkstats_admin_page() {
    $active_tab = isset( $_GET['tab'] ) && $_GET['tab'] === 'settings' ? 'settings' : 'status';
    $base_url   = yourls_admin_url( 'plugins.php?page=linkstats' );

    // --- Handle Page Settings save ---
    if ( $active_tab === 'settings' && isset( $_POST['linkstats_save_settings'] ) ) {
        yourls_verify_nonce( 'linkstats_settings' );
        yourls_update_option( 'linkstats_logo_url',     trim( $_POST['linkstats_logo_url']     ?? '' ) );
        yourls_update_option( 'linkstats_page_title',   trim( $_POST['linkstats_page_title']   ?? '' ) );
        yourls_update_option( 'linkstats_page_heading', trim( $_POST['linkstats_page_heading'] ?? '' ) );
        $logo_width  = isset( $_POST['linkstats_logo_width'] )  ? (int) $_POST['linkstats_logo_width']  : 0;
        $logo_height = isset( $_POST['linkstats_logo_height'] ) ? (int) $_POST['linkstats_logo_height'] : 0;
        yourls_update_option( 'linkstats_logo_width',   $logo_width  > 0 ? $logo_width  : '' );
        yourls_update_option( 'linkstats_logo_height',  $logo_height > 0 ? $logo_height : '' );
        yourls_update_option( 'linkstats_logo_ratio',      isset( $_POST['linkstats_logo_ratio'] ) ? 1 : 0 );
        yourls_update_option( 'linkstats_footer_bg_color', trim( $_POST['linkstats_footer_bg_color'] ?? '#9b1c1c' ) );
        yourls_update_option( 'linkstats_footer_tx_color', trim( $_POST['linkstats_footer_tx_color'] ?? '#ffffff' ) );
        yourls_update_option( 'linkstats_footer_host_text',trim( $_POST['linkstats_footer_host_text'] ?? '' ) );
        yourls_update_option( 'linkstats_footer_host_url', trim( $_POST['linkstats_footer_host_url']  ?? '' ) );
        echo '<p style="color:green;font-weight:bold;">&#10003; Settings saved.</p>';
    }

    // --- Handle Link Status bulk save ---
    if ( $active_tab === 'status' && isset( $_POST['linkstats_bulk'] ) ) {
        yourls_verify_nonce( 'linkstats_bulk' );
        if ( isset( $_POST['bulk_keyword'] ) && is_array( $_POST['bulk_keyword'] ) ) {
            foreach ( $_POST['bulk_keyword'] as $keyword ) {
                $keyword = yourls_sanitize_keyword( $keyword );
                $status  = isset( $_POST['bulk_status'][$keyword] )
                           && in_array( $_POST['bulk_status'][$keyword], array( 'active', 'archived', 'trashed' ) )
                           ? $_POST['bulk_status'][$keyword] : 'active';
                $display = isset( $_POST['bulk_display'][$keyword] ) ? 1 : 0;
                linkstats_save( $keyword, $status, $display );
            }
            echo '<p style="color:green;font-weight:bold;">&#10003; Changes saved.</p>';
        }
    }

    // --- Tab navigation ---
    echo '<style>
        .linkstats-tabs { display:flex; gap:0; margin-bottom:0; border-bottom:2px solid #9b1c1c; }
        .linkstats-tabs a {
            display:inline-block; padding:8px 20px; text-decoration:none;
            color:#555; background:#f4f4f4; border:1px solid #ddd;
            border-bottom:none; margin-right:4px; border-radius:4px 4px 0 0;
        }
        .linkstats-tabs a.active { background:#fff; color:#9b1c1c; font-weight:bold; border-bottom:2px solid #fff; margin-bottom:-2px; }
        .linkstats-tab-content { background:#fff; border:1px solid #ddd; border-top:none; padding:20px; }
        .linkstats-form-row { margin-bottom:14px; }
        .linkstats-form-row label { display:block; font-weight:bold; margin-bottom:4px; }
        .linkstats-form-row input[type=text] { width:100%; max-width:600px; padding:5px 8px; }
        .linkstats-form-row small { display:block; color:#666; margin-top:3px; font-size:0.88em; }
    </style>';

    echo '<h2>Link Status Manager</h2>';
    echo '<div class="linkstats-tabs">';
    echo '<a href="' . $base_url . '&tab=status"  class="' . ( $active_tab === 'status'   ? 'active' : '' ) . '">&#9881; Link Status</a>';
    echo '<a href="' . $base_url . '&tab=settings" class="' . ( $active_tab === 'settings' ? 'active' : '' ) . '">&#9881; Page Settings</a>';
    echo '</div>';
    echo '<div class="linkstats-tab-content">';

    if ( $active_tab === 'settings' ) {
        // ---- PAGE SETTINGS TAB ----
        $logo_url      = yourls_get_option( 'linkstats_logo_url' )     ?: '';
        $page_title    = yourls_get_option( 'linkstats_page_title' )   ?: '';
        $page_heading  = yourls_get_option( 'linkstats_page_heading' ) ?: '';
        $logo_width    = (int) yourls_get_option( 'linkstats_logo_width' );
        $logo_height   = (int) yourls_get_option( 'linkstats_logo_height' );
        $keep_ratio    = yourls_get_option( 'linkstats_logo_ratio' );
        $keep_ratio_checked = ( (string) $keep_ratio === '' || (int) $keep_ratio === 1 ) ? 'checked' : '';
        $heading_text  = $page_heading ?: 'List of Links';
        $footer_bg_color  = yourls_get_option( 'linkstats_footer_bg_color' )  ?: '#9b1c1c';
        $footer_tx_color  = yourls_get_option( 'linkstats_footer_tx_color' )  ?: '#ffffff';
        $footer_host_text = yourls_get_option( 'linkstats_footer_host_text' ) ?: '';
        $footer_host_url  = yourls_get_option( 'linkstats_footer_host_url' )  ?: '';
        $nonce            = yourls_create_nonce( 'linkstats_settings' );

        // --- Inline CSS (adapted from LogoSuite) ---
        echo '<style>
        .ls-panel { background:#fff; border:1px solid #d9e6ef; border-radius:10px; margin-bottom:18px; padding:16px; }
        .ls-panel h3 { margin:0 0 12px; font-size:1.05rem; font-weight:700; color:#0a4b78; }
        .ls-form-row { margin-bottom:12px; }
        .ls-form-row label { display:block; font-weight:700; margin-bottom:2px; font-size:.84rem; }
        .ls-form-row small { display:block; color:#667885; margin:0 0 6px; font-size:.73rem; }
        .ls-form-row input[type=text] { width:100%; max-width:600px; height:32px; border:1px solid #c7d8e4; border-radius:8px; padding:0 9px; box-sizing:border-box; }
        .ls-logo-layout { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:24px; align-items:start; }
        .ls-logo-preview-box { border:1px solid #d9e6ef; border-radius:10px; background:#f9fcff; padding:16px; min-height:240px; }
        .ls-preview-label { display:block; margin-bottom:10px; font-weight:700; font-size:.88rem; color:#26323a; }
        #ls-preview-wrapper { min-height:160px; display:flex; align-items:center; justify-content:center; }
        .ls-preview-img { max-height:200px !important; max-width:100% !important; object-fit:contain; border:1px solid #d4e2ec; border-radius:8px; padding:10px; background:#fff; }
        .ls-preview-hidden { display:none; }
        .ls-size-warning { margin-top:8px; padding:7px 10px; background:#fff9e8; border-left:3px solid #f0b429; border-radius:4px; font-size:.72rem; color:#5f4a10; }
        .ls-size-warning-hidden { display:none; }
        .ls-preview-error { color:#bf1d1d; font-size:.78rem; font-weight:600; margin-top:6px; }
        .ls-preview-controls { margin-top:10px; padding-top:8px; border-top:1px dashed #d5e3ee; }
        .ls-size-row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; margin-bottom:6px; }
        .ls-size-col label { display:block; margin-bottom:3px; font-size:.78rem; font-weight:700; color:#3e515f; }
        .ls-size-col input[type=number] { width:100%; height:30px; border:1px solid #c7d8e4; border-radius:7px; padding:0 8px; font-size:.74rem; box-sizing:border-box; }
        .ls-ratio-row label { display:inline-flex; align-items:center; gap:6px; font-size:.76rem; color:#3e515f; }
        .ls-ratio-tip { display:block; font-size:.69rem; color:#667885; margin-top:4px; }
        .ls-heading-preview { margin-top:12px; padding:12px 16px; border:1px solid #d9e6ef; background:#fafafa; border-radius:8px; }
        .ls-heading-preview-label { margin:0 0 6px; font-size:.75rem; color:#888; text-transform:uppercase; letter-spacing:.05em; }
        @media (max-width:860px) { .ls-logo-layout { grid-template-columns:1fr; } }
        </style>';

        echo '<p>These settings control the appearance of the public links page.</p>';
        echo '<form method="post">';
        echo "<input type='hidden' name='linkstats_save_settings' value='1' />";
        echo "<input type='hidden' name='nonce' value='$nonce' />";

        // --- Logo section ---
        echo '<div class="ls-panel">';
        echo '<h3>Logo Settings</h3>';
        echo '<div class="ls-logo-layout">';
        echo '<div class="ls-logo-fields">';

        echo '<div class="ls-form-row">';
        echo '<label for="linkstats_logo_url">Image URL</label>';
        echo '<small>Full URL to your logo image (PNG, JPG, SVG). Leave blank to hide.</small>';
        echo '<input type="text" name="linkstats_logo_url" id="linkstats_logo_url" value="' . yourls_esc_attr( $logo_url ) . '" placeholder="https://example.com/logo.png" />';
        echo '</div>';

        echo '</div>'; // .ls-logo-fields

        // --- Preview column ---
        $img_hidden = $logo_url ? '' : ' ls-preview-hidden';
        echo '<div class="ls-logo-preview-box">';
        echo '<span class="ls-preview-label">Logo Preview</span>';
        echo '<div id="ls-preview-wrapper">';
        echo '<img id="ls-logo-preview" class="ls-preview-img' . $img_hidden . '" src="' . yourls_esc_url( $logo_url ) . '" alt="" />';
        echo '</div>';
        echo '<div id="ls-size-warning" class="ls-size-warning ls-size-warning-hidden"></div>';
        echo '<div id="ls-preview-error" class="ls-preview-error ls-preview-hidden">Unable to load image — check the URL.</div>';
        echo '<div class="ls-preview-controls">';
        echo '<div class="ls-size-row">';
        echo '<div class="ls-size-col"><label for="linkstats_logo_width">Width (px)</label>';
        echo '<input type="number" name="linkstats_logo_width" id="linkstats_logo_width" min="1" step="1" value="' . ( $logo_width > 0 ? $logo_width : '' ) . '" /></div>';
        echo '<div class="ls-size-col"><label for="linkstats_logo_height">Height (px)</label>';
        echo '<input type="number" name="linkstats_logo_height" id="linkstats_logo_height" min="1" step="1" value="' . ( $logo_height > 0 ? $logo_height : '' ) . '" /></div>';
        echo '</div>';
        echo '<div class="ls-ratio-row"><label><input type="checkbox" name="linkstats_logo_ratio" id="linkstats_logo_ratio" value="1" ' . $keep_ratio_checked . ' /> Keep aspect ratio</label></div>';
        echo '<span class="ls-ratio-tip">Tip: set one side only and keep aspect ratio enabled for proportional resize.</span>';
        echo '</div>'; // .ls-preview-controls
        echo '</div>'; // .ls-logo-preview-box

        echo '</div>'; // .ls-logo-layout
        echo '</div>'; // .ls-panel

        // --- Page text section ---
        echo '<div class="ls-panel">';
        echo '<h3>Page Text Settings</h3>';

        echo '<div class="ls-form-row">';
        echo '<label for="linkstats_page_title">Browser Tab Title</label>';
        echo '<small>Text shown in the browser tab. Default: "Links — YOURLS"</small>';
        echo '<input type="text" name="linkstats_page_title" id="linkstats_page_title" value="' . yourls_esc_attr( $page_title ) . '" placeholder="Links — My Organisation" />';
        echo '</div>';

        echo '<div class="ls-form-row">';
        echo '<label for="linkstats_page_heading">Page Heading</label>';
        echo '<small>The large heading shown on the public page. Default: "List of Links"</small>';
        echo '<input type="text" name="linkstats_page_heading" id="linkstats_page_heading" value="' . yourls_esc_attr( $page_heading ) . '" placeholder="List of Links" />';
        echo '</div>';

        echo '<div class="ls-heading-preview">';
        echo '<p class="ls-heading-preview-label">Heading Preview</p>';
        echo '<h2 id="ls-heading-preview" style="margin:0;font-size:1.4em;">' . yourls_esc_html( $heading_text ) . '</h2>';
        echo '</div>';

        echo '</div>'; // .ls-panel (Page Text Settings)

        // --- Footer Settings panel ---
        // Build preview footer text
        $preview_footer = 'Powered by YOURLS (linked)';
        if ( $footer_host_text ) {
            $preview_footer .= ' &mdash; Hosted by ' . yourls_esc_html( $footer_host_text );
        }

        echo '<div class="ls-panel">';
        echo '<h3>Footer Settings</h3>';

        // Helper to render a color picker row with RGB inputs
        // bg color
        echo '<div class="ls-form-row">';
        echo '<label>Background Color</label>';
        echo '<small>Pick a color or enter RGB values. Default: #9b1c1c (dark red)</small>';
        echo '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:4px;">';
        echo '<input type="color" name="linkstats_footer_bg_color" id="ls_footer_bg" value="' . yourls_esc_attr( $footer_bg_color ) . '" style="width:48px;height:36px;padding:2px;border:1px solid #c7d8e4;border-radius:6px;cursor:pointer;" oninput="ls_sync_picker(\'bg\',this.value)" />';
        echo '<div style="display:flex;align-items:center;gap:6px;">';
        echo '<span style="font-size:.8rem;color:#555;">R</span><input type="number" id="ls_bg_r" min="0" max="255" style="width:56px;height:30px;border:1px solid #c7d8e4;border-radius:6px;padding:0 6px;font-size:.8rem;" oninput="ls_sync_rgb(\'bg\')" />';
        echo '<span style="font-size:.8rem;color:#555;">G</span><input type="number" id="ls_bg_g" min="0" max="255" style="width:56px;height:30px;border:1px solid #c7d8e4;border-radius:6px;padding:0 6px;font-size:.8rem;" oninput="ls_sync_rgb(\'bg\')" />';
        echo '<span style="font-size:.8rem;color:#555;">B</span><input type="number" id="ls_bg_b" min="0" max="255" style="width:56px;height:30px;border:1px solid #c7d8e4;border-radius:6px;padding:0 6px;font-size:.8rem;" oninput="ls_sync_rgb(\'bg\')" />';
        echo '</div></div>';
        echo '</div>';

        // text color
        echo '<div class="ls-form-row">';
        echo '<label>Text Color</label>';
        echo '<small>Pick a color or enter RGB values. Default: #ffffff (white)</small>';
        echo '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:4px;">';
        echo '<input type="color" name="linkstats_footer_tx_color" id="ls_footer_tx" value="' . yourls_esc_attr( $footer_tx_color ) . '" style="width:48px;height:36px;padding:2px;border:1px solid #c7d8e4;border-radius:6px;cursor:pointer;" oninput="ls_sync_picker(\'tx\',this.value)" />';
        echo '<div style="display:flex;align-items:center;gap:6px;">';
        echo '<span style="font-size:.8rem;color:#555;">R</span><input type="number" id="ls_tx_r" min="0" max="255" style="width:56px;height:30px;border:1px solid #c7d8e4;border-radius:6px;padding:0 6px;font-size:.8rem;" oninput="ls_sync_rgb(\'tx\')" />';
        echo '<span style="font-size:.8rem;color:#555;">G</span><input type="number" id="ls_tx_g" min="0" max="255" style="width:56px;height:30px;border:1px solid #c7d8e4;border-radius:6px;padding:0 6px;font-size:.8rem;" oninput="ls_sync_rgb(\'tx\')" />';
        echo '<span style="font-size:.8rem;color:#555;">B</span><input type="number" id="ls_tx_b" min="0" max="255" style="width:56px;height:30px;border:1px solid #c7d8e4;border-radius:6px;padding:0 6px;font-size:.8rem;" oninput="ls_sync_rgb(\'tx\')" />';
        echo '</div></div>';
        echo '</div>';

        // webhost fields
        echo '<div class="ls-form-row">';
        echo '<label for="linkstats_footer_host_text">Webhost Display Text</label>';
        echo '<small>Name of your hosting provider shown in the footer. e.g. "DreamHost". Leave blank to omit.</small>';
        echo '<input type="text" name="linkstats_footer_host_text" id="linkstats_footer_host_text" value="' . yourls_esc_attr( $footer_host_text ) . '" placeholder="DreamHost" />';
        echo '</div>';

        echo '<div class="ls-form-row">';
        echo '<label for="linkstats_footer_host_url">Webhost URL</label>';
        echo '<small>Full URL for the hosting provider link. e.g. "https://www.dreamhost.com". Leave blank to show text only.</small>';
        echo '<input type="text" name="linkstats_footer_host_url" id="linkstats_footer_host_url" value="' . yourls_esc_attr( $footer_host_url ) . '" placeholder="https://www.dreamhost.com" />';
        echo '</div>';

        // live preview
        echo '<div class="ls-form-row">';
        echo '<label>Footer Preview</label>';
        echo '<div id="ls-footer-preview" style="padding:10px 16px;border-radius:6px;text-align:center;font-size:.85rem;background:' . yourls_esc_attr( $footer_bg_color ) . ';color:' . yourls_esc_attr( $footer_tx_color ) . ';">' . $preview_footer . '</div>';
        echo '</div>';

        echo '</div>'; // .ls-panel (Footer Settings)

        echo '<p><button type="submit" class="button button-primary">&#128190; Save Settings</button></p>';
        echo '</form>';

        // --- JS (adapted from LogoSuite admin.js) ---
        echo <<<JS
<script>
(function() {
    function els() {
        return {
            urlInput:    document.getElementById('linkstats_logo_url'),
            preview:     document.getElementById('ls-logo-preview'),
            error:       document.getElementById('ls-preview-error'),
            warning:     document.getElementById('ls-size-warning'),
            widthInput:  document.getElementById('linkstats_logo_width'),
            heightInput: document.getElementById('linkstats_logo_height'),
            ratioInput:  document.getElementById('linkstats_logo_ratio'),
            headingInput:document.getElementById('linkstats_page_heading'),
            headingPrev: document.getElementById('ls-heading-preview'),
        };
    }

    function applySize() {
        var e = els();
        if (!e.preview) return;
        var w = parseInt(e.widthInput.value, 10) || 0;
        var h = parseInt(e.heightInput.value, 10) || 0;
        var keepRatio = e.ratioInput.checked;
        e.preview.style.width = '';
        e.preview.style.height = '';
        e.preview.style.objectFit = '';
        e.preview.style.maxHeight = '';
        if (!w && !h) return;
        if (keepRatio) {
            if (w && h) { e.preview.style.width = w+'px'; e.preview.style.height = h+'px'; e.preview.style.objectFit = 'contain'; }
            else if (w) { e.preview.style.width = w+'px'; e.preview.style.height = 'auto'; }
            else        { e.preview.style.height = h+'px'; e.preview.style.width = 'auto'; }
        } else {
            if (w) e.preview.style.width  = w+'px';
            if (h) e.preview.style.height = h+'px';
        }
    }

    function syncRatio(changed) {
        var e = els();
        if (!e.preview || !e.ratioInput.checked) return;
        var nw = e.preview.naturalWidth || 0;
        var nh = e.preview.naturalHeight || 0;
        if (!nw || !nh) return;
        var ratio = nw / nh;
        var w = parseInt(e.widthInput.value, 10) || 0;
        var h = parseInt(e.heightInput.value, 10) || 0;
        if (changed === 'width'  && w) e.heightInput.value = Math.max(1, Math.round(w / ratio));
        if (changed === 'height' && h) e.widthInput.value  = Math.max(1, Math.round(h * ratio));
        if (changed === 'ratio'  && w) e.heightInput.value = Math.max(1, Math.round(w / ratio));
    }

    function prefillDims() {
        var e = els();
        if (!e.preview || !e.preview.naturalWidth) return;
        if (!e.widthInput.value  || parseInt(e.widthInput.value,  10) <= 0) e.widthInput.value  = e.preview.naturalWidth;
        if (!e.heightInput.value || parseInt(e.heightInput.value, 10) <= 0) e.heightInput.value = e.preview.naturalHeight;
    }

    function checkSizeWarning() {
        var e = els();
        var w = e.preview ? e.preview.naturalWidth  : 0;
        var h = e.preview ? e.preview.naturalHeight : 0;
        if (w > 800 || h > 800) {
            e.warning.textContent = '⚠️ Source image is ' + w + 'x' + h + ' px. It will display at full size unless you set a display width below. Suggested: set Width to 400-600 px and enable Keep aspect ratio.';
            e.warning.classList.remove('ls-size-warning-hidden');
        } else {
            e.warning.textContent = '';
            e.warning.classList.add('ls-size-warning-hidden');
        }
    }

    function onPreviewLoad() {
        var e = els();
        e.error.classList.add('ls-preview-hidden');
        e.preview.classList.remove('ls-preview-hidden');
        prefillDims();
        applySize();
        checkSizeWarning();
    }

    function onPreviewError() {
        var e = els();
        e.preview.classList.add('ls-preview-hidden');
        e.error.classList.remove('ls-preview-hidden');
        e.warning.classList.add('ls-size-warning-hidden');
    }

    function updatePreview(url) {
        var e = els();
        e.error.classList.add('ls-preview-hidden');
        e.warning.classList.add('ls-size-warning-hidden');
        if (!url.trim()) { e.preview.classList.add('ls-preview-hidden'); return; }
        e.preview.src = url;
        e.preview.classList.remove('ls-preview-hidden');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var e = els();
        if (!e.urlInput) return;

        e.preview.addEventListener('load',  onPreviewLoad);
        e.preview.addEventListener('error', onPreviewError);
        e.urlInput.addEventListener('input', function() { updatePreview(this.value); });

        e.widthInput.addEventListener('input',  function() { syncRatio('width');  applySize(); });
        e.heightInput.addEventListener('input', function() { syncRatio('height'); applySize(); });
        e.ratioInput.addEventListener('change', function() { syncRatio('ratio');  applySize(); });

        e.headingInput.addEventListener('input', function() {
            e.headingPrev.textContent = this.value || 'List of Links';
        });

        // Init footer color RGB fields from stored hex values
        var bgPicker = document.getElementById('ls_footer_bg');
        var txPicker = document.getElementById('ls_footer_tx');
        if (bgPicker) ls_sync_picker('bg', bgPicker.value);
        if (txPicker) ls_sync_picker('tx', txPicker.value);

        // Init preview if URL already set
        if (e.preview && !e.preview.classList.contains('ls-preview-hidden') && e.preview.complete && e.preview.naturalWidth > 0) {
            onPreviewLoad();
        } else {
            applySize();
        }
    });
})();

// --- Footer color helpers ---
function ls_hex_to_rgb(hex) {
    hex = hex.replace('#','');
    if (hex.length === 3) hex = hex.split('').map(function(c){return c+c;}).join('');
    return {
        r: parseInt(hex.substring(0,2),16),
        g: parseInt(hex.substring(2,4),16),
        b: parseInt(hex.substring(4,6),16)
    };
}
function ls_rgb_to_hex(r,g,b) {
    return '#'+[r,g,b].map(function(v){
        return Math.max(0,Math.min(255,v)).toString(16).padStart(2,'0');
    }).join('');
}
// channel = 'bg' or 'tx'
function ls_sync_picker(channel, hex) {
    var rgb = ls_hex_to_rgb(hex);
    var set = function(id, val){ var el=document.getElementById(id); if(el) el.value=val; };
    set('ls_'+channel+'_r', rgb.r);
    set('ls_'+channel+'_g', rgb.g);
    set('ls_'+channel+'_b', rgb.b);
    ls_update_footer_preview();
}
function ls_sync_rgb(channel) {
    var get = function(id){ return parseInt((document.getElementById(id)||{}).value,10)||0; };
    var hex = ls_rgb_to_hex(get('ls_'+channel+'_r'), get('ls_'+channel+'_g'), get('ls_'+channel+'_b'));
    var picker = document.getElementById('ls_footer_'+channel);
    if (picker) picker.value = hex;
    ls_update_footer_preview();
}
function ls_update_footer_preview() {
    var preview = document.getElementById('ls-footer-preview');
    if (!preview) return;
    var bg = (document.getElementById('ls_footer_bg')||{}).value || '#9b1c1c';
    var tx = (document.getElementById('ls_footer_tx')||{}).value || '#ffffff';
    var host_text = (document.getElementById('linkstats_footer_host_text')||{}).value || '';
    var text = 'Powered by YOURLS (linked)' + (host_text ? ' — Hosted by ' + (host_text || '') : '');
    preview.style.background = bg;
    preview.style.color = tx;
    preview.textContent = text;
}
// Keep preview in sync while typing host fields
document.addEventListener('DOMContentLoaded', function() {
    ['linkstats_footer_host_text','linkstats_footer_host_url'].forEach(function(id){
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', ls_update_footer_preview);
    });
});
</script>
JS;

    } else {
        // ---- LINK STATUS TAB ----
        $table_url  = YOURLS_DB_TABLE_URL;
        $table_stat = YOURLS_DB_PREFIX . 'linkstat';

        $sql  = "SELECT u.keyword, u.title, u.url,
                        COALESCE(s.status, 'active') AS status,
                        COALESCE(s.display, 1)        AS display
                 FROM `$table_url` u
                 LEFT JOIN `$table_stat` s ON u.keyword = s.keyword
                 ORDER BY u.timestamp DESC";
        $rows = yourls_get_db()->fetchObjects( $sql );
        $nonce = yourls_create_nonce( 'linkstats_bulk' );

        echo '<p>Set the status and visibility for each link. Changes here affect what appears on the public links page.</p>';
        echo '<form method="post">';
        echo "<input type='hidden' name='linkstats_bulk' value='1' />";
        echo "<input type='hidden' name='nonce' value='$nonce' />";
        echo '<table style="width:100%;border-collapse:collapse;">';
        echo '<thead><tr>';
        echo '<th style="text-align:left;padding:6px;border-bottom:2px solid #ccc;">Keyword</th>';
        echo '<th style="text-align:left;padding:6px;border-bottom:2px solid #ccc;">Title</th>';
        echo '<th style="text-align:left;padding:6px;border-bottom:2px solid #ccc;">Status</th>';
        echo '<th style="text-align:left;padding:6px;border-bottom:2px solid #ccc;">Show on Public Page</th>';
        echo '</tr></thead><tbody>';

        foreach ( $rows as $row ) {
            $k = yourls_esc_attr( $row->keyword );
            $t = yourls_esc_html( $row->title ?: $row->keyword );
            $status_opts = '';
            foreach ( array( 'active', 'archived', 'trashed' ) as $s ) {
                $sel          = selected( $row->status, $s, false );
                $status_opts .= "<option value='$s' $sel>$s</option>";
            }
            $disp_checked = $row->display ? 'checked' : '';

            echo "<tr style='border-bottom:1px solid #eee;'>";
            echo "<td style='padding:6px;'><code>$k</code><input type='hidden' name='bulk_keyword[]' value='$k' /></td>";
            echo "<td style='padding:6px;'>$t</td>";
            echo "<td style='padding:6px;'><select name='bulk_status[$k]'>$status_opts</select></td>";
            echo "<td style='padding:6px;'><input type='checkbox' name='bulk_display[$k]' value='1' $disp_checked /></td>";
            echo "</tr>";
        }

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button">Save All Changes</button></p>';
        echo '</form>';
    }

    echo '</div>'; // .linkstats-tab-content
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function linkstats_get( $keyword ) {
    $table = YOURLS_DB_PREFIX . 'linkstat';
    $sql   = "SELECT * FROM `$table` WHERE `keyword` = :keyword LIMIT 1";
    $binds = array( 'keyword' => $keyword );
    return yourls_get_db()->fetchObject( $sql, $binds );
}

function linkstats_save( $keyword, $status, $display ) {
    $table = YOURLS_DB_PREFIX . 'linkstat';
    $sql   = "INSERT INTO `$table` (`keyword`, `status`, `display`)
              VALUES (:keyword, :status, :display)
              ON DUPLICATE KEY UPDATE `status` = :status2, `display` = :display2";
    $binds = array(
        'keyword'  => $keyword,
        'status'   => $status,
        'display'  => (int) $display,
        'status2'  => $status,
        'display2' => (int) $display,
    );
    yourls_get_db()->fetchAffected( $sql, $binds );
}

// helper: return 'selected' attr string (mirrors WordPress selected())
function selected( $current, $value, $echo = true ) {
    $out = $current === $value ? ' selected="selected"' : '';
    if ( $echo ) echo $out;
    return $out;
}
