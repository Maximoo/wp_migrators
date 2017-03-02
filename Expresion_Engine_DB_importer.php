<?php

require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

define("MIGRATE_PPP", 500);
set_time_limit(0);

function sanitize_filename_on_upload($filename) {
	$exp = explode('.',$filename);
	$ext = end($exp);
	$sanitized = preg_replace('/[^a-zA-Z0-9-_.]/','', substr($filename, 0, -(strlen($ext)+1)));
	$sanitized = str_replace('.','-', $sanitized);
	return strtolower($sanitized.'.'.$ext);
}
add_filter('sanitize_file_name', 'sanitize_filename_on_upload', 10);

add_action( 'init', 'migrate' );
function migrate() {

	if( isset( $_GET['_action'] ) )
	{

		error_reporting(E_ALL);
		ini_set('display_errors', 1);

      	__html();
      	echo '<a href="?_action=migrate">Inicio</a><hr />';
        global $wpdb;
     	session_start();
     	switch($_GET['_action'])
     	{
	     	case "migrate":
	     		echo '<a href="?_action=users">Insert Users</a><hr />';
				$wpdb->get_results( "CREATE TABLE IF NOT EXISTS `exp_wp` (
				`entry_id` int(11) NOT NULL,
				`ID` int(11) NOT NULL,
				PRIMARY KEY (`entry_id`),
				UNIQUE KEY `ID` (`ID`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;" );
				$registros = $wpdb->get_results( "SELECT channel_id, channel_name, channel_title FROM exp_channels" );
				for($i = 0, $l = count($registros); $i < $l; $i++)
				{
					echo '<a href="?_action=inject&channel_name='.$registros[$i]->channel_name.'&channel_id='.$registros[$i]->channel_id.'">'.$registros[$i]->channel_title.'</a><br />';
					if($registros[$i]->channel_title == "Noticia" || $registros[$i]->channel_title == "Videos")
					echo '<a href="?_action=inject_images&channel_name='.$registros[$i]->channel_name.'&channel_id='.$registros[$i]->channel_id.'">'.$registros[$i]->channel_title.' - Images</a><br />';
				}
				echo '<hr /><a href="?_action=category_parents">Set Category Parents</a><br />';
				echo '<hr /><a href="?_action=reset_posts">RESET exp_wp, posts, postmeta</a><br />';
				echo '<a href="?_action=reset_terms">RESET terms, term_relationships, term_taxonomy</a><br />';
				echo '<a href="?_action=reset_users">RESET users, usermeta</a><br />';
     			if(!empty($_POST))
     			{
	     			$_SESSION["MIGRATE_PPP"] = (int) $_POST["MIGRATE_PPP"];
	     			$_SESSION["NO_UPDATE"] = isset($_POST["NO_UPDATE"]);
     			}
     			$MIGRATE_PPP = empty($_SESSION["MIGRATE_PPP"]) ? MIGRATE_PPP : $_SESSION["MIGRATE_PPP"];
     				
     			echo '<hr />';
     			if(!empty($_POST)) echo 'Actualizado<br />';
	     		echo '<form action="?_action=migrate" method="POST">
	     			<fieldset>
						<legend>Fijar Post Per Page:</legend>
						<input type="text" name="MIGRATE_PPP" value="'.$MIGRATE_PPP.'" />
					</fieldset>
					<fieldset>
						<legend>Ignorar posts existentes:</legend>
						<input type="checkbox" name="NO_UPDATE" '. (!empty($_SESSION["NO_UPDATE"])?'checked="checked"':'') .'/>
						<label for="NO_UPDATE">Ignorar</label><br />
					</fieldset>
					<br /><input type="submit" value="Actualizar" />
				</form>';
			break;
			case "reset_posts":
				$wpdb->get_results( "TRUNCATE exp_wp" );
				echo "TRUNCATE exp_wp :: DONE<br />";
				$wpdb->get_results( "TRUNCATE {$wpdb->prefix}posts" );
				echo "TRUNCATE wp_posts :: DONE<br />";
				$wpdb->get_results( "TRUNCATE {$wpdb->prefix}postmeta" );
				echo "TRUNCATE wp_postmeta :: DONE<br />";
			break;
			case "reset_terms":
				$wpdb->get_results( "TRUNCATE {$wpdb->prefix}term_relationships" );
				echo "TRUNCATE {$wpdb->prefix}term_relationships :: DONE<br />";
				$wpdb->get_results( "TRUNCATE {$wpdb->prefix}term_taxonomy" );
				$wpdb->get_results( "INSERT INTO {$wpdb->prefix}term_taxonomy (term_id,taxonomy) VALUES (1,'category')" );
				echo "TRUNCATE {$wpdb->prefix}term_taxonomy :: DONE<br />";
				$wpdb->get_results( "TRUNCATE {$wpdb->prefix}terms" );
				$wpdb->get_results( "INSERT INTO {$wpdb->prefix}terms (name,slug) VALUES ('Uncategorized','uncategorized')" );
				echo "TRUNCATE {$wpdb->prefix}terms :: DONE<br />";
			break;
			case "reset_users":
				$wpdb->get_results( "DELETE FROM {$wpdb->prefix}users WHERE id > 1" );
				echo "DELETE FROM {$wpdb->prefix}users WHERE id > 1 :: DONE<br />";
				$wpdb->get_results( "DELETE FROM {$wpdb->prefix}usermeta WHERE user_id > 1" );
				echo "DELETE FROM {$wpdb->prefix}usermeta WHERE user_id > 1 :: DONE<br />";
				$wpdb->get_results( "ALTER TABLE {$wpdb->prefix}users AUTO_INCREMENT = 2" );
				echo "ALTER TABLE {$wpdb->prefix}users AUTO_INCREMENT = 2 :: DONE<br />";
			break;
			case "inject": case "inject_images":
				$MIGRATE_PPP = empty($_SESSION["MIGRATE_PPP"]) ? MIGRATE_PPP : $_SESSION["MIGRATE_PPP"];
				$post_types = array("horoscopo" => "zodiac", "opinion" => "opinion", "noticia" => "post", "videos" => "video");
				if(!isset($post_types[$_GET["channel_name"]]))
				{
					echo "No está soportada ñ_ñ";
					break;
				}
				$url = '?_action='.$_GET['_action'].'&channel_name='.$_GET["channel_name"].'&channel_id='.$_GET["channel_id"];
				$count = count_entries($_GET["channel_id"]);
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
				$registros = query_entries($_GET["channel_id"],$page,$MIGRATE_PPP);
				for($i = 0, $l = count($registros); $i < $l; $i++)
				{
					$registros[$i]->ID = get_post_ID($registros[$i]->entry_id);
					$registros[$i]->post_type = $post_types[$_GET["channel_name"]];
					if($_GET['_action'] == "inject")
					{
						if(!empty($registros[$i]->ID) && !empty($_SESSION["NO_UPDATE"]))
						{
							echo "No actualizado | Expressionengine ID:<strong>" . $registros[$i]->entry_id ."</strong> | Wordpress ID:<strong>". $registros[$i]->ID . "</strong><br />";
							continue;
						}
						cast_post_fields_migrate($registros[$i]);
						//$registros[$i]->ID = wp_insert_post($registros[$i]);
						remove_filter('content_save_pre', 'wp_filter_post_kses');
						remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
						$registros[$i]->ID = wp_insert_post($registros[$i]);
						add_filter('content_save_pre', 'wp_filter_post_kses');
						add_filter('content_filtered_save_pre', 'wp_filter_post_kses');
						
						update_exp_wp($registros[$i]);
						update_post_meta_migrate($registros[$i]);
						update_terms_posts($registros[$i]);
						echo "Actualizado | Expressionengine ID:<strong>" . $registros[$i]->entry_id ."</strong> | Wordpress ID:<strong>". $registros[$i]->ID ."</strong><hr />";
					}
					elseif(!empty($registros[$i]->ID))
					{
						sideload_post_thumbnail($registros[$i]);
						//var_pre($registros[$i]);
						echo " | " . $registros[$i]->ID ."<hr />";
					}
					else echo "Crea primero el post para esta imagen<hr />";
				}
				if($hasnext && isset($_GET["auto"]))
				{
					echo "<script>setTimeout(function(){window.location.href='$nextpage_url&auto'} , 100);</script>";
				}
			break;
			case "category_parents":
				update_terms_parents("category");
				update_terms_parents("columnist");
				update_terms_parents("channel");
			break;
			case "users":
				update_users();
			break;
		}
    	echo mysql_error();
    	__html(0);
		exit;
    }
}
function update_users()
{
  global $wpdb;
  $registros = $wpdb->get_results( "SELECT
  	member_id,
  	'password' AS user_pass,
  	username AS user_login,
	screen_name AS display_name,
	email AS user_email,
	DATE_FORMAT(FROM_UNIXTIME(join_date),'%Y-%m-%d %H:%i:%s') as user_registered,
	(CASE group_title
        WHEN 'Super Admins' THEN 'administrator'
        WHEN 'Admin Editor' THEN 'editor'
        WHEN 'Editor' THEN 'author'
      	WHEN 'Members' THEN 'subscriber'
        ELSE group_title
    END) AS role
    FROM exp_members
    LEFT JOIN exp_member_groups ON (exp_members.group_id = exp_member_groups.group_id)
    WHERE member_id > 1");
	for($i = 0, $l = count($registros); $i < $l; $i++)
	{
		if($user = username_exists($registros[$i]->user_login))
		{
			echo "Ya existe | ";  
		}
		else
		{
			$user = wp_insert_user($registros[$i]);
			$wpdb->get_results( "UPDATE {$wpdb->prefix}users SET ID = {$registros[$i]->member_id} WHERE ID = {$user}" );
			$user = $registros[$i]->member_id;
		}
		echo "Expressionengine ID: <strong>{$registros[$i]->member_id}</strong> | Wordpress ID: <strong>{$user}</strong> | {$registros[$i]->user_login}<hr/>";
	}
}
function update_terms_posts( $data )
{
  $taxonomies = array(
    "opinion" => "columnist",
    "post" => "category",
    "video" => "channel"
  );
  if(isset($taxonomies[$data->post_type]))
  {
    global $wpdb;
    $taxonomy = $taxonomies[$data->post_type];
    $registros = $wpdb->get_results( "SELECT
      exp_category_posts.entry_id,
      exp_category_posts.cat_id,
      exp_categories.cat_name as name,
      exp_categories.cat_url_title as slug,
      exp_categories.cat_description as description,
      exp_categories.cat_image,
      exp_categories.cat_order
    FROM exp_category_posts
    LEFT JOIN exp_categories ON(exp_categories.cat_id = exp_category_posts.cat_id)
    WHERE exp_category_posts.entry_id = '{$data->entry_id}'" );
    $terms = array();
    for($i = 0, $l = count($registros); $i < $l; $i++)
    {
      $term = term_exists($registros[$i]->name,$taxonomy);
      if(!$term)
      {
      	$term = wp_insert_term($registros[$i]->name,$taxonomy,$registros[$i]);
      	if(!is_wp_error($term))
      	{
	        if($url = get_media_URL($registros[$i]->cat_image))
	        {
				echo media_sideload_image($url, $term["term_id"], $registros[$i]->name) . "<br />";
	        }
	        custom_cat_options($data->post_type,$term["term_id"],$registros[$i]);
	    }
	    else
	    {	
	    	echo $registros[$i]->name . " | ERROR | La taxonomia: $taxonomy, aún no ha sido registrada. <br/>";
	    	continue;
		}
      }
      $terms[] = (int)(isset($term["term_id"]) ? $term["term_id"] : $term);
    }
    wp_set_object_terms($data->ID,$terms,$taxonomy,true);
  }
}
function update_terms_parents($taxonomy)
{
	echo "Update Terms Parents: $taxonomy <br />";
	global $wpdb;
	$registros = $wpdb->get_results( "SELECT
	  tparent.cat_name as parent,
	  exp_categories.cat_name as name
	FROM exp_categories
	LEFT JOIN exp_categories tparent ON(tparent.cat_id = exp_categories.parent_id)
	WHERE tparent.cat_name <> ''" );
	for($i = 0, $l = count($registros); $i < $l; $i++)
	{
		if(	($term 		  = get_term_by( "name", $registros[$i]->name, $taxonomy )) &&
			($term_parent = get_term_by( "name", $registros[$i]->parent, $taxonomy )) )
		{
			var_pre($registros[$i]);
			wp_update_term($term->term_id, $taxonomy, array('parent' => $term_parent->term_id));
		}
	}
	echo "<hr />";
}
function cast_post_fields_migrate($data)
{
  $fields = array(
    "opinion" => array(
      'opinion' => 'post_content',
      'summary_col' => 'post_excerpt'
    ),
    "post" => array(
      'text' => 'post_content',
      'tags' => 'tags_input'
    ),
    "video" => array(
      'text_video' => 'post_content',
      'tags_videos' => 'tags_input'
    )
  );
  if(isset($fields[$data->post_type]))
    foreach($fields[$data->post_type] as $k => $v)
    {
      $data->{$v} = $data->{$k};
      unset($data->{$k});
    }
}
function update_post_meta_migrate($data)
{
  $meta_fields = array(
    "xyz" => array(
      'xyz' => 'xyz',
      'xyz' => 'xyz',
    ),
    "post" => array(
      'summary' => 'sp_teaser',
      'destacado' => array('sp_featured_post_in' => array("si" => "featured_sidebar_home","Home" => "featured_sidebar_home")),
      'breaking_news' => array('sp_featured_post_in' => array("Si" => "in_breakingnews")),
      'titular' => array('sp_featured_post_in' => array("Si" => "featured_sidebar"),
      					 'sp_featured_post' => array("Home" => "1", "Nota_miniatura_1" => "2","Nota_miniatura_2" => "3","Nota_miniatura_3" => "4"),
      					 'sp_featured_post_in' => array("Titulares" => "in_headlines"),
      					 'sp_featured_post_in' => array("Categoria_Principal" => "featured_archives")
      					)
    ),
    "video" => array(
      'video_webm' => 'video_webm',
      'summary_video' => 'sp_teaser',
      'breaking' => array('sp_featured_post_in' => array("Si" => "in_breakingnews")),
      'titular_v' => array('sp_featured_video' => array("Home" => "1"),
      					   'sp_featured_post_in' => array("Si" => "featured_archives"))
    )
  );
  if(isset($meta_fields[$data->post_type]))
    foreach($meta_fields[$data->post_type] as $exp_field => $wp_field)
    {
    	if(is_array($wp_field))
    	{
    		foreach($wp_field as $wp_field_k => $wp_field_values)
    		{
	    		foreach(explode("|",$data->{$exp_field}) as $value)
	    		{
	    			if( isset($wp_field_values[$value]) )
	    			{
	    				if(!update_post_meta($data->ID, $wp_field_k, $wp_field_values[$value], $wp_field_values[$value]))
	    				{
	    					add_post_meta($data->ID, $wp_field_k, $wp_field_values[$value], false);
	    				}
	    			}
	    		}
	    	}
    	}
    	else update_post_meta($data->ID, $wp_field, $data->{$exp_field});
    }
}
function update_exp_wp( $data )
{
  global $wpdb;
  $registros = $wpdb->get_results( "INSERT INTO exp_wp (entry_id,ID) VALUES ('{$data->entry_id}','{$data->ID}') ON DUPLICATE KEY UPDATE entry_id=entry_id" );
}
function get_post_ID( $entry_id )
{
  global $wpdb;
  $registros = $wpdb->get_results( "SELECT ID FROM exp_wp WHERE entry_id = '$entry_id'" );
  return count($registros) ? $registros[0]->ID : '';
}
function get_media_URL( $image )
{
  if(!empty($image))
  {
	preg_match_all("/\{filedir_([^\}]*)\}(.*)/", $image, $matches);
	if($matches[1])
	{
		global $wpdb;
		$registros = $wpdb->get_results( "SELECT url FROM exp_upload_prefs WHERE id = '". $matches[1][0] ."'" );
		return $registros[0]->url . $matches[2][0];
	}
  }
  return false;
}
function get_query_fields( $channel_id )
{
  global $wpdb;
  $registros = $wpdb->get_results( "SELECT field_group FROM exp_channels WHERE channel_id = '$channel_id'" );
  $registros = $wpdb->get_results( "SELECT field_id, field_name, field_fmt FROM exp_channel_fields WHERE group_id = '".$registros[0]->field_group."'" );
  $q = array();
  for($i = 0, $l = count($registros); $i < $l; $i++)
  {
    $q[] = "exp_channel_data.field_id_".$registros[$i]->field_id." AS ".$registros[$i]->field_name;
  }
  return implode(",", $q);
}
function query_entries( $channel_id, $page = 1, $ppp = 10 )
{
  global $wpdb;
  $query_fields = get_query_fields($channel_id);
  $registros = $wpdb->get_results( "SELECT
      exp_channel_titles.entry_id,
      exp_channel_titles.author_id AS post_author,
      exp_channel_titles.title AS post_title,
      exp_channel_titles.url_title AS post_name,
      IF(exp_channel_titles.status = 'open','publish','draft') AS post_status,
      DATE_FORMAT(FROM_UNIXTIME(exp_channel_titles.entry_date),'%Y-%m-%d %H:%i:%s') AS post_date,
      $query_fields
    FROM exp_channel_titles
    LEFT JOIN exp_channel_data ON (exp_channel_data.entry_id = exp_channel_titles.entry_id)
    WHERE exp_channel_titles.channel_id = '$channel_id'
    ORDER BY exp_channel_titles.entry_id ASC
    LIMIT ".(($page - 1) * $ppp).", $ppp" );
  return $registros;
}
function count_entries( $channel_id )
{
  global $wpdb;
  $registros = $wpdb->get_results( "SELECT COUNT(entry_id) AS c FROM exp_channel_titles WHERE channel_id = '$channel_id'" );
  return (int) $registros[0]->c;
}
function var_pre( $arr )
{
	echo "<pre>";
	foreach($arr as $k => $w)
	{
		echo $k . "=>"  . $w . "\n";
	}
	echo "</pre>";
}
function __html($i = 1)
{
  echo $i ? '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Migrate</title></head><body>' : '</body></html>';
}
function update_option_category_meta( $term_id, $field, $value )
{
	return update_option_array('category_meta',$term_id, $field, $value);
}
function update_option_array( $option, $term_id, $field, $value )
{
	$metas = get_option( $option );
	if ( !is_array( $metas ) ) $metas = (array) $metas;
	$meta = isset( $metas[$term_id] ) ? $metas[$term_id] : array();
	$meta[$field] = $value;
	$metas[$term_id] = $meta;
	return update_option( $option, $metas );
}
function custom_cat_options( $post_type, $term_id, $data )
{
  $custom_val = array(
    "opinion" => array(
    	"columnist_bullet" => array(
			array("field" => "cat_id", "cat_id" => "50", "value" => 'XYZ'),
			array("field" => "cat_id", "cat_id" => "53", "value" => 'XYZ'),
			array("field" => "cat_id", "cat_id" => "49", "value" => "XYZ"),
    	),
		"columnist_photo" => "image"
    )
  );

	if(isset($custom_val[$post_type]))
	{
		foreach ($custom_val[$post_type] as $field => $action)
		{
			if(is_array($action))
			{
				foreach ($action as $item)
				{
					if($data->{$item["field"]} == $item[$item["field"]])
					{
						update_option_category_meta($term_id,$field,$item["value"]);
					}
				}
			}
			elseif($action == "image")
			{
				$image = get_attached_media($action,$term_id);
				$image = array_pop($image);
				update_option_category_meta($term_id,$field,$image->ID);
			}
		}
	}
}
function sideload_post_thumbnail( $data )
{
	if($data->post_type == "post")
	{
		$attachments = get_attached_media('image', $data->ID );
		if(count($attachments) == 0)
		{
			global $wpdb;
			$registros = $wpdb->get_results( "SELECT
				col_id_24 as image, col_id_25 as title, col_id_27 as credits
				FROM exp_channel_grid_field_1
				WHERE entry_id = '{$data->entry_id}'" );
			if(count($registros))
			{
				$registros = $registros[0];
				$registros->image = get_media_URL($registros->image);
				$result = media_sideload_image($registros->image, $data->ID, $registros->title);
				if(!is_wp_error($result))
				{
					echo /*$result . */"Imagen: {$data->image}";
					$attachment = get_attached_media('image', $data->ID );
					if(count($attachment) > 0){
						$attachment = array_pop($attachment);
						set_post_thumbnail($data->ID, $attachment->ID);
						wp_update_post(array("ID" => $attachment->ID, "post_excerpt" => $registros->credits));
						update_post_meta( $attachment->ID, '_wp_attachment_image_alt', wp_slash(addslashes($registros->title)) );
					}
				}
			}
		}
		else echo "No actualizado";
	}
	elseif($data->post_type == "video")
	{
		$attachments = get_attached_media('image', $data->ID );
		if(count($attachments) == 0)
		{
			$data->image = get_media_URL($data->thumb_video);
			$result = media_sideload_image($data->image, $data->ID, $data->post_title);
			if(!is_wp_error($result))
			{
				echo /*$result . */"Imagen: {$data->image}";
				$attachment = get_attached_media('image', $data->ID );
				if(count($attachment) > 0){
					$attachment = array_pop($attachment);
					set_post_thumbnail($data->ID, $attachment->ID);
					wp_update_post(array("ID" => $attachment->ID, "post_excerpt" => $data->pie_foto));
					update_post_meta( $attachment->ID, '_wp_attachment_image_alt', wp_slash(addslashes($data->post_title)) );
				}
			}
		}
		else echo "No actualizado";
	}
}