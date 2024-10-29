<?php
/*
Plugin Name: Alan's HTML Cache
Plugin URI: http://www.woyaofeng.com/
Description: 将文章页、目录页、首页实现HTML静态化，跳过PHP处理，加快访问速度达20倍
Version: 1.0.7
Author: everalan
Author URI: http://www.woyaofeng.com/
*/

/*  Copyright 2011 Alan <everalan@everalan.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
*/
ob_start();
add_action('shutdown', 'alan_html_cache', 0);
add_action( 'admin_menu', 'alan_html_cache_menu' );
add_action('trash_post', 'alan_html_cache_rmdir');
add_action('edit_post', 'alan_html_cache_rmdir');
//固定链接
define('ALAN_PRE_PAGE', 'article');
add_filter( 'page_link', 'alan_permalinks_page_link', 10, 2 );
add_filter( 'category_link', 'alan_permalinks_term_link', 10, 2 );
add_filter( 'request', 'alan_permalinks_request', 10, 1 );

function alan_html_cache(){
	global $wp_query, $wp_rewrite;;
	if(!(is_home() and $_SERVER['REQUEST_URI'] == '/') and !is_single() and !is_page() and !is_category()){	//only make index,single,page and category
		return;
	}
	if(is_single() or is_page()){
		$id = $wp_query->get_queried_object_id();
		$obj = $wp_query->get_queried_object();
		if($obj->post_type !== 'post' and $obj->post_type !== 'page'){	//附件类型等
			return;
		}
		$url = get_permalink( $id );
	}elseif(is_home()){
		$url = home_url();
		
	}elseif(is_category()){
		$id = $wp_query->get_queried_object_id();
		$url = get_category_link($id);
		$page = get_query_var('paged');
		if($page > 1){
			$page_url = user_trailingslashit( $wp_rewrite->pagination_base . "/" . $page, 'paged' );
			$url = trim($url, '/') . '/' . ltrim($page_url, '/');
		}
	}
	if($_SERVER['REQUEST_URI'] == '/'){
		$_SERVER['REQUEST_URI'] = '';
	}
	if($url != home_url().$_SERVER['REQUEST_URI'] or $_SERVER['REQUEST_METHOD']!='GET' or $_SERVER['QUERY_STRING'] != ''){
		return;
	}
	$info = parse_url($url);
	$path = '/' . trim($info['path'], '/') . '/index.html';
	
	$content = ob_get_contents();
	$filename = ABSPATH . "cache/alan_html_cache$path";

	$dir = dirname($filename);
	if(!file_exists($dir)){
		mkdir($dir, 0777, true);
	}
	$content .= "\n<!-- cached at " . date("Y-m-d H:i:s") . " -->";
	file_put_contents($filename, $content);
}

function alan_html_cache_menu() {
	add_menu_page( '清除缓存', '清除缓存', 'manage_options', 'alan_clear_cache' , 'alan_html_cache_do_clear');
}

function alan_html_cache_do_clear() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	alan_html_cache_rmdir();
	add_action('admin_notices', 'alan_html_cache_notice');
	echo '<div class="updated">
       <p>清除缓存成功！</p>
    </div>';
}

function alan_html_cache_rmdir(){
	$dir = ABSPATH . "cache/alan_html_cache";
	_alan_html_cache_rmdir($dir);
}

function _alan_html_cache_rmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir."/".$object) == "dir") 
					_alan_html_cache_rmdir($dir."/".$object); 
				else 
					unlink($dir."/".$object);
			}
		}
		reset($objects);
		rmdir($dir);
	}
}


/**
 * Filter to rewrite the query if we have a matching post
 *
 * @package CustomPermalinks
 * @since 0.1
 */
function alan_permalinks_request($query) {
	if(preg_match('|/'.ALAN_PRE_PAGE.'/(\d+)|', $_SERVER['REQUEST_URI'], $ms)) {
		$query = array('page_id'=>$ms[1]);
	}elseif(preg_match('|/'.get_option('category_base').'/(\d+)/?$|', $_SERVER['REQUEST_URI'], $ms) or preg_match('|/'.get_option('category_base').'/(\d+)(?:/page/(\d+))?$|', $_SERVER['REQUEST_URI'], $ms)) {
		$query = array('cat'=>$ms[1], 'paged'=>$ms[2]);
	}
	return $query;
}

/**
 * Filter to replace the page permalink with the custom one
 *
 * @package CustomPermalinks
 * @since 0.4
 */
function alan_permalinks_page_link($permalink, $page) {
	return get_home_url()."/".ALAN_PRE_PAGE.'/'.$page;
}

function alan_permalinks_term_link($permalink, $term) {
	if ( is_object($term) ) $term = $term->term_id;
	$permalink = get_home_url().'/'.get_option('category_base').'/'.$term;
	
	return $permalink;
}
