<?php
/*
Plugin Name: Fluidinfo
Plugin URI: http://www.fluidinfo.com
Description: Plugin to export posts to Fluidinfo
Author: PA Parent
Version: 0.1-alpha
Author URI: http://www.twitter.com/paparent
License: MIT
*/

/*
 * Copyright (c) 2011 PA Parent
 *
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */


// Include fluidinfo.php library
require('includes/fluidinfo.php');

// Set-up Hooks
register_uninstall_hook(__FILE__, 'fi_delete_plugin_options');
add_action('admin_init', 'fi_init');
add_action('admin_menu', 'fi_admin_menu');
add_action('admin_head', 'fi_admin_jsapp');
add_action('wp_ajax_fi_export', 'fi_ajax_export');

// Delete options table entries ONLY when plugin deactivated AND deleted
function fi_delete_plugin_options() {
	delete_option('fi_options');
}

// Init plugin options to white list our options
function fi_init() {
	register_setting('fi_plugin_options', 'fi_options', 'fi_validate_options');
}

function fi_getfluidinfo() {
	$options = get_option('fi_options');

	$fluidinfo = new Fluidinfo();
	$fluidinfo->setPrefix($options['instance']);
	$fluidinfo->setCredentials($options['username'], $options['password']);

	return $fluidinfo;
}

// Add menu page
function fi_admin_menu() {
	add_options_page('Fluidinfo Options Page', 'Fluidinfo', 'manage_options', 'fi-menu-options', 'fi_options_render');
	add_management_page('Fluidinfo Export', 'Fluidinfo Export', 'export', 'fi-menu-management', 'fi_export_render');
}

// Render the Plugin options form
function fi_options_render() {
	$options = get_option('fi_options');
?>
<div class="wrap">
	<div class="icon32" id="icon-options-general"><br></div>
	<h2>Fluidinfo</h2>
	<p>Fluidinfo configuration</p>

	<form method="post" action="options.php">
		<?php settings_fields('fi_plugin_options'); ?>
		<table class="form-table">
			<tr>
				<th scope="row">Username</th>
				<td><input type="text" size="57" name="fi_options[username]" value="<?php echo $options['username']; ?>" /></td>
			</tr>
			<tr>
				<th scope="row">Password</th>
				<td><input type="password" size="57" name="fi_options[password]" value="<?php echo $options['password']; ?>" /></td>
			</tr>
			<tr>
				<th scope="row">Namespace</th>
				<td><input type="text" size="57" name="fi_options[namespace]" value="<?php echo $options['namespace']; ?>" /></td>
			</tr>
			<tr>
				<th scope="row">Fluidinfo instance url</th>
				<td><input type="text" size="57" name="fi_options[instance]" value="<?php echo $options['instance']; ?>" /></td>
			</tr>
		</table>
		<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
</div>
<?php
}

// Sanitize and validate input. Accepts an array, return a sanitized array.
function fi_validate_options($input) {
	$input['username'] = wp_filter_nohtml_kses($input['username']);
	$input['password'] = wp_filter_nohtml_kses($input['password']);
	$input['namespace'] = rtrim(wp_filter_nohtml_kses($input['namespace']), '/');
	$input['instance'] = rtrim(wp_filter_nohtml_kses($input['instance']), '/');
	return $input;
}

add_filter('plugin_action_links', 'fi_plugin_action_links', 10, 2);
// Display a Settings link on the main Plugins page
function fi_plugin_action_links($links, $file) {
	if ($file == plugin_basename(__FILE__)) {
		$fi_links = '<a href="'.get_admin_url().'options-general.php?page=fi-menu-options">'.__('Settings').'</a>';
		// make the 'Settings' link appear first
		array_unshift($links, $fi_links);
	}
	return $links;
}

// Tools menu - Fluidinfo Export
function fi_export_render() {
	$options = get_option('fi_options');
	$hidden_field_name = 'fi_submit_hidden';
?>
<style>
.fiexportlist td{padding:2px;}
.fiexportlist td.status .fail{background-color:#800;color:#ddd;padding:2px;}
.fiexportlist td.status .ok{background-color:#080;color:#ddd;padding:2px;}

.striperows tr.alt{background:#eee;}
.striperows tr.over{background:#ccc;}
</style>
<div class="wrap">
	<div class="icon32" id="icon-tools"><br></div>
	<h2>Fluidinfo export</h2>
<?php

	if (isset($_POST[$hidden_field_name]) && $_POST[$hidden_field_name] == 'Y') {
		echo '<div id="message" class="updated below-h2"><p><strong>Posts exported.</strong></p></div>';
	}

	// Get all posts for grid
	$args = array('nopaging'=>true);
	$posts = get_posts($args);

	// Fetch all exported posts
	$fluidinfo = fi_getfluidinfo();

	$exported_posts_urls = array();
	$out = $fluidinfo->getValues('has ' . $options['namespace'] . '/title', 'fluiddb/about');
	$out = $out['results']['id'];
	if ($out) foreach ($out as $id => $entry) {
		$exported_posts_urls[] = $entry['fluiddb/about']['value'];
	}

?>

	<p>Below you can select posts to export into Fluidinfo. Either select a set of posts
           and click &quot;Export selected posts&quot; below, or click on the &quot;Export now&quot;
           link next to an individual post to export it immediately.</p>

	<form id="fi-export-form" method="post" action="">
		<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
<table class="fiexportlist striperows">
<tr><td colspan="4"><input type="checkbox" class="checkall"> Select all</td></tr>
<?php
	foreach ($posts as $post) {
		$permalink = get_permalink($post->ID);
		echo '<tr>';
		echo '<td><input type="checkbox" name="postid-'.$post->ID.'"><a href="#" class="fi-export">Export now</a></td>';
		echo '<td>' . $post->post_title . '</td>';
		echo '<td>' . $post->post_date . '</td>';
		echo '<td class="status">';
		if (in_array($permalink, $exported_posts_urls)) {
			echo '<span class="ok">Already exported</span>';
		}
		echo '</td>';
		echo '</tr>';
	}
?>
</table>
		<p class="submit"><input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Export selected posts') ?>" /></p>
	</form>
</div>
<?php
}

// Javascript Application
function fi_admin_jsapp() {
?>
<script>
/*
* jQuery.ajaxQueue - A queue for ajax requests
*
* (c) 2011 Corey Frang
* Dual licensed under the MIT and GPL licenses.
*
* Requires jQuery 1.5+
*/
(function($) {

// jQuery on an empty object, we are going to use this as our Queue
var ajaxQueue = $({});

$.ajaxQueue = function( ajaxOpts ) {
    var jqXHR,
        dfd = $.Deferred(),
        promise = dfd.promise();

    // queue our ajax request
    ajaxQueue.queue( doRequest );

    // add the abort method
    promise.abort = function( statusText ) {

        // proxy abort to the jqXHR if it is active
        if ( jqXHR ) {
            return jqXHR.abort( statusText );
        }

        // if there wasn't already a jqXHR we need to remove from queue
        var queue = ajaxQueue.queue(),
            index = $.inArray( doRequest, queue );

        if ( index > -1 ) {
            queue.splice( index, 1 );
        }

        // and then reject the deferred
        dfd.rejectWith( ajaxOpts.context || ajaxOpts, [ promise, statusText, "" ] );
        return promise;
    };

    // run the actual query
    function doRequest( next ) {
        jqXHR = $.ajax( ajaxOpts )
            .then( next, next )
            .done( dfd.resolve )
            .fail( dfd.reject );
    }

    return promise;
};

})(jQuery);

jQuery(document).ready(function($) {
	function fi_update_status(text, successcls, $tr) {
		$status = $tr.find("td.status");
		$status.html('<span class="'+successcls+'">' + text + '</span>');
	}

	function fi_export(postid, $tr, cb) {

		var data = {
			action: 'fi_export',
			postid: postid
		};

		$.ajaxQueue({url: ajaxurl, data: data, success:function(response){
			console.log(response);
			if (response.success) {
				cb("Exported in " + response.time + "ms", 'ok', $tr);
			}
			else {
				cb("An error occured: " + response.msg, 'fail', $tr);
			}
		}});
	}

	$('.fi-export').click(function() {
		$this = $(this);
		$tr = $this.parent("td").parent("tr");
		$input = $tr.find('input[type="checkbox"]');
		postid = $input.attr("name").replace(/postid-/, '');
		fi_export(postid, $tr, fi_update_status);
		return false;
	});

	$("#fi-export-form").submit(function(){
		$this = $(this);
		event.preventDefault();

		$this.parent().parent().find('input[type="checkbox"]').each(function(){
			if (this.checked && $(this).attr("class") != 'checkall') {
				postid = $(this).attr("name").replace(/postid-/, '');
				$tr = $(this).parent("td").parent("tr");
				fi_update_status("pending", $tr);
				fi_export(postid, $tr, fi_update_status);
			}
		});

		return false;
	});
	$('.checkall').click(function () {
		$('#fi-export-form').find(':checkbox').attr('checked', this.checked);
	});
	$('.striperows tr:even').addClass("alt");
	$('.striperows tr').hover(function(){$(this).addClass("over");},function(){$(this).removeClass("over");});
});
</script>
<?php
}

// Callback for Ajax export
function fi_ajax_export() {
	header('Content-type: application/json');

	try {
		$post_id = $_GET['postid'];

		$tc = new TimeCounter();
		$tc->startCounter();

		$post = get_post($post_id);
		fi_export_post($post);

		$tc->stopCounter();
		echo json_encode(array('success'=>True,'time'=>$tc->getElapsedTime()));
	}
	catch (Exception $e) {
		echo json_encode(array('success'=>False,'msg'=>$e->getMessage()));
	}

	die;
}

function fi_export_post($post) {

	$options = get_option('fi_options');
	$ns = $options['namespace'];

	$fluidinfo = fi_getfluidinfo();

	$data = array();

	$permalink = get_permalink($post->ID);

	$data['fluiddb/about'] = $permalink;
	$data[$ns.'/text'] = strip_tags($post->post_content);
	$data[$ns.'/html'] = $post->post_content;
	$data[$ns.'/title'] = $post->post_title;

	// Can posts have more that one author?
	$author = get_userdata($post->post_author);
	$data[$ns.'/author-names'] = array($author->user_firstname . ' ' . $author->user_lastname);

	$data[$ns.'/publication-datetime'] = $post->post_date_gmt;
	$publication = strtotime($post->post_date_gmt);
	$data[$ns.'/publication-year'] = (int) date('Y', $publication);
	$data[$ns.'/publication-month'] = (int) date('n', $publication);
	$data[$ns.'/publication-day'] = (int) date('j', $publication);
	$data[$ns.'/publication-time'] = date('g:i:s', $publication);
	$data[$ns.'/publication-timestamp'] = (float) $publication; // TODO: Need to force float...
	// "paparent/wordpress/publication-date": "May 29th 2011",

	$data[$ns.'/modification-datetime'] = $post->post_modified_gmt;
	$modification = strtotime($post->post_modified_gmt);
	$data[$ns.'/modification-year'] = (int) date('Y', $modification);
	$data[$ns.'/modification-month'] = (int) date('n', $modification);
	$data[$ns.'/modification-day'] = (int) date('j', $modification);
	$data[$ns.'/modification-time'] = date('g:i:s', $modification);
	$data[$ns.'/modification-timestamp'] = (float) $modification; // TODO: Need to force float...
	// "paparent/wordpress/modification-date": "June 10th 2011",

	$post_cats = get_the_category($post->ID);
	if ($post_cats) {
		foreach ($post_cats as $cat) {
			$cat->slug = str_replace(' ', '-', $cat->slug);
			$data[$ns.'/categories/'.$cat->slug] = null;
		}
	}

	$post_tags = get_the_tags($post->ID);
	if ($post_tags) {
		foreach ($post_tags as $tag) {
			$tag->name = str_replace(' ', '-', $tag->name);
			$data[$ns.'/tags/'.$tag->name] = null;
		}
	}

	$DOM = new DOMDocument;
	$DOM->loadHTML($post->post_content);

	$anchors = $DOM->getElementsByTagName('a');

	$urls = array();
	$domains = array();
	for ($i = 0; $i < $anchors->length; $i++) {
	   $url = $anchors->item($i)->getAttribute('href');
	   // Starts with https?:// (Didn't use Regexp for performance)
	   if (strpos($url, 'http://') === 0 OR strpos($url, 'https://') === 0) {
		   $domain = parse_url($url, PHP_URL_HOST);
		   if (!in_array($domain, $domains)) {
			   $domains[] = $domain;
		   }
		   if (!in_array($url, $urls)) {
			   $urls[] = $url;
		   }
	   }
	}

	if ($domains) {
		$data[$ns.'/urls'] = $urls;
		$data[$ns.'/domains'] = $domains;

		fi_tag_urls_domains($permalink, $urls, $domains);
	}

	$about = $data['fluiddb/about'];
	unset($data['fluiddb/about']);

	$out = $fluidinfo->updateValues('fluiddb/about="' . $about . '"', $data);
	if (is_array($out)) {
		throw new Exception('Fluidinfo status code: ' . $out[0]);
	}
}

$fi_mentions = null;
function fi_tag_urls_domains($permalink, $urls, $domains) {
	global $fi_mentions;

	$options = get_option('fi_options');
	$ns = $options['namespace'];

	$fluidinfo = fi_getfluidinfo();

	if (!$fi_mentions) {
		$result = $fluidinfo->getValues('has '.$ns.'/mentioned', array('fluiddb/about', $ns.'/mentioned'));

		$mentioned = $result['results']['id'];

		$fi_mentions = array();
		if ($mentioned) foreach ($mentioned as $m) {
			$fi_mentions[$m['fluiddb/about']['value']] = $m[$ns.'/mentioned']['value'];
		}
	}

	foreach ($urls as $url) {
		if (array_key_exists($url, $fi_mentions)) {
			if (!in_array($permalink, $fi_mentions[$url])) {
				$fi_mentions[$url][] = $permalink;
				$mentioned = $fi_mentions[$url];
			}
			else {
				continue;
			}
		}
		else {
			$mentioned = array($permalink);
		}
		$json = array($ns.'/mentioned' => $mentioned);
		$out = $fluidinfo->updateValues('fluiddb/about="' . $url . '"', $json);
	}

	foreach ($domains as $domain) {
		if (array_key_exists($domain, $fi_mentions)) {
			if (!in_array($permalink, $fi_mentions[$domain])) {
				$fi_mentions[$domain][] = $permalink;
				$mentioned = $fi_mentions[$domain];
			}
			else {
				continue;
			}
		}
		else {
			$mentioned = array($permalink);
		}
		$json = array($ns.'/mentioned' => $mentioned);
		$out = $fluidinfo->updateValues('fluiddb/about="' . $domain . '"', $json);
	}

}

class TimeCounter
{
    var $startTime;
    var $endTime;

    function TimeCounter()
    {
        $this->startTime=0;
        $this->endTime=0;
    }
    function getTimestamp()
    {
        $timeofday = gettimeofday();
        //RETRIEVE SECONDS AND MICROSECONDS (ONE MILLIONTH OF A SECOND)
        //CONVERT MICROSECONDS TO SECONDS AND ADD TO RETRIEVED SECONDS
        //MULTIPLY BY 1000 TO GET MILLISECONDS
         return 1000*($timeofday['sec'] + ($timeofday['usec'] / 1000000));
    }
    function startCounter()
    {
        $this->startTime=$this->getTimestamp();
    }
    function stopCounter()
    {
        $this->endTime=$this->getTimestamp();
    }
    function getElapsedTime()
    {
        //RETURN DIFFERECE IN MILLISECONDS
        return number_format(($this->endTime)-($this->startTime), 2);
    }
}
