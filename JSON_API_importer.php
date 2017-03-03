<?php 
/* JSON API 1.1.1 */

/* functions.php

add_action('json_api_query_args', 'my_query_args');
    
function my_query_args($args) {
  $args['post_status'] = array('draft', 'future', 'publish');
  return $args;
}

*/

/* json-api/models/post.php

...

var $content;         // String (modified by read_more query var)
var $content_plain;   // String

...

$content = get_the_content($json_api->query->read_more);
$this->content_plain = $content;
$content = apply_filters('the_content', $content);

...

*/


add_action( 'init', 'post_importer', 99 );
function post_importer() {
  if( isset( $_GET['_action'] ) )
  {
    session_start();
    error_reporting(E_ALL ^ E_NOTICE);
    ini_set('display_errors', 1);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>Post Importer</title></head><body>';
    echo '<a href="?_action=post_importer">Inicio</a><hr />';
    switch($_GET['_action']){
      case "post_importer":
        echo '<a href="?_action=post_importer_run">NEXT</a><hr />';
        echo '<a href="?_action=post_importer_run&run">NEXT AND RUN</a><hr />';
        if(!empty($_POST))
        {
          $_SESSION["PI_JSON"] = $_POST["PI_JSON"];
          $_SESSION["PI_PAGE"] = (int) $_POST["PI_PAGE"];
          $_SESSION["PI_CONT"] = (int) $_POST["PI_CONT"];
          $_SESSION["PI_TYPE"] = $_POST["PI_TYPE"];
          $_SESSION["PI_STAT"] = $_POST["PI_STAT"];
        }
        $PI_JSON = empty($_SESSION["PI_JSON"]) ? '' : $_SESSION["PI_JSON"];
        $PI_PAGE = empty($_SESSION["PI_PAGE"]) ? 1 : $_SESSION["PI_PAGE"];
        $PI_CONT = empty($_SESSION["PI_CONT"]) ? 1 : $_SESSION["PI_CONT"];
        $PI_TYPE = empty($_SESSION["PI_TYPE"]) ? 'post' : $_SESSION["PI_TYPE"];
        $PI_STAT = empty($_SESSION["PI_STAT"]) ? 'any' : $_SESSION["PI_STAT"];
        if(!empty($_POST)) echo 'Actualizado<br />';
        echo '<form action="?_action=post_importer" method="POST">
          <fieldset>
          <legend>JSON API URL:</legend>
          <input type="text" style="width:80%" name="PI_JSON" value="'.$PI_JSON.'" />
          <legend>PAGE:</legend>
          <input type="text" name="PI_PAGE" value="'.$PI_PAGE.'" />
          <legend>TYPE:</legend>
          <input type="text" name="PI_TYPE" value="'.$PI_TYPE.'" />
          <legend>STATUS:</legend>
          <input type="text" name="PI_STAT" value="'.$PI_STAT.'" />
          <legend>COUNT:</legend>
          <input type="text" name="PI_CONT" value="'.$PI_CONT.'" />
        </fieldset>
        <br /><input type="submit" value="Actualizar" />
      </form>';
        echo '<hr />';
      break;
      case "post_importer_run":
        $conflict = false;
        if(!empty($_GET["page"])){
          $_SESSION["PI_PAGE"] = (int) $_GET["page"];
        }
        $url = $_SESSION["PI_JSON"] . "&count=" . $_SESSION["PI_CONT"] . "&status=" . $_SESSION["PI_STAT"] . "&post_type=" . $_SESSION["PI_TYPE"] . "&page=" . $_SESSION["PI_PAGE"];
        $json = json_decode(file_get_contents($url));
        echo 'URL: <a href="' . $url . '&dev=1" target="_blank">'. $url .'</a><br />Total:'. $json->count_total .'<br />Pages:'. $json->pages .'<hr />';
        if(count($json->posts)){
          for ($i=0; $i < count($json->posts); $i++) { $post = $json->posts[$i];   
            $local_post = get_posts(array(
              'name' => $post->slug,
              'posts_per_page' => 1,
              'post_type' => $_SESSION["PI_TYPE"],
              'post_status' => $_SESSION["PI_STAT"]
            ));
            echo 'REMOTE POST: <a href="'. $post->url .'" target="_blank">' . $post->slug . '</a><br />';
            if(empty($local_post)){
              $cats = array();
              for ($j=0; $j < count($post->categories); $j++) { 
                $cats[] = $post->categories[$j]->id;
              }
              $tags = array();
              for ($j=0; $j < count($post->tags); $j++) { 
                $tags[] = $post->tags[$j]->id;
              }
              $meta_input = array();
              if ( ! empty( $post->custom_fields ) ) {
                  foreach ( $post->custom_fields as $field => $value ) {
                    $meta_input[$field] = is_Array($value) && !empty($value) ? $value[0] : $value;
                  }
              }
              $args = array(
                'import_id' => $post->id,
                'post_author' => $post->author->id,
                'post_date' => $post->date,
                'post_content' => $post->content_plain,
                'post_content_filtered' => $post->content,
                'post_title' => $post->title_plain,
                'post_excerpt' => $post->excerpt,
                'post_status' => $post->status,
                'post_type' => $post->type,
                'post_name' => $post->slug,
                'post_modified' => $post->modified,
                'post_category' => $cats,
                'tags_input' => $tags,
                'meta_input' => $meta_input
              );
              
              if(isset($_GET["run"])){
                $post_id = wp_insert_post($args);
                if(is_wp_error($post_id)){
                  echo '<pre>';
                  var_dump($post_id);
                  echo '</pre>';
                  $conflict = true;
                } else {
                  echo 'POST: <a href="'. get_permalink($post_id) .'" target="_blank">' . $post_id . '</a> <a href="'. admin_url("post.php?post=$post_id&action=edit") .'" target="_blank">EDIT</a><br />';

                  if(!empty($post->taxonomy_columnist)){
                    wp_set_object_terms($post_id, $post->taxonomy_columnist[0]->id,'columnist',false);
                  }
                  if(!empty($post->taxonomy_channel)){
                    $cannels = array();
                    for ($y=0; $y < count($post->taxonomy_channel); $y++) { 
                      $cannels[] = $post->taxonomy_channel[$y]->id;
                    }
                    wp_set_object_terms($post_id, $cannels,'channel',false);
                  }

                  

                  if(!empty($post->thumbnail_images)){
                    if ( !function_exists('media_handle_sideload') ) { 
                      require_once(ABSPATH . 'wp-admin/includes/image.php');
                      require_once(ABSPATH . 'wp-admin/includes/file.php');
                      require_once(ABSPATH . 'wp-admin/includes/media.php');
                    }
                    $file = $post->thumbnail_images->full->url;
                    preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
                    $file_array = array();
                    $file_array['name'] = basename( $matches[0] );
                    $file_array['tmp_name'] = download_url( $file );
                    if ( is_wp_error( $file_array['tmp_name'] ) ) {
                      echo '<pre>';
                      var_dump($file_array['tmp_name']);
                      echo '</pre>';
                      $conflict = true;
                    } else {
                      $thumbnail_id = media_handle_sideload( $file_array, $post_id, '' );
                      if ( is_wp_error( $thumbnail_id ) ) {
                        @unlink( $file_array['tmp_name'] );
                        echo '<pre>';
                        var_dump($thumbnail_id);
                        echo '</pre>';
                        $conflict = true;
                      } else {
                        set_post_thumbnail($post_id, $thumbnail_id);
                        $src = wp_get_attachment_url( $thumbnail_id );
                        echo 'FETURED IMAGE: <a href="'. $src .'" target="_blank">' . $src . "</a><br />";
                        $count = impatt($post_id, true);
                        if($count){
                          echo 'IMÁGENES RESTANTES: ' . $count;
                          $conflict = true;
                        } else {
                          echo 'IMÁGENES DONE!';
                        }
                      }
                    }
                  }
                }
              } else {
                echo '<pre>';
                var_dump($args);
                echo '</pre>';
                echo '<a href="?_action=post_importer_run&page=' . $_SESSION["PI_PAGE"] .'&run">RUN</a><br />';
              }
            } else {
              $post_id = $local_post[0]->ID;
              echo 'ALREADY HERE. <a href="'. get_permalink($post_id) .'" target="_blank">' . $local_post[0]->post_name . '</a> <a href="'. admin_url("post.php?post=$post_id&action=edit") .'" target="_blank">EDIT</a><br />';
              echo 'UPDATING META <br />'; 
              if ( ! empty( $post->custom_fields ) ) {
                foreach ( $post->custom_fields as $field => $value ) {
                  echo $field . "::" . $value[0] . "<br />";
                  update_post_meta( $post_id, $field, $value[0] );
                }
              }
            }
            echo "<hr />";
          }
          $nextpage_url = '?_action=post_importer_run&page=' . (((int) $_SESSION["PI_PAGE"]) + 1); 
          echo '<a href="' . $nextpage_url .'">NEXT</a><br />';
          echo '<a href="' . $nextpage_url .'&run">NEXT AND RUN</a><br />';
          echo '<a href="' . $nextpage_url .'&run&auto">NEXT AND AUTO RUN</a><br />';
          if(isset($_GET["auto"]) && !$conflict){
            echo "<script>setTimeout(function(){window.location.href='$nextpage_url&run&auto'} , 200);</script>";
          }
        }
        echo '<hr />';
      break;
    }
    echo '</body></html>';
    die();
  }
}

function impatt( $post_id, $do = false ){
  $post = get_post($post_id);
  $replaced = false;
  $content = $post->post_content;
  $imgs = impatt_get_img_tags($post);
  $total = count($imgs);
  if($total){
    echo "IMÁGENES A IMPORTAR: " . $total;
    echo '<pre>';
    var_dump($imgs);
    echo '</pre>';
    if($do){
      $count = 0;
      for ( $i=0; $i<=20; $i++ ) {
        if (isset($imgs[$i]) && impatt_is_external_file($imgs[$i]) ) {
          $new_img = impatt_sideload( $imgs[$i] , $post_id );
          if ($new_img && impatt_is_external_file($new_img) ) {
            $content = str_replace( $imgs[$i] , $new_img , $content);
            $replaced = true;
            $count++;
          }
        }
      }
      if ( $replaced ) {
        /*$update_post = array();
        $update_post['ID'] = $post_id;
        $update_post['post_content'] = $content;
        wp_update_post($update_post);
        if ( function_exists('_fix_attachment_links') ) { 
          _fix_attachment_links( $post_id );
        }*/
      }
    }
  }
  return $total - $count;
}

function impatt_get_img_tags( $post ) {
  $s = get_option( 'siteurl' );
  $result = array();
  preg_match_all( '/<img[^>]* src=[\'"]?([^>\'"]+)/' , $post->post_content , $matches );
  preg_match_all( '/<a[^>]* href=[\'"]?([^>\'"]+)/' , $post->post_content , $matches2 );
  $matches[0] = array_merge( $matches[0] , $matches2[0] );
  $matches[1] = array_merge( $matches[1] , $matches2[1] );
  $host = get_site_url();
  $host = parse_url($host);
  $host = $host['host'];
  for ( $i=0; $i<count($matches[0]); $i++ ) {
    $uri = $matches[1][$i];
    $path_parts = pathinfo($uri);
    if ( strpos($uri, $host ) != false ){
      $uri = '';
    }
    if ( $uri != '' && preg_match( '/^https?:\/\//' , $uri ) ) {
      if ( $s != substr( $uri , 0 , strlen( $s ) ) ) {
        $path_parts['extension'] = (isset($path_parts['extension'])) ? strtolower($path_parts['extension']) : false;
        if ( in_array( $path_parts['extension'], array( 'gif', 'jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx' ) ) )
          $result[] = $uri;
      }
    }
  }
  return array_unique($result);
}

function impatt_sideload( $file , $post_id , $desc = '' ) {
  if ( ! empty($file) && impatt_is_external_file( $file ) ) {
    if ( !function_exists('media_handle_sideload') ) { 
      require_once(ABSPATH . 'wp-admin/includes/image.php');
      require_once(ABSPATH . 'wp-admin/includes/file.php');
      require_once(ABSPATH . 'wp-admin/includes/media.php');
    }
    $tmp = download_url( $file );
    preg_match('/[^\?]+\.(jpg|jpeg|gif|png|pdf|doc|docx)/i', $file, $matches);
    $file_array['name'] = basename($matches[0]);
    $file_array['tmp_name'] = $tmp;
    if ( is_wp_error( $tmp ) ) {
      @unlink($file_array['tmp_name']);
      $file_array['tmp_name'] = '';
      return false;
    }
    $desc = $file_array['name'];
    $id = media_handle_sideload( $file_array, $post_id, $desc );
    if ( is_wp_error($id) ) {
      @unlink($file_array['tmp_name']);
      return false;
    } else {
      $src = wp_get_attachment_url( $id );
    }
  }
  if ( !empty( $src ) && impatt_is_external_file( $src ) )
    return $src;
  else
    return false;
}

function impatt_is_external_file( $file ) {
  $allowed = array( 'jpeg' , 'png', 'bmp' , 'gif',  'pdf', 'jpg', 'doc', 'docx' );
  $ext = pathinfo($file, PATHINFO_EXTENSION);
  if ( in_array( strtolower($ext) , $allowed ) ) {
    return true;
  }
  return false;
}