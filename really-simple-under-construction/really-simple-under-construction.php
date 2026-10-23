<?php
/*
Plugin Name: Really Simple Under Construction Page
Plugin URI: https://wordpress.org/plugins/really-simple-under-construction/
Description: ...or Hide From Google functionality. Adds a really simple version of a Under Construction page. Use whitelisted IP and secret word to skip under construction page. Also good to have when you want to hide the site from search bots when developing a new site.
Version: 1.6.4
Author: jonashjalmarsson
Author URI: http://jonashjalmarsson.se
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: really-simple-under-construction
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die( '' );

/* ADD INIT ACTION */
add_action( 'plugins_loaded', 'rsuc_init' );
function rsuc_init( $rsuc_late = false ) {

    /* IGNORE RSUC IF FOLLOWING */

    // Never intercept WP-CLI or cron — both run unauthenticated and would
    // otherwise hit the UC render path and kill the command/cron job.
    if ( defined( 'WP_CLI' ) && WP_CLI ) { return; }
    if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || wp_doing_cron() ) { return; }

    // if enabled NOT is checked
    if ( 1 != get_option( 'rsuc-enable' ) ) { return; }

    // if logged in
    if ( is_user_logged_in() ) { return; }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
    $current_url = $http_host . $request_uri;
    $admin_url   = get_admin_url();

    // if login page or admin page. Earlier versions compared $current_url
    // (host + path, no scheme) against $admin_url (full URL), which never
    // matched — so any /wp-admin/* path other than the bare /wp-admin/
    // root fell through to the UC page. Match on REQUEST_URI prefix
    // instead so admin-post.php, admin-ajax.php and friends are reachable.
    if ( ( strpos( wp_login_url(), $GLOBALS['pagenow'] ) !== false ) ||
         ( strpos( $request_uri, '/wp-admin/' ) === 0 ) ||
         ( $request_uri == '/admin' ) ||
         ( $request_uri == '/admin/' ) ||
         ( $request_uri == '/wp-admin' ) ) {
        return;
    }

    // if api call. The 1.4.6 version checked $GLOBALS['PHP_SELF'] which is
    // never populated, so the wp-json bypass never fired. $_SERVER['PHP_SELF']
    // would also miss because Apache mod_rewrite resolves /wp-json/* to
    // /index.php. The REQUEST_URI is what actually carries the public path.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public-route check, no state mutation.
    // Match the PATH only (not the query string): "/valfri/?x=/wp-json/" is not an API call.
    $request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
    $rest_path    = (string) wp_parse_url( home_url( '/wp-json/' ), PHP_URL_PATH );
    if ( '' !== $rest_path && 0 === strpos( $request_path, $rest_path ) ) { return; }

    // WooCommerce callbacks (?wc-api=...) are handled by WooCommerce on parse_request
    // and end with die(), so they never reach template_redirect. Anything that does
    // reach it is a normal page request that merely carries a wc-api parameter (or
    // WooCommerce is not installed), and must NOT bypass the UC page. So instead of
    // returning here, re-run the check late.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public-route check, no state mutation.
    if ( ( isset( $_REQUEST['wc-api'] ) || false !== strpos( $request_path, '/wc-api/' ) ) && ! $rsuc_late ) {
        add_action( 'template_redirect', function () { rsuc_init( true ); }, 0 );
        return;
    }

    // if enable-homepage is enabled and is_front_page
    if ( 1 == get_option( 'rsuc-enable-homepage' ) && $request_uri == '/' ) { return; }

    // if ip in whitelist
    $user_ip = rsucGetIPAddress();
    $ip_list = get_option( 'rsuc-ip' );
    if ( ! empty( $ip_list ) && ! empty( $user_ip ) ) {
        $ip_array       = explode( "\n", $ip_list );
        $clean_ip_array = array();
        foreach ( $ip_array as $ip ) {
            // Take the first whitespace-delimited token of each line so trailing
            // comments after the IP are ignored, then validate with filter_var
            // (accepts both IPv4 and IPv6, rejects out-of-range like 999.x.x.x).
            if ( preg_match( '/^\s*(\S+)/', $ip, $m ) && filter_var( $m[1], FILTER_VALIDATE_IP ) ) {
                $clean_ip_array[] = $m[1];
            }
        }

        if ( in_array( $user_ip, $clean_ip_array, true ) ) {
            return; // user is whitelisted
        }
    }

    // Filter: lets add-ons (e.g. PRO's CIDR support) extend the whitelist
    // without modifying Free. Return true to bypass the UC page based on IP.
    if ( apply_filters( 'rsuc_ip_whitelisted', false, $user_ip ) ) {
        return;
    }


    /* SHOW RSUC IF FOLLOWING */

    // Filter: lets add-ons (e.g. PRO's multi-bypass URLs) decide that the
    // current request is trusted. Return true to skip the UC page.
    if ( apply_filters( 'rsuc_should_bypass', false ) ) {
        return;
    }

    $secretword = get_option( 'rsuc-secret-word' );
    if ( $secretword != '' ) {
        $rsuc_cookie_name     = 'rsuc_cookie';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the secret word IS the bypass token; nonces don't apply to a public bypass URL.
        $user_url_rsuc_secret = rsuc_url_has_secret( $secretword );

        // check cookie value (timing-safe compare)
        $trusted_user = false;
        if ( isset( $_COOKIE[ $rsuc_cookie_name ] ) ) {
            $cookie_val = sanitize_text_field( wp_unslash( $_COOKIE[ $rsuc_cookie_name ] ) );
            if ( hash_equals( (string) $secretword, $cookie_val ) ) {
                $trusted_user = true;
            }
        }
        // check url argument (also when an old, no longer valid cookie is present,
        // otherwise a changed secret word locks out anyone holding the old cookie)
        if ( ! $trusted_user && $user_url_rsuc_secret ) {
            $trusted_user = true;

            // set cookie
            $cookie_time        = 30;
            $option_cookie_time = get_option( 'rsuc-cookie-time' );
            if ( isset( $option_cookie_time ) && is_numeric( $option_cookie_time ) && $option_cookie_time > 0 && $option_cookie_time < 366 ) {
                $cookie_time = (int) $option_cookie_time;
            }
            // PHP 7.3+ array signature: Secure (when SSL), HttpOnly, SameSite=Lax.
            setcookie(
                $rsuc_cookie_name,
                $secretword,
                array(
                    'expires'  => time() + ( 86400 * $cookie_time ),
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                )
            );

            // #351: the secret word is the query parameter's NAME, so it ends up
            // in the access log's request line. Redirect to the same URL without
            // it right after the cookie is set, so it leaves the address bar,
            // browser history and Referer. The cookie rides on the redirect.
            if ( ! headers_sent() ) {
                wp_safe_redirect( rsuc_url_without_secret( $secretword ) );
                exit;
            }
        }

        if ( ! $trusted_user ) {
            rsuc_render_uc_page();
        }
    }
    // else render of no secret word is set
    else {
        rsuc_render_uc_page();
    }
}

/**
 * Is the secret word present as a query parameter NAME in the URL?
 * PHP rewrites "." and " " in $_GET keys to "_", so a word like "my.secret" is
 * never found with isset( $_GET[ $word ] ). Compare against the raw query string.
 */
function rsuc_url_has_secret( $secretword ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the secret word IS the bypass token.
    if ( isset( $_GET[ $secretword ] ) ) { return true; }
    $query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '';
    foreach ( explode( '&', $query ) as $pair ) {
        $parts = explode( '=', $pair, 2 );
        if ( hash_equals( (string) $secretword, urldecode( $parts[0] ) ) ) { return true; }
    }
    return false;
}

/**
 * The current URL with the secret word removed from the query string.
 * remove_query_arg() goes through parse_str(), which rewrites "." and " " in keys
 * to "_", so for a word like "my.secret" the word would stay in the address bar.
 * Filter the raw query string instead and keep every other pair as it was.
 */
function rsuc_url_without_secret( $secretword ) {
    $uri   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
    $path  = (string) wp_parse_url( $uri, PHP_URL_PATH );
    $query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
    $keep  = array();
    foreach ( explode( '&', $query ) as $pair ) {
        if ( '' === $pair ) { continue; }
        // Let PHP do the same key normalisation that rsuc_url_has_secret() relies on
        // ("." " " "[" ...), so every pair that lets someone in is removed, whatever form it has.
        $parsed = array();
        parse_str( $pair, $parsed );
        $word_keys = array();
        parse_str( rawurlencode( (string) $secretword ) . '=1', $word_keys );
        $hit = array_key_exists( (string) $secretword, $parsed ) || array_intersect_key( $parsed, $word_keys );
        if ( ! $hit ) { $keep[] = $pair; }
    }
    return $path . ( $keep ? '?' . implode( '&', $keep ) : '' );
}

/**
 * Render the Under Construction page with a 503 Service Unavailable response.
 * Replaces the bare die() in 1.4.6 — same visual outcome, but search engines
 * see "temporarily unavailable" instead of a 200 OK with a thin page, and
 * proxies/CDN caches won't memo-ize the placeholder.
 */
function rsuc_render_uc_page() {
    status_header( 503 );
    nocache_headers();
    header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
    header( 'Retry-After: 3600' );
    // Site admins explicitly enter HTML for the UC page (manage_options
    // capability required to save). This is a rendered template, not user
    // input — emitting raw is the historical contract since 0.1.
    //
    // Filter `rsuc_html_output` lets add-ons post-process the HTML before
    // it goes to the wire (e.g. PRO's shortcode resolution for
    // [rsucp_email_form]). Default behavior is unchanged.
    $html = apply_filters( 'rsuc_html_output', get_option( 'rsuc-html' ) );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $html;
    exit;
}

/* ADD ADMIN PAGE */
add_action( 'admin_menu', 'rsuc_menu' );
function rsuc_menu() {
    add_submenu_page(
        'options-general.php',
        'Really Simple Under Construction',
        'Really Simple Under Construction',
        'manage_options',
        'rsuc-submenu-page',
        'rsuc_submenu_page_callback'
    );
    add_action( 'admin_init', 'rsuc_plugin_settings' );
}

/* REGISTER SETTINGS */
function rsuc_plugin_settings() {
    register_setting( 'rsuc-settings-group', 'rsuc-enable',          array( 'sanitize_callback' => 'rsuc_sanitize_bool' ) );
    register_setting( 'rsuc-settings-group', 'rsuc-enable-homepage', array( 'sanitize_callback' => 'rsuc_sanitize_bool' ) );
    register_setting( 'rsuc-settings-group', 'rsuc-secret-word',     array( 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'rsuc-settings-group', 'rsuc-cookie-time',     array( 'sanitize_callback' => 'rsuc_sanitize_cookie_days' ) );
    // The rsuc-html field accepts a full HTML document (DOCTYPE + <html>/
    // <head>/<style>/<body>) that the plugin emits raw on the Under
    // Construction page. wp_kses_post is too strict — it strips structural
    // tags and breaks the historical contract that has held since 0.1.
    // Instead, strip only the demonstrably dangerous bits: <script> tags
    // and on* event handlers. Defense-in-depth against a compromised admin
    // account, without breaking custom UC pages.
    register_setting( 'rsuc-settings-group', 'rsuc-html',             array( 'sanitize_callback' => 'rsuc_sanitize_html_lite' ) );
    register_setting( 'rsuc-settings-group', 'rsuc-ip',              array( 'sanitize_callback' => 'rsuc_sanitize_ip_list' ) );
}

function rsuc_sanitize_bool( $value ) {
    return ( '1' === (string) $value ) ? '1' : '';
}

function rsuc_sanitize_cookie_days( $value ) {
    $value = absint( $value );
    if ( $value < 0 || $value > 365 ) {
        return 30;
    }
    return $value;
}

/**
 * Light-touch sanitizer for the Under Construction HTML field. Preserves
 * DOCTYPE, <html>, <head>, <style>, <body> and any presentational markup,
 * but removes <script> blocks and HTML event handlers (onclick, onload,
 * onmouseover, etc) so a compromised admin account cannot inject JS that
 * runs for visitors and other admins.
 */
function rsuc_sanitize_html_lite( $value ) {
    $value = (string) $value;
    // Strip <script>...</script> (case-insensitive, multi-line, lazy).
    $value = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $value );
    // Strip self-closing or unclosed <script ... > stubs.
    $value = preg_replace( '#<script\b[^>]*/?>#i', '', $value );
    // Strip on* event handler attributes ("onclick=...", "onload=...", etc).
    $value = preg_replace( '#\son\w+\s*=\s*"[^"]*"#i', '', $value );
    $value = preg_replace( "#\son\w+\s*=\s*'[^']*'#i", '', $value );
    $value = preg_replace( '#\son\w+\s*=\s*[^\s>]+#i', '', $value );
    // Strip javascript: URIs in href/src/etc.
    $value = preg_replace( '#(href|src|action|formaction)\s*=\s*"\s*javascript:[^"]*"#i', '$1=""', $value );
    $value = preg_replace( "#(href|src|action|formaction)\s*=\s*'\s*javascript:[^']*'#i", '$1=""', $value );
    return $value;
}

function rsuc_sanitize_ip_list( $value ) {
    // Multi-line textarea: keep newlines, sanitize each line, drop control chars.
    $lines  = preg_split( "/\r\n|\r|\n/", (string) $value );
    $clean  = array();
    foreach ( $lines as $line ) {
        $clean[] = sanitize_text_field( $line );
    }
    return implode( "\n", $clean );
}

/* SHOW ADMIN PAGE */
function rsuc_submenu_page_callback() {
    $current_ip = rsucGetIPAddress();
    ?>
<style>
    form { max-width: 600px; }
    section { margin-bottom: 60px; }
    h3 { margin-top: 20px; font-size: 13px; }
    input[type='text'],
    input[type='number'] { margin-bottom: 10px; }
</style>
<div class="wrap">
<h1>Really Simple Under Construction</h1>
<?php
/**
 * Fires right after the page heading on the settings page, before the
 * settings form. Add-on plugins can use this to render a tab nav, an
 * upgrade banner, or other UI between the heading and the form.
 *
 * @since 1.5.1
 */
do_action( 'rsuc_render_after_heading' );
?>
<form method="post" action="options.php">
    <?php settings_fields( 'rsuc-settings-group' ); ?>
    <?php do_settings_sections( 'rsuc-settings-group' ); ?>
    <section>
        <h2><?php esc_html_e( 'General Settings', 'really-simple-under-construction' ); ?></h2>
        <p><?php esc_html_e( "Block users from seeing your site by enabling the under construction page below. Logged in users still can see the website. Use secret word and IP whitelisting to grant access to users without logging in.", 'really-simple-under-construction' ); ?></p>

        <input type="checkbox" name="rsuc-enable" value="1"<?php checked( 1 == get_option( 'rsuc-enable' ) ); ?> />
        <span><?php esc_html_e( "Enable the 'Under Construction page'", 'really-simple-under-construction' ); ?></span><br/>

        <input type="checkbox" name="rsuc-enable-homepage" value="1"<?php checked( 1 == get_option( 'rsuc-enable-homepage' ) ); ?> />
        <span>
            <?php esc_html_e( 'Make WordPress Static Homepage visible:', 'really-simple-under-construction' ); ?>
            <?php
            $front_id = (int) get_option( 'page_on_front' );
            if ( $front_id ) {
                printf(
                    '<i><a href="%1$s">%2$s</a></i>',
                    esc_url( get_edit_post_link( $front_id ) ),
                    esc_html( get_the_title( $front_id ) )
                );
            } else {
                echo '<i>' . esc_html__( 'Not set', 'really-simple-under-construction' ) . '</i>';
            }
            ?>
        </span>

        <h3><?php esc_html_e( 'HTML to show as Under Construction page', 'really-simple-under-construction' ); ?></h3>
        <textarea name="rsuc-html" style="width: 600px; max-width: 100%; height: 200px;"><?php echo esc_textarea( get_option( 'rsuc-html' ) ); ?></textarea>
    </section>

    <section>
        <h2><?php esc_html_e( 'Secret Word', 'really-simple-under-construction' ); ?></h2>
        <p><?php esc_html_e( 'Add your Secret Word to create a link to use and by-pass the Under Construction page, a cookie is saved to remember that browser. Clear the secret word or uncheck the enable plugin to disable the Under Construction site. When you change the secret all previous cookies will be obsolete.', 'really-simple-under-construction' ); ?></p>
        <h3><?php esc_html_e( 'Secret Word to by-pass for one browser', 'really-simple-under-construction' ); ?></h3>
        <input type="text" name="rsuc-secret-word" value="<?php echo esc_attr( get_option( 'rsuc-secret-word' ) ); ?>" /><br />
        <?php
        $secret = get_option( 'rsuc-secret-word' );
        if ( '' !== $secret ) {
            $bypass_url = add_query_arg( $secret, '', home_url( '/' ) );
            // add_query_arg appends "=", e.g. "?mysecret=" — strip the trailing "=" for the prettier link.
            $bypass_url = rtrim( $bypass_url, '=' );
            ?>
            <p><i>
                <?php
                printf(
                    /* translators: %s: bypass URL */
                    esc_html__( 'Use the URL %s to show the website.', 'really-simple-under-construction' ),
                    '<a href="' . esc_url( $bypass_url ) . '">' . esc_html( $bypass_url ) . '</a>'
                );
                ?>
            </i></p>
            <?php
        }
        ?>

        <h3><?php esc_html_e( 'Set number of days the site should be remembered by the web browser.', 'really-simple-under-construction' ); ?></h3>
        <input type="number" name="rsuc-cookie-time" value="<?php echo esc_attr( get_option( 'rsuc-cookie-time' ) ); ?>" /><br />
        <i><?php esc_html_e( 'Default is 30 days if nothing is set. Can not be larger than 365 days.', 'really-simple-under-construction' ); ?></i>
    </section>

    <section>
        <h2><?php esc_html_e( 'Whitelist IP', 'really-simple-under-construction' ); ?></h2>

        <p><?php echo wp_kses_post( __( 'Add a user IP to the whitelist, one IP per row.<br />Comment after the IP to remember what user or service is using the IP. The first non-whitespace token on each line is taken as the IP.', 'really-simple-under-construction' ) ); ?></p>

        <h3><?php esc_html_e( 'User IP addresses to whitelist', 'really-simple-under-construction' ); ?></h3>
        <textarea name="rsuc-ip" id="rsuc-ip" style="width: 600px; max-width: 100%; height: 200px;"><?php echo esc_textarea( get_option( 'rsuc-ip' ) ); ?></textarea>
        <p><i><?php esc_html_e( 'Add your IP address to whitelist.', 'really-simple-under-construction' ); ?> <span style="cursor: pointer; text-decoration: underline;" id="rsuc-append-link"><?php echo esc_html( $current_ip ); ?></span></i></p>
        <script>
            ( function () {
                var btn = document.getElementById( 'rsuc-append-link' );
                var ta  = document.getElementById( 'rsuc-ip' );
                if ( ! btn || ! ta ) { return; }
                btn.addEventListener( 'click', function ( ev ) {
                    ev.preventDefault();
                    var prefix = ta.value !== '' ? '\n' : '';
                    ta.value += prefix + <?php echo wp_json_encode( $current_ip ); ?> + ' // my ip';
                } );
            } )();
        </script>
    </section>
    <?php submit_button(); ?>

</form>
</div>
    <?php
}


/**
 * Best-effort client IP. Mirrors common header-fallback chain; treats the
 * left-most XFF entry as the client (XFF can be spoofed, so this should
 * never be the only auth signal — it's a convenience for IP whitelisting
 * which the admin opts into knowingly).
 */
function rsucGetIPAddress() {
    $candidates = array();
    if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
        $candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
    }
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $xff = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        // XFF can be a comma-list; take the left-most entry.
        $first = trim( strtok( $xff, ',' ) );
        if ( '' !== $first ) {
            $candidates[] = $first;
        }
    }
    if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
    }
    foreach ( $candidates as $candidate ) {
        if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
            return $candidate;
        }
    }
    return '';
}


/* add settings link in plugin-list */
add_filter( 'plugin_action_links_really-simple-under-construction/really-simple-under-construction.php', 'rsuc_settings_link' );
function rsuc_settings_link( $links ) {
    $url = esc_url( add_query_arg(
        'page',
        'rsuc-submenu-page',
        get_admin_url() . 'options-general.php'
    ) );
    $settings_link = '<a href="' . $url . '">' . esc_html__( 'Settings', 'really-simple-under-construction' ) . '</a>';
    array_push( $links, $settings_link );
    return $links;
}

/**
 * Pro upsell — a simple "Get Pro" link in the Plugins-screen row, plus a
 * small pill on the settings page with two plain links: the Pro landing
 * page, and a direct download of the Pro .zip.
 *
 * Both are ordinary <a href> links the user clicks themselves. This plugin
 * never downloads, installs, activates or updates anything on their behalf —
 * that would break wp.org guideline 8 ("serving updates or otherwise
 * installing plugins […] from servers other than WordPress.org's"). It also
 * contacts no external server of its own: no lemon.js, no phone-home, no
 * inline script. The purchase and license activation happen on the landing
 * page or inside the Pro plugin's own License tab once installed.
 */
const RSUC_PRO_LANDING_URL = 'https://jonashjalmarsson.se/plugins/really-simple-under-construction-pro/';

/**
 * Direct download of the Pro .zip, for people who would rather install first
 * and buy from inside the Pro plugin. Manual upload only — see above.
 */
const RSUC_PRO_DOWNLOAD_URL = 'https://plugins.jonashjalmarsson.se/really-simple-under-construction-pro/really-simple-under-construction-pro.zip';

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    if ( class_exists( 'RSUCP\\DesignPage' ) ) {
        return $links; // Pro is loaded — no need to upsell.
    }
    // #673 (Idde, 2026-10-08): källmarkör sa klick->landningssida kan
    // mätas i serverloggen (GA ar consent-gatad). Query-strang, inte
    // hash, eftersom detta lases server-sidan - verifierat att
    // landningssidan har en korrekt canonical-tag utan query, sa GSC
    // inte splittrar sok-datan (samma fallgrop som drojsmalsranta).
    $links[] = '<a href="' . esc_url( add_query_arg( 'ref', 'rsuc-admin-row', RSUC_PRO_LANDING_URL ) ) . '" target="_blank" rel="noopener">'
        . esc_html__( 'Get Pro', 'really-simple-under-construction' )
        . '</a>';
    return $links;
} );

add_action( 'rsuc_render_after_heading', function () {
    if ( class_exists( 'RSUCP\\DesignPage' ) ) {
        return; // Pro is loaded — hide the upsell.
    }
    ?>
    <div class="rsuc-pro-pill" style="background:#f5f7ff;border:1px solid #d6dffd;border-radius:6px;padding:1em 1.25em;margin:1em 0 1.5em;max-width:760px;">
        <span style="display:inline-block;font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#3858e9;background:#e7ecfb;padding:.15em .55em;border-radius:3px;margin-bottom:.6em;"><?php esc_html_e( 'Pro upgrade', 'really-simple-under-construction' ); ?></span>
        <p style="margin:.2em 0 .8em;"><?php esc_html_e( 'Design editor, scheduled launch, email signup, multiple bypass URLs and CIDR-range IP whitelisting.', 'really-simple-under-construction' ); ?></p>
        <p style="margin:0 0 .6em;">
            <a class="button" href="<?php echo esc_url( add_query_arg( 'ref', 'rsuc-admin-pill', RSUC_PRO_LANDING_URL ) ); ?>" target="_blank" rel="noopener">
                <?php esc_html_e( 'Learn more →', 'really-simple-under-construction' ); ?>
            </a>
            <a class="button" href="<?php echo esc_url( RSUC_PRO_DOWNLOAD_URL ); ?>" target="_blank" rel="noopener" style="margin-left:.4em;">
                <?php esc_html_e( 'Download Pro (.zip)', 'really-simple-under-construction' ); ?>
            </a>
        </p>
        <p style="margin:0;font-size:12px;color:#646970;">
            <?php esc_html_e( 'Downloaded the zip? Install it yourself under Plugins → Add Plugin → Upload Plugin, then buy or paste your license key on the Pro plugin\'s License tab.', 'really-simple-under-construction' ); ?>
        </p>
    </div>
    <?php
} );

/* REVIEW REQUEST (1.6.3, #673)
 *
 * One small, dismissible card on this plugin's OWN settings page — never a
 * site-wide admin notice. Shown only to users who can manage_options, only
 * after the under-construction page has been switched on and the settings
 * page has been used for at least 7 days. "Maybe later" hides it for 30 days,
 * "Don't ask again" for good (both per user). No external requests are made;
 * the button is a plain link to the plugin's review page on wordpress.org.
 */
define( 'RSUC_REVIEW_URL', 'https://wordpress.org/support/plugin/really-simple-under-construction/reviews/#new-post' );

/**
 * Handle the "Maybe later" / "Don't ask again" links (nonce + capability checked).
 */
add_action( 'admin_init', function () {
    if ( ! isset( $_GET['rsuc_review'], $_GET['_wpnonce'] ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $action = sanitize_key( wp_unslash( $_GET['rsuc_review'] ) );
    if ( ! in_array( $action, array( 'later', 'never' ), true ) ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'rsuc_review_' . $action ) ) {
        return;
    }
    $user_id = get_current_user_id();
    if ( 'never' === $action ) {
        update_user_meta( $user_id, 'rsuc_review_dismissed', 1 );
    } else {
        update_user_meta( $user_id, 'rsuc_review_snooze_until', time() + ( 30 * DAY_IN_SECONDS ) );
    }
    wp_safe_redirect( admin_url( 'options-general.php?page=rsuc-submenu-page' ) );
    exit;
} );

add_action( 'rsuc_render_after_heading', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $user_id = get_current_user_id();
    if ( get_user_meta( $user_id, 'rsuc_review_dismissed', true ) ) {
        return;
    }
    if ( (int) get_user_meta( $user_id, 'rsuc_review_snooze_until', true ) > time() ) {
        return;
    }
    // Only ask someone who has actually switched the page on.
    if ( 1 != get_option( 'rsuc-enable' ) ) {
        return;
    }
    // Remember when the settings page was first opened; ask after 7 days.
    $first_seen = (int) get_option( 'rsuc-review-first-seen', 0 );
    if ( ! $first_seen ) {
        update_option( 'rsuc-review-first-seen', time(), false );
        return;
    }
    if ( time() - $first_seen < 7 * DAY_IN_SECONDS ) {
        return;
    }
    $later_url = wp_nonce_url( admin_url( 'options-general.php?page=rsuc-submenu-page&rsuc_review=later' ), 'rsuc_review_later' );
    $never_url = wp_nonce_url( admin_url( 'options-general.php?page=rsuc-submenu-page&rsuc_review=never' ), 'rsuc_review_never' );
    ?>
    <div class="rsuc-review-card" style="background:#fff;border:1px solid #dcdcde;border-left:4px solid #2271b1;border-radius:4px;padding:.8em 1.25em;margin:1em 0 1.5em;max-width:760px;">
        <p style="margin:.2em 0 .7em;"><?php esc_html_e( 'Enjoying Really Simple Under Construction? A short review on wordpress.org helps other people find it.', 'really-simple-under-construction' ); ?></p>
        <p style="margin:0;">
            <a class="button button-primary" href="<?php echo esc_url( RSUC_REVIEW_URL ); ?>" target="_blank" rel="noopener">
                <?php esc_html_e( 'Leave a review', 'really-simple-under-construction' ); ?>
            </a>
            <a class="button" href="<?php echo esc_url( $later_url ); ?>" style="margin-left:.4em;">
                <?php esc_html_e( 'Maybe later', 'really-simple-under-construction' ); ?>
            </a>
            <a href="<?php echo esc_url( $never_url ); ?>" style="margin-left:.8em;font-size:12px;">
                <?php esc_html_e( "Don't ask again", 'really-simple-under-construction' ); ?>
            </a>
        </p>
    </div>
    <?php
} );
