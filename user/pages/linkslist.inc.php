<?php

/*

About:
Public link list page for YOURLS. Included by /index.php at the site root.
Shows active links with their full short URL (for copying) linking to the long URL.
Includes pagination and per-page selector.

Original creator: Ruth Kitchin Tillman — https://github.com/ruthtillman/YOURLS
Edited by: nina de jesus — http://satifice.com
Updated: Deaf In Government — https://deafingov.org

*/

$site       = yourls_get_yourls_site();
$table_url  = YOURLS_DB_TABLE_URL;
$table_stat = YOURLS_DB_PREFIX . 'linkstat';

// --- Pull configurable settings from plugin options (set in admin > Link Status > Page Settings) ---
$logo_url     = yourls_get_option( 'linkstats_logo_url' )     ?: $site . '/user/files/DIGov_FConeline.png';
$page_title   = yourls_get_option( 'linkstats_page_title' )   ?: 'Links &mdash; YOURLS';
$page_heading = yourls_get_option( 'linkstats_page_heading' ) ?: 'List of Links';
$logo_width   = (int) yourls_get_option( 'linkstats_logo_width' );
$logo_height  = (int) yourls_get_option( 'linkstats_logo_height' );

// Build logo size attribute string
$logo_size_attr = '';
if ( $logo_width  > 0 ) $logo_size_attr .= ' width="'  . $logo_width  . '"';
if ( $logo_height > 0 ) $logo_size_attr .= ' height="' . $logo_height . '"';

// --- Pagination parameters ---
$per_page_options = array( 10, 25, 50, 'all' );
$per_page_default = 10;

$per_page = isset( $_GET['per_page'] ) ? $_GET['per_page'] : $per_page_default;
if ( $per_page !== 'all' ) {
    $per_page = in_array( (int) $per_page, array( 10, 25, 50 ) ) ? (int) $per_page : $per_page_default;
}

$current_page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

// --- Get total count of visible links ---
$sql_count = "SELECT COUNT(*) FROM `$table_url` u
              LEFT JOIN `$table_stat` s ON u.keyword = s.keyword
              WHERE s.keyword IS NULL OR (s.display = 1 AND s.status = 'active')";
$total_links = (int) yourls_get_db()->fetchValue( $sql_count, array() );

// --- Build query with pagination ---
if ( $per_page === 'all' ) {
    $offset     = 0;
    $limit_sql  = '';
    $total_pages = 1;
    $showing_count = $total_links;
} else {
    $total_pages   = $total_links > 0 ? ceil( $total_links / $per_page ) : 1;
    $current_page  = min( $current_page, $total_pages );
    $offset        = ( $current_page - 1 ) * $per_page;
    $limit_sql     = "LIMIT $per_page OFFSET $offset";
    $showing_count = min( $per_page, $total_links - $offset );
}

$sql = "SELECT u.keyword, u.url, u.title
        FROM `$table_url` u
        LEFT JOIN `$table_stat` s ON u.keyword = s.keyword
        WHERE s.keyword IS NULL OR (s.display = 1 AND s.status = 'active')
        ORDER BY u.timestamp DESC
        $limit_sql";

$query = yourls_get_db()->fetchObjects( $sql );

// --- Helper: build URL preserving current params ---
function linkstats_page_url( $paged, $per_page ) {
    $params = array( 'paged' => $paged, 'per_page' => $per_page );
    return '?' . http_build_query( $params );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo yourls_esc_html( $page_title ); ?></title>
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .page-content {
            flex: 1;
            padding: 1rem 2rem 2rem;
        }
        .logo-wrap {
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .logo-wrap img { height: 100px; }
        .logo-wrap h2  { margin: 0.25rem 0 1rem; }
        .copy-tip {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #555;
        }
        .list-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: #444;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .per-page-form label { margin-right: 0.4rem; }
        .per-page-form select {
            padding: 2px 6px;
            font-size: 0.9rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #ddd;
        }
        th { background: #f4f4f4; }
        .short-url-cell { white-space: nowrap; }
        .copy-btn {
            background: none;
            border: 1px solid #aaa;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            padding: 2px 6px;
            margin-left: 6px;
            color: #555;
            vertical-align: middle;
        }
        .copy-btn:hover { background: #eee; }
        .copy-btn.copied { color: green; border-color: green; }
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            margin-top: 1.25rem;
            flex-wrap: wrap;
            font-size: 0.9rem;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 4px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .pagination a:hover { background: #f0f0f0; }
        .pagination span.current {
            background: #9b1c1c;
            color: #fff;
            border-color: #9b1c1c;
            font-weight: bold;
        }
        .pagination span.disabled { color: #bbb; border-color: #e0e0e0; }
        site-footer {
            display: block;
            background: #9b1c1c;
            color: #fff;
            text-align: center;
            padding: 0.6rem 1rem;
            font-size: 0.85rem;
        }
        site-footer a { color: #ffd; text-decoration: underline; }
    </style>
</head>
<body>
<div class="page-content">
    <div class="logo-wrap">
        <?php if ( $logo_url ) : ?>
        <img src="<?php echo yourls_esc_url( $logo_url ); ?>" alt="<?php echo yourls_esc_attr( $page_heading ); ?>"<?php echo $logo_size_attr; ?> />
        <?php endif; ?>
        <h2><?php echo yourls_esc_html( $page_heading ); ?></h2>
    </div>

    <?php if ( $query ) : ?>

    <p class="copy-tip">Click a short link to visit the destination &mdash; or use the <strong>Copy</strong> button to copy the short link for use in a flyer, chat, or email.</p>

    <div class="list-meta">
        <span>
            <?php if ( $per_page === 'all' ) : ?>
                Viewing all <?php echo $total_links; ?> link<?php echo $total_links !== 1 ? 's' : ''; ?>
            <?php else : ?>
                Viewing <?php echo $offset + 1; ?>&ndash;<?php echo $offset + $showing_count; ?> of <?php echo $total_links; ?> link<?php echo $total_links !== 1 ? 's' : ''; ?>
            <?php endif; ?>
        </span>
        <form class="per-page-form" method="get">
            <label for="per_page_select">Show:</label>
            <select id="per_page_select" name="per_page" onchange="this.form.submit()">
                <?php foreach ( array( 10, 25, 50, 'all' ) as $opt ) :
                    $label    = $opt === 'all' ? 'All' : $opt;
                    $selected = (string) $per_page === (string) $opt ? ' selected' : '';
                ?>
                <option value="<?php echo $opt; ?>"<?php echo $selected; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="paged" value="1" />
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th width="35%">Short Link</th>
                <th width="65%">Title</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $query as $row ) :
            $short = $site . '/' . $row->keyword;
        ?>
            <tr>
                <td class="short-url-cell">
                    <a href="<?php echo yourls_esc_url( $row->url ); ?>" target="_blank"><?php echo yourls_esc_html( $short ); ?></a>
                    <button class="copy-btn" data-copy="<?php echo yourls_esc_attr( $short ); ?>" onclick="copyShortUrl(this)">Copy</button>
                </td>
                <td>
                    <a href="<?php echo yourls_esc_url( $row->url ); ?>" target="_blank"><?php echo yourls_esc_html( $row->title ?: $row->url ); ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ( $per_page !== 'all' && $total_pages > 1 ) : ?>
    <div class="pagination">
        <?php if ( $current_page > 1 ) : ?>
            <a href="<?php echo linkstats_page_url( 1, $per_page ); ?>" title="First">&laquo;</a>
            <a href="<?php echo linkstats_page_url( $current_page - 1, $per_page ); ?>" title="Previous">&lsaquo;</a>
        <?php else : ?>
            <span class="disabled">&laquo;</span>
            <span class="disabled">&lsaquo;</span>
        <?php endif; ?>

        <?php
        // Show up to 5 page numbers centred on current page
        $range = 2;
        $start = max( 1, $current_page - $range );
        $end   = min( $total_pages, $current_page + $range );
        for ( $p = $start; $p <= $end; $p++ ) :
        ?>
            <?php if ( $p === $current_page ) : ?>
                <span class="current"><?php echo $p; ?></span>
            <?php else : ?>
                <a href="<?php echo linkstats_page_url( $p, $per_page ); ?>"><?php echo $p; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ( $current_page < $total_pages ) : ?>
            <a href="<?php echo linkstats_page_url( $current_page + 1, $per_page ); ?>" title="Next">&rsaquo;</a>
            <a href="<?php echo linkstats_page_url( $total_pages, $per_page ); ?>" title="Last">&raquo;</a>
        <?php else : ?>
            <span class="disabled">&rsaquo;</span>
            <span class="disabled">&raquo;</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <p>No links available.</p>
    <?php endif; ?>
</div>

<site-footer>
    Powered by <a href="https://yourls.org" target="_blank">YOURLS</a> &mdash;
    Hosted by <a href="https://www.dreamhost.com" target="_blank">DreamHost</a>
</site-footer>

<script>
function copyShortUrl(btn) {
    var text = btn.getAttribute('data-copy');
    navigator.clipboard.writeText(text).then(function() {
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.textContent = 'Copy';
            btn.classList.remove('copied');
        }, 2000);
    });
}
</script>
</body>
</html>
