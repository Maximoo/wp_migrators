add_action( 'init', 'post_walker', 99 );
function post_walker() {
  if( isset( $_GET['_action'] ) ){
    session_start();
    error_reporting(E_ALL ^ E_NOTICE);
    ini_set('display_errors', 1);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>Migrate</title></head><body>';
    echo '<a href="?_action=migrate">Inicio</a><hr />';
    switch($_GET['_action']){
      case "migrate":
        echo '<a href="?_action=migrate_run">NEXT</a><hr />';
        echo '<a href="?_action=migrate_run&run">NEXT AND RUN</a><hr />';
        if(!empty($_POST))
        {
          $_SESSION["MIGRATE_PAGE"] = (int) $_POST["MIGRATE_PAGE"];
          $_SESSION["MIGRATE_PPP"] = (int) $_POST["MIGRATE_PPP"];
        }
        $MIGRATE_PAGE = empty($_SESSION["MIGRATE_PAGE"]) ? 1 : $_SESSION["MIGRATE_PAGE"];
        $MIGRATE_PPP = empty($_SESSION["MIGRATE_PPP"]) ? 10 : $_SESSION["MIGRATE_PPP"];
        if(!empty($_POST)) echo 'Actualizado<br />';
        echo '<form action="?_action=migrate" method="POST">
          <fieldset>
          <legend>POST PER PAGE:</legend>
          <input type="text" name="MIGRATE_PPP" value="'.$MIGRATE_PPP.'" />
          <legend>PAGE:</legend>
          <input type="text" name="MIGRATE_PAGE" value="'.$MIGRATE_PAGE.'" />
        </fieldset>
        <br /><input type="submit" value="Actualizar" />
      </form>';
        echo '<hr />';
      break;
      case "migrate_run":
        if(!empty($_GET["page"])){
          $_SESSION["MIGRATE_PAGE"] = (int) $_GET["page"];
        } 
        $query = new WP_Query(array(
          "post_type" => "video",
          "meta_key" => "youtube_id",
          "posts_per_page" => $_SESSION["MIGRATE_PPP"],
          "paged" => $_SESSION["MIGRATE_PAGE"]
        ));
        $yt_url = "https://www.youtube.com/watch?v=";
        $conflict = false;
        if ( $query->have_posts() ) {
          echo '<ul>';
          while ( $query->have_posts() ) {
            $query->the_post();
            echo '<li>' . get_the_title() . "<br />";
            $update = array(
              'ID'           => get_the_ID(),
              'post_content' => $yt_url . get_post_meta(get_the_ID(), "youtube_id", true) . "\n\n&nbsp;",
            );
            echo $update["post_content"] . '<br />';
            echo "Post format: video" . '<br />';
            if(isset($_GET["run"])){
              echo "Runing... <br />";
              $update_post_content = wp_update_post( $update, true );
              $update_post_format = set_post_format(get_the_ID(), "video");
              if(is_wp_error($update_post_content)){
                echo "ERROR: " . $update_post_content->get_error_message() . "<br />";
                $conflict = true;
              }
              if(is_wp_error($update_post_format)){
                echo "ERROR: " . $update_post_format->get_error_message() . "<br />";
                $conflict = true;
              }
              if(!$conflict){
                echo "Everyting is ok.";
              }
            }
            echo '</li>';
          }
          echo '</ul>';
          $current_url = '?_action=migrate_run&page=' . $_SESSION["MIGRATE_PAGE"]; 
          $nextpage_url = '?_action=migrate_run&page=' . ((int) $_SESSION["MIGRATE_PAGE"] + 1); 
          echo '<a href="' . $current_url .'&run">RUN</a><br />';
          echo '<a href="' . $nextpage_url .'">NEXT</a><br />';
          echo '<a href="' . $nextpage_url .'&run">NEXT AND RUN</a><br />';
          echo '<a href="' . $nextpage_url .'&run&auto">NEXT AND AUTO RUN</a><br />';
          if(isset($_GET["auto"]) && !$conflict){
            echo "<script>setTimeout(function(){window.location.href='$nextpage_url&run&auto'} , 200);</script>";
          }
        } else {
          echo 'DONE!';
        }
        echo '<hr />';
      break;
    }
    echo '</body></html>';
    die();
  }
}
