<?php
/*
 * Plugin Name: ii-math: MathML to SVG converter
 * Plugin URI: https://iishanto.com
 * Description: This plugin will convert MathML expression to SVG compilable expression
 * Version: 1.1.1
 * Author: Sharif Hasan
 * Author URI: https://facebook.com/io.shanto
 */


  /*
    Adding custom style
    */
    function block_copy() {
      echo '<style>
	  /*Added by ii-math plugin*/
	.ii-math{
	  max-width: 100% !important;
    overflow: auto;
    display: inline-block;
    vertical-align: middle;
	}
		</style>';
    }
    add_action( 'wp_head', 'block_copy' );

    /*EnD*/

function rmdir_recursive( $dir ) {
  if ( !is_dir( $dir ) ) return;
  foreach ( scandir( $dir ) as $file ) {
    if ( '.' === $file || '..' === $file ) continue;
    if ( is_dir( "$dir/$file" ) )rmdir_recursive( "$dir/$file" );
    else unlink( "$dir/$file" );
  }
  rmdir( $dir );
}


if ( isset( $_GET[ "ii-math-cache-clear" ] ) ) {
  rmdir_recursive( ABSPATH . "/wp-content/mathml-svg-cache/" );
  echo( "<h3 align='center'> SVG math cache cleared</h3>" );
}


function clean( $dir ) {
  if ( !is_dir( $dir ) ) return;
  foreach ( scandir( $dir ) as $file ) {
    if ( '.' === $file || '..' === $file ) continue;
    if ( !is_dir( "$dir/$file" ) ) {
      unlink( "$dir/$file" );
    }
  }
}

function cache( $do, $id, $save = false ) {
  //$t=microtime();
  $do = json_encode( $do );
  $hs = md5( $do );
  //$tt=microtime();
  $path = ABSPATH . "/wp-content/mathml-svg-cache/" . $id . "/";

  if ( !is_dir( ABSPATH . "/wp-content/mathml-svg-cache/" ) ) {
    mkdir( $path, 0777, true );
  }

  if ( !is_dir( $path ) ) {
    mkdir( $path, 0777, true );
    return false;
  }

  if ( $save != false ) {
    clean( $path );
    $save = json_encode( $save );
    if ( $save == "" || $save == NULL ) return false;
    $f = fopen( $path . $hs . ".json", "w+" );
    fwrite( $f, $save );
    fclose( $f );
    return true;
  }

  if ( !file_exists( $path . $hs . ".json" ) ) {
    return false;
  } else {
    return file_get_contents( $path . $hs . ".json" );
  }
}

function mathmlsvg( $eq, $id, $url = "https://mathjax2svg.herokuapp.com/convert" ) {
  $ck = cache( $eq[ 'math' ], $id );
  if ( $ck != false ) {
    //echo "Serving already cached svgs<br>";
    return json_decode( $ck, 1 );
  }
  $ch = curl_init( $url );
  $payload = json_encode( $eq );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
  curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type:application/json' ) );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  $result = curl_exec( $ch );
  //echo curl_error($ch);
  curl_close( $ch );

  $res = json_decode( $result, 1 );
  $ot = array();
  $i = 0;
  foreach ( $res as $key => $value ) {
    $ot[ $i ] = "<span class='ii-math'>" . $value[ 'svg' ] . "</span>";
    $i++;
  }
  if ( empty( $ot ) ) return false;
  cache( $eq[ 'math' ], $id, $ot );
  return $ot;
}

add_filter( 'the_content', 'filter_the_content_in_the_main_loop', 100000000, 1 );

function filter_the_content_in_the_main_loop( $content ) {

  // Check if we're inside the main loop in a single Post.
  if ( is_singular() && in_the_loop() && is_main_query() ) {
    //echo get_the_ID()."<br>";
    $matches = array();
    $has_fn = preg_match_all( '/\$(.*?)\$/', $content, $matches );
    if ( !$has_fn ) {
      return $content;
    }
    $eq = array();
    $eq[ "math" ] = $matches[ 1 ];
    $ttl = count( $matches[ 1 ] );

    for ( $i = 0; $i < $ttl; $i++ ) {
      $eq[ "math" ][ $i ] = html_entity_decode( $eq[ "math" ][ $i ] );
    }
    $eq[ "format" ] = "TeX";
    $eq[ "svg" ] = true;


    $res = mathmlsvg( $eq, get_the_ID() );
    if ( !$res ) return $content;
    if ( !isset( $res ) ) {
      return $content;
    }



    $content = str_replace( $matches[ 0 ], $res, $content );
  }

  return $content;
}


add_action( 'save_post', 'remove_cache', 10, 3 );

function remove_cache( $post_id, $post, $update ) {
  $path = ABSPATH . "/wp-content/mathml-svg-cache/" . $post_id . "/";
  rmdir_recursive( $path );
}


/* add a link to the WP Toolbar
 */
function ccfm_toolbar_link( $wp_admin_bar ) {
  $url = add_query_arg( '_wpnonce', wp_create_nonce( 'ii-math-cache-clear' ), admin_url() . '?ii-math-cache-clear=1' );
  $args = array(
    'id' => 'ii-math-link',
    'title' => 'Clear Cached Maths',
    'href' => $url,
    'meta' => array(
      'title' => 'Clear svg math cache'
    )
  );
  $wp_admin_bar->add_node( $args );
}
add_action( 'admin_bar_menu', 'ccfm_toolbar_link', 999 );


?>
