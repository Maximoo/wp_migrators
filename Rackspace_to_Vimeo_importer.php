<?php

add_action( 'init', 'import_vimeo' );
function import_vimeo() {
  if( isset( $_GET['_action'] ) )
  {
    define("MIGRATE_PPP", 100);

    error_reporting(E_ALL ^ E_NOTICE);
    ini_set('display_errors', 1);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>Migrate</title></head><body>';
    echo '<a href="?_action=import_vimeo">Inicio</a><hr />';
    global $wpdb;
    session_start();
    switch($_GET['_action'])
    {
      case "import_vimeo":
        echo '<a href="?_action=update_vimeo">Actualizar Vimeo</a><hr />';
        echo '<a href="?_action=rename_vimeo">Renombrar Vimeo</a><hr />';
        if(!empty($_POST))
        {
          $_SESSION["MIGRATE_PPP"] = (int) $_POST["MIGRATE_PPP"];
        }
        $MIGRATE_PPP = empty($_SESSION["MIGRATE_PPP"]) ? MIGRATE_PPP : $_SESSION["MIGRATE_PPP"];
        if(!empty($_POST)) echo 'Actualizado<br />';
        echo '<form action="?_action=import_vimeo" method="POST">
          <fieldset>
          <legend>Fijar Post Per Page:</legend>
          <input type="text" name="MIGRATE_PPP" value="'.$MIGRATE_PPP.'" />
        </fieldset>
        <br /><input type="submit" value="Actualizar" />
      </form>';
        echo '<hr />';
      break;
      case "rename_vimeo":
        $lib = new \Vimeo\Vimeo('****', '****');        
        //$token = $lib->clientCredentials(array('public','edit'));
        $lib->setToken('****');
      break;
      case "update_vimeo":

        $MIGRATE_PPP = empty($_SESSION["MIGRATE_PPP"]) ? MIGRATE_PPP : $_SESSION["MIGRATE_PPP"];
        $url = '?_action='.$_GET['_action'];
        $count = wp_count_posts('video');
        $count = $count->publish + $count->draft;
        $page = !empty($_GET["page"]) ? $_GET["page"] : 1;
        $totalpages = ceil($count/$MIGRATE_PPP);
        $hasnext = $page + 1 <= $totalpages;
        echo "Total:$count Pages:$totalpages";
        echo " | <a href='$url&page=1'>First Page</a>";
        echo " | <a href='$url&page=$totalpages'>Last Page</a>";
        echo " | CurrentPage:<a href='$url&page=$page'>$page</a>";
        if($page > 1)
        {
          echo " | PrevPage:<a href='$url&page=".($page-1)."'>".($page-1)."</a>";
        }
        $nextpage_url = "$url&page=".($page+1);
        if($hasnext)
        {
          echo " | NextPage:<a href='$nextpage_url'>".($page+1)."</a>";
          echo " | NextAuto:<a href='$nextpage_url&auto'>Auto</a>";
        }
        echo "<hr />";
        
        echo "<pre>";
        $posts = get_posts(array(
          'posts_per_page'   => $MIGRATE_PPP,
          'offset'           => ($page - 1) * $MIGRATE_PPP,
          'orderby'          => 'post_date',
          'order'            => 'DESC',
          //'meta_key'         => 'video_webm',
          'post_status'      => 'any',
          'post_type'        => 'video'));
        $remove = strlen("http://****.r28.cf5.rackcdn.com/");
        $remove2 = strlen("https://vimeo.com/");

        $print_auto = true;
        foreach($posts as $post)
        {
          echo $post->post_title . "\n";

          $vimeo_id_meta = get_post_meta($post->ID, 'vimeo_id', true);
          if(!empty($vimeo_id_meta))
          {
            echo "MIGRADO<hr />";
            continue;
          }


          $video_webm = get_post_meta($post->ID, 'video_webm', true);
          echo $video_webm . "\n";
          if(empty($video_webm)){
            echo spiga_get_metavideo($post->ID) . "\n";
            echo "NO SE PUEDE MIGRAR<hr />";
            continue;
          }

          $video_webm_arr = unserialize(base64_decode($video_webm));
          if(is_array($video_webm_arr))
          {
            var_dump($video_webm_arr);
            $video_webm = $video_webm_arr['url'];
          }
          
          $video_webm = str_replace('_',' ',substr(substr($video_webm, $remove),0,-4));
          echo "Búsqueda: " . $video_webm . "\n";

          $lib = new \Vimeo\Vimeo('****', '****');        
          //$token = $lib->clientCredentials(array('public'));
          //$lib->setToken($token['body']['access_token']);
          $lib->setToken('****');
          $response = $lib->request('/users/****/videos', array('per_page' => 5, 'query' => $video_webm), 'GET');
          if(!empty($response['body']['error']) )
          {
            echo "API ERROR: " . $response['status'] . " " . $response['body']['error'] . "<hr />";
            $print_auto = false;
            break;
          }

          if($response['body']['total'] >= 1)
          {
            if($response['body']['total'] > 1)
            {
              echo "***** CONFLICTO *****\n";
              $print_auto = false;
              for($i = 0; $i < $response['body']['total']; $i++)
              {
                echo ($i+1) . ") " . $response['body']['data'][$i]['name'] . "\n";
                echo $response['body']['data'][$i]['link'] . "\n\n";
              }
            }
            else echo "Encontrado\n";
            $vimeo_id = $response['body']['data'][0]['link'];
            echo $vimeo_id ."\n";
            $vimeo_id = substr($vimeo_id,$remove2);
            echo "                  ".$vimeo_id ."\n";
            update_post_meta($post->ID, 'vimeo_id', $vimeo_id);
          }
          else
          {
            echo "\n===== NOT FOUND =====\n";
            update_post_meta($post->ID, 'vimeo_id', '');
          }
          echo "<hr />";
        }
        if($hasnext && isset($_GET["auto"]) && $print_auto)
        {
          echo "<script>setTimeout(function(){window.location.href='$nextpage_url&auto'} , 4000);</script>";
        }
        echo "</pre>";
      break;
    }
    echo '</body></html>';
    die();
  }
}

?>

<?php /*****/

require dirname(__FILE__).'/vendor/autoload.php';

use OpenCloud\Rackspace;

define('RS_REGION', 'IAD');

if(array_key_exists('import-videos', $_GET)):

  $args = array(
    'post_type' => 'video',
    'posts_per_page' => defined('MIGRATE_PPP') ? MIGRATE_PPP : 10,
    'page' => isset($_GET['page']) ? $_GET['page'] : 0,
    'meta_key'  => 'video_webm'
    
  );
  
  $post_count = 0;
  $the_query = new WP_Query( $args );

  if ( $the_query->have_posts() ):
    while ( $the_query->have_posts() ):
      $the_query->the_post();
      $video_webm = get_post_meta(get_the_ID(),'video_webm', true);
      $video_webm_arr = unserialize(base64_decode($video_webm));
      if(is_array($video_webm_arr)):
        $url = $video_webm_arr['url'];
        update_post_meta(get_the_ID(), 'video_webm', $url);
        //print_r($video_webm_arr);
        //print_r($video_webm_arr);
      endif;
      $post_count++;
    endwhile;
  else:
    die('--no posts--');
  endif;

  die;

  //-------------

  $client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
    'username' => '****',
    'apiKey'   => '****'
  ));

  $service = $client->computeService(null, RS_REGION);

  // Obtain an Object Store service object from the client.
  $objectStoreService = $client->objectStoreService(null, RS_REGION);

  // Create a container for your objects (also referred to as files).
  //$container = $objectStoreService->createContainer('gallery');

  $container = $objectStoreService->getContainer('webfiles');

  $list = $container->ObjectList();

  //$account = $service->getAccount();
  //$account->getObjectCount();

  $containerObjects = array();
  $marker = '';

  while ($marker !== null) {
    $params = array(
      'marker' => $marker,
    );

    $objects = $container->objectList($params);
    $total = $objects->count();
    $count = 0;

    if ($total == 0) {
      break;
    }

    foreach ($objects as $object) {
      /** @var $object OpenCloud\ObjectStore\Resource\DataObject **/
      $containerObjects[] = array(
        'name'  => $object->getName(),
        'size' => $object->getContentLength(),
        'type' => $object->getContentType(),
        'checksum' => $object->getEtag(),
        'lastModified' => $object->getLastModified()
      );

      $count++;

      $marker = ($count == $total) ? $object->getName() : null;
    }
  }

  //echo sizeof($containerObjects);
  //print_r($containerObjects[0]);


  // First, you'll need to set the "temp url key" on your Account. This is an
  // arbitrary secret shared between Cloud Files and your application that's
  // used to validate temp url requests. You only need to do this once.
  //$account = $service->getAccount();
  //$account->setTempUrlSecret();

  // Get a temporary URL that will expire in 3600 seconds (1 hour) from now
  // and only allow GET HTTP requests to it.
  //$tempUrl = $object->getTemporaryUrl(3600, 'GET');

  die;

endif;

?>