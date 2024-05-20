<?php
/*
     WordPress免登录发布接口
     把文件放到WordPress安装目录，和wp-config.php相同路径。
     为了安全，请一定要修改文件名，或者密码，最好两者都修改。否则别人从 aibrandinghub.com 看到后，可以直接在你的站点发布文章了。
	 如果修改了文件名，在添加 Publisher 的时候，使用 https://www.yourdomain.com/wp-ai.php
*/
$access_token = 'aibrandinghub.com'; // 请修改为你的密码
$auto_cate = true; // 是否自动建立分类 true | false
$post_author = 'aibrandinghub'; // 发布文章的用户名，必需是已注册用户
//$save    = "save";//发布到草稿箱，不发布到前台，你审核后可以发布
$post_status = 'publish'; // 发布文章的状态 "publish"：立即发布，
$next_time = false; // 随机时间发布，每篇帖子的间隔秒数；如果设置此项，提交的日期将会失效
$post_exists = true; // 是否判断标题重复 true | false

@$comment_users = $_POST['post_author']; // 本接口支持回复，可以设置回复的用户名，如果没有，可以从这些默认用户中选择，多个用户之间使用|||分开，高级功能，不会用的不要修改

## 配置段结束，下面的代码一般不需要修改 ##


if ($_POST['access_token'] != $access_token) {
	exit('error');
}


@header('Content-Type: text/html; charset=UTF-8');
require('./wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');
@extract($_REQUEST,EXTR_OVERWRITE);


if (!empty($reset_time)) {

	if ($set_last_time) {
		if (get_option('ai_post_time')) {
			update_option( 'ai_post_time', strtotime( $set_last_time ));	
			echo "<hr>Successful update!";
		} else {
			add_option('ai_post_time', strtotime( $set_last_time ));
			echo "<hr>Successful setup!";
		}
	} 
	if ($del_last_time) {
		delete_option('ai_post_time');
		echo "<hr>Deleted successfully!";
	}
	echo "<hr>";

	if (get_option( 'ai_post_time' )) {
		echo "The current final release time is set to:" . gmdate('Y-m-d H:i:s', get_option( 'ai_post_time' ));
	} else {
		echo "No final release time is currently set!";
	}
	exit();
}

if ($post_exists) {
	if (post_exists($post_title)) {
		exit("[OK] Title exists");
	}
}

if (empty($post_title)) {
	wp_dropdown_categories(array(
		'hide_empty' => 0, 
		'hide_if_empty' => false, 
		'taxonomy' => 'category', 
		'name' => 'parent', 
		'orderby' => 'name', 
		'hierarchical' => true, 
		'show_option_none' => __('None'))
	);
	exit();
}

if (!empty($post_category) && $auto_cate == true) {
	$post_category_id = array();
	$parent = 0;
	$cats = array_filter(explode('|||', $post_category));
	foreach ($cats as $key => $value) {
		$parent = wp_create_category($value, (int)$parent);
		$post_category_id[] = $parent;
	}	
} else {
	$post_category_id = @explode(",",$post_category_id);
}

$user_ID = get_user_by('login', $post_author) -> ID; 
$user_ID = !$user_ID ? 1 : $user_ID;

if (is_numeric($next_time) && $next_time > 3) {
	if (get_option('ai_post_time')) {
		$last_post_time = get_option( 'ai_post_time' );
		$post_time = ( $last_post_time + $next_time );
		update_option( 'ai_post_time', $post_time );		
	} else {
		$post_time =  time() ;
		add_option('ai_post_time', $post_time);
	}
} else {
	$post_time =  time() ;
}

$post_info = array(
	'post_status' => $post_status, 
	'post_type' => 'post', 
	'post_author' => $user_ID,
	'ping_status' => get_option( 'default_ping_status' ), 
	'comment_status' => get_option( 'default_comment_status' ),
	'post_pingback' => get_option( 'default_pingback_flag' ),
	'post_parent' => 0,
	'menu_order' => 0, 
	'to_ping' => '', 
	'pinged' => '', 
	'post_password' => '',
	'guid' => '', 
	'post_content_filtered' => '', 
	'post_excerpt' => $post_excerpt, 
	'import_id' => 0,
	'post_content' => $post_content, 
	'post_title' => $post_title, 
	'post_category' => $post_category_id,
	'post_name' => trim($post_name),
	'post_date_gmt' => get_gmt_from_date(gmdate('Y-m-d H:i:s', $post_time + ( get_option( 'gmt_offset' ) * 3600 ))),
	'post_date' => gmdate('Y-m-d H:i:s', $post_time + ( get_option( 'gmt_offset' ) * 3600 )), 
	'tags_input' => array_unique(array_filter(explode('|||', preg_replace("/[^a-zA-Z\s\|]+/", "", $tags)))),
);

$pid = wp_insert_post($post_info); 
set_post_format($pid, '');
if ($pid) {
	echo "[OK] $pid";
} else {
	exit('error');
}

if (is_array($tax_input) && !empty($tax_input)) {
	foreach (array_unique(array_filter($_POST['tax_input'])) as $key => $value) {
		add_post_meta($pid, $key, $value, true);
	}
}

if (!empty($post_comment)) { 
	$comm_array = explode('|||', $post_comment);
	$comm_user_array = array_filter(explode('|||', $comment_users));
	$comm_date = empty($comment_date) ? gmdate('Y-m-d H:i:s', $post_time + ( get_option( 'gmt_offset' ) * 3600 )) : $comment_date;

	foreach ($comm_array as $comm_key => $comm_value) {
		$comm_date = gmdate('Y-m-d H:i:s' , (strtotime($comm_date) + rand(6000, 12000)));
		$comm = array(
			"comment_post_ID" => $pid,
			"comment_author"=>$comm_user_array[array_rand($comm_user_array)], 
			"comment_date"=>$comm_date,
			"comment_date_gmt" =>  get_gmt_from_date($comm_date),
			);

		preg_match_all("~\[(user|time)#([^\]<>]*?)\]~i", $comm_value, $temp);
		if (is_array($temp[1]) && is_array($temp[2]) && !empty($temp[1])) {
			$comm_info_new = array_combine($temp[1],$temp[2]);
			print_r($comm_info_new);
			if (!empty($comm_info_new['user'])) {
				$comm['comment_author'] = $comm_info_new['user'];
			}
			if (!empty($comm_info_new['time'])) {
				$comm['comment_date'] = gmdate('Y-m-d H:i:s' , strtotime($comm_info_new['time']));
				$comm['comment_date_gmt'] =  get_gmt_from_date($comm['comment_date']);
			}
		}

		$comm['comment_content'] =preg_replace("~\[(user|time)#([^\]<>]*?)\]~i", "", $comm_value);
		$comm['comment_approved'] = 1;
		
		wp_insert_comment($comm);
		unset($comments,$comm_info);
	}
}
function set_featured_image_from_url($post_id, $url) {
    $image_url = $url;

    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $image_data = file_get_contents($image_url);
    $upload_dir = wp_upload_dir();
    $filename = basename($image_url);
    $filepath = $upload_dir['path'] . '/' . $filename;

    file_put_contents($filepath, $image_data);

    set_post_thumbnail($post_id, $filepath);
}

function get_feature_image_url($post_id) {

    $image_url = get_post_meta($post_id, 'fiurl', true);
    return $image_url;
}

if (!empty($post_thumbnail)) {
	$image_url = get_feature_image_url($pid);
	if ($image_url) {
		set_featured_image_from_url($pid, $image_url);
	}
}
?>