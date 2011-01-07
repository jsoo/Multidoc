<?php

// soo_multidoc
//
// Multiple-page document plugin for the Textpattern content management system
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 2 of the License, or
// (at your option) any later version.
// 
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with this program. If not, see <http://www.gnu.org/licenses/>.

$plugin['version'] = '2.0.0';
$plugin['author'] = 'Jeff Soo';
$plugin['author_uri'] = 'http://ipsedixit.net/txp/';
$plugin['description'] = 'Create structured multi-page documents';
$plugin['type'] = 1; 

defined('PLUGIN_HAS_PREFS') or define('PLUGIN_HAS_PREFS', 0x0001); 
defined('PLUGIN_LIFECYCLE_NOTIFY') or define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); 
$plugin['flags'] = PLUGIN_HAS_PREFS | PLUGIN_LIFECYCLE_NOTIFY;

defined('txpinterface') or @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

require_plugin('soo_txp_obj');
@require_plugin('soo_plugin_pref');		// optional

  //---------------------------------------------------------------------//
 //									Globals								//
//---------------------------------------------------------------------//

if ( @txpinterface == 'admin' ) 
{
	add_privs('plugin_prefs.soo_multidoc','1,2');
	add_privs('plugin_lifecycle.soo_multidoc','1,2');
	register_callback('soo_multidoc_prefs', 'plugin_prefs.soo_multidoc');
	register_callback('soo_multidoc_prefs', 'plugin_lifecycle.soo_multidoc');
}

global $soo_multidoc;
$soo_multidoc = function_exists('soo_plugin_pref_vals') ? 
	array_merge(soo_multidoc_defaults(true), soo_plugin_pref_vals('soo_multidoc')) 
	: soo_multidoc_defaults(true);
$soo_multidoc = array_merge($soo_multidoc, array(
	'init'		=>	false,
	'status'	=>	false,
	'id_parent'	=>	'',
	'data'		=>	'',
));

function soo_multidoc_prefs( $event, $step )
{
	if ( function_exists('soo_plugin_pref') )
		return soo_plugin_pref($event, $step, soo_multidoc_defaults());
	if ( substr($event, 0, 12) == 'plugin_prefs' ) {
		$plugin = substr($event, 12);
		$message = '<p><br /><strong>' . gTxt('edit') . " $plugin " . 
			gTxt('edit_preferences') . ':</strong><br />' . gTxt('install_plugin') . 
			' <a href="http://ipsedixit.net/txp/92/soo_plugin_pref">soo_plugin_pref</a></p>';
		pagetop(gTxt('edit_preferences') . " &#8250; $plugin", $message);
	}
}

function soo_multidoc_defaults( $vals_only = false ) 
{
	$defaults = array(
		'list_all'	=>	array(
			'val'	=>	0,
			'html'	=>	'yesnoradio',
			'text'	=>	'Show Multidoc sub-pages in article lists?',
		),
		'posted_time'	=>	array(
			'val'	=>	'past',
			'html'	=>	'text_input',
			'text'	=>	'Show articles posted &lsquo;past&rsquo;, &lsquo;future&rsquo;, or &lsquo;any&rsquo;',
		),
	);
	if ( $vals_only )
		foreach ( $defaults as $name => $arr )
			$defaults[$name] = $arr['val'];
	return $defaults;
}

  //---------------------------------------------------------------------//
 //							MLP Pack definitions						//
//---------------------------------------------------------------------//

define('SOO_MULTIDOC_PREFIX', 'soo_mdoc');
global $soo_multidoc_strings;
$soo_multidoc_strings = array(
	'start'	=>	'start',
	'up'	=>	'up',
	'next'	=>	'next',
	'prev'	=>	'prev',
);

register_callback('soo_multidoc_enumerate_strings', 'l10n.enumerate_strings');

function soo_multidoc_enumerate_strings( $event, $step = '', $pre = 0 )
{
	global $soo_multidoc_strings;
	$r = array(
		'owner'		=> 'soo_multidoc',
		'prefix'	=> SOO_MULTIDOC_PREFIX,
		'lang'		=> 'en-us',
		'event'		=> 'public',
		'strings'	=> $soo_multidoc_strings,
				);
	return $r;
}

function soo_multidoc_gTxt( $what , $args = array() )
{
	global $textarray;
	global $soo_multidoc_strings;

	$key = SOO_MULTIDOC_PREFIX . '-' . $what;
	$key = strtolower($key);

	if(isset($textarray[$key]))
		$str = $textarray[$key];

	else
	{
		$key = strtolower($what);

		if( isset( $soo_multidoc_strings[$key] ) )
			$str = $soo_multidoc_strings[$key];
		else
			$str = $what;
	}

	if( !empty($args) )
		$str = strtr( $str , $args );

	return $str;
}


  //---------------------------------------------------------------------//
 //									Classes								//
//---------------------------------------------------------------------//

class soo_multidoc_rowset extends soo_nested_set
{
	public function has_subnode( $a, $b )
	// Boolean: is $b a subnode of $a?
	{
		return ( $this->$b->lft > $this->$a->lft and $this->$b->rgt < $this->$a->rgt );
	}
	
	public function find_by_link_type( $link_type, $current = null, $dir = 'next' )
	{
		$rs = $this->rows;
		if ( $dir == 'prev' )
			$rs = array_reverse($rs, true);
		$ids = array_keys($rs);
		$link_type = strtolower($link_type);
		
		if ( $current and in_array($current, $ids) )
		{
			$id_position = array_search($current, $ids);
			if ( $dir == 'prev' )
				$id_position++;
			$searchable_ids = array_flip(array_slice($ids, $id_position));
			$rs = array_intersect_key($rs, $searchable_ids);
		}
		
		foreach ( $rs as $k => $r )
			if ( $r->link_type == $link_type )
				return $k;
	}
}

  //---------------------------------------------------------------------//
 //									Tags								//
//---------------------------------------------------------------------//

function soo_multidoc_link( $atts, $thing = null )
{
// Output tag: display an HTML anchor
// Requires article context

	extract(lAtts(array(
		'rel'			=>	'',
		'add_title'		=>	'',
		'html_id'		=>	'',
		'class'			=>	'',
 		'active_class'	=>	'',
 		'wraptag'		=>	'',
	), $atts));
	
	global $soo_multidoc;
	if ( ! ( _soo_multidoc_init() and $soo_multidoc['status'] ) ) return false;
	
	foreach ( array('start', 'up', 'next', 'prev') as $text )
		$reserved_rel[$text] = $$text = strtolower(soo_multidoc_gTxt($text));
		
	global $thisarticle;
	$rowset = &$soo_multidoc['rowset'];
	$thisid = $thisarticle['thisid'];
	$rel = trim($rel);
	
	// $rel value may be space-separated list of link types
	// this tag allows only 'prev' or 'next' in combination with another type
	if ( preg_match("/^($next|$prev)\s+(\w+)/i", $rel, $match) )
	{
		$rel_dir = strtolower($match[1]);
		$rel_type = strtolower($match[2]);		
		$link_id = $rowset->find_by_link_type($rel_type, $thisid, $rel_dir);		

		// if I have an ancestor of the requested link type, that ancestor will
		// be the prev link of that type, which isn't what the user wants.
		// So continue back one more step.
		if ( $rel_dir == 'prev' and $rowset->has_subnode($link_id, $thisid) )
		{
			$next_link = $rowset->find_by_link_type($rel_type, $link_id, $rel_dir);
			if ( $next_link != $link_id and is_numeric($next_link) )
				$link_id = $next_link;
			else
				unset($link_id);
		}
	}
	elseif ( in_array($relcomp = strtolower($rel), $reserved_rel) )
	{	
		if ( $relcomp == $start )
			$link_id = $soo_multidoc['id_root'][$thisid];
		elseif ( $relcomp == $up )
		{
			$link_id = $soo_multidoc['id_parent'][$thisid];
			if ( ! intval($link_id) )
				$link_id = 0;
		}
		else	// next or prev
		{
			$next_array = $soo_multidoc['next_array'];
			if ( $relcomp == $prev )
				$next_array = array_reverse($next_array);
			$i = array_search($thisid, $next_array);
			$link_id = isset($next_array[$i + 1]) ? $next_array[$i + 1] : 0;
		}
			
	}
	else
		$link_id = $rowset->find_by_link_type($rel);
	
	if ( ! empty($link_id) )
		$url = $soo_multidoc['data'][$link_id]['url'];
	
	if ( ! isset($url) ) return false;
	
	if ( $add_title )
		$thing .= $rowset->$link_id->title;
	
	if ( $link_id == $thisid )
		$tag = new soo_html_span(array('class' => $active_class));

	else
		$tag = new soo_html_anchor(array('href' => $url, 'rel' => $rel));
	
	$tag->contents( $thing ? $thing : $rel );
		
	if ( $wraptag and class_exists($tag_class = 'soo_html_' . $wraptag) )
	{
		$wraptag = new $tag_class;
		return $wraptag->contents($tag)->class($class)->
			id($html_id)->
			tag();
	}
	else
	{
		$tag->id($html_id);
		if ( $link_id != $thisid )
			$tag->class($class);
		return $tag->tag();
	}
}
///////////////////// end of soo_multidoc_link() ///////////////////////

function soo_multidoc_pager( $atts )
{
// Output tag: display a simple list of page links
// Requires article context

	extract(lAtts(array(
		'limit'			=>	0,
		'placeholder'	=>	' &hellip; ',
		'html_id'		=>	'',
		'class'			=>	'',
 		'active_class'	=>	'',
 		'wraptag'		=>	'',
 		'wrapclass'		=>	'',
 		'break'			=>	'',
 		'breakclass'	=>	'',
	), $atts));

	global $soo_multidoc;
	if ( ! ( _soo_multidoc_init() and $soo_multidoc['status'] ) ) return false;
	
	global $thisarticle;
	$thisid = $thisarticle['thisid'];
	
	$wraptag = trim(strtolower($wraptag));
	if ( $wraptag == 'table' )
		$break = 'td';
	elseif ( $wraptag == 'ul' or $wraptag == 'ol' )
		$break = 'li';
	
	if ( $break )
	{
		$break_obj = 'soo_html_' . trim(strtolower($break));
		if ( ! class_exists($break_obj) )
			$break_obj = null;
		else {
			$break = '';
			$test_obj = new $break_obj;
			$empty_break = $test_obj->is_empty;
		}
	}
	else
		$break_obj = null;
	
	$page_ids = $soo_multidoc['next_array'];
	
	$total = count($page_ids);
	$page_nums = array_combine($page_ids, range(1, $total));
	$this_num = $page_nums[$thisid];
	
	$w_start = max(1, 
		min($this_num - $limit, $total - ( $limit * 2 ) + 1));
	$w_end = min($w_start + ( $limit * 2 ) - 1, $total);
	
	$show_nums = array_unique(array_merge(
		array(1), range($w_start, $w_end), array($total)
	));
	
	$objs = array();
	
	while ( $n = array_shift($show_nums) )
	{
		if ( $n == $this_num )
			$objs[] = new soo_html_span(array('class' => $active_class), $n);
		else
			$objs[] = new soo_html_anchor(array(
				'href' => $soo_multidoc['data'][$page_ids[$n - 1]]['url'],
				'class' => $class), $n)
			;
			
		$fill = $show_nums ? 
			( $show_nums[0] > $n + 1 ? $placeholder : $break ) : '';
		if ( $fill )
			$objs[] = new soo_html_span('', $fill);
	}
	
	if ( $break_obj )
	{
		if ( $empty_break )
		{
			while ( $objs )
			{
				$broken_objs[] = array_shift($objs);
				$broken_objs[] = new $break_obj;
			}
			array_pop($broken_objs);
			$objs = $broken_objs;
		}
		else
			foreach ( $objs as $i => $obj )
				$objs[$i] = new $break_obj('', $obj);
		foreach ( $objs as $obj )
			if ( $obj instanceof $break_obj )
				$obj->class($breakclass);
	}
	
	if ( $wraptag == 'table' )
		$wrap_obj = new soo_html_tr;
	
	else {
		$wrap_obj_class = 'soo_html_' . $wraptag;
		if ( class_exists($wrap_obj_class) )
			$wrap_obj = new $wrap_obj_class;
	}
	if ( isset($wrap_obj) )
	{
		$wrap_obj->class($wrapclass);
		foreach ( $objs as $obj )
			$wrap_obj->contents($obj);

		if ( $wraptag == 'table' )
		{
			$table = new soo_html_table(array('id' => $html_id),
				new soo_html_tbody('', $wrap_obj));
			return $table->tag();
		}
		else
			return $wrap_obj->id($html_id)->tag();
	}
	else
	{
		$out = array();
		foreach ( $objs as $obj )
			$out[] = $obj->tag();
		return implode("\n", $out);
	}
}
///////////////////// end of soo_multidoc_pager() ///////////////////////

function soo_multidoc_page_number( $atts )
{
// Output tag: display current page number
// Requires article context

	extract(lAtts(array(
		'html_id'		=>	'',
		'class'			=>	'',
 		'wraptag'		=>	'span',
 		'format'		=>	'Page {page} of {total}',
	), $atts));

	global $soo_multidoc;
	if ( ! ( _soo_multidoc_init() and $soo_multidoc['status'] ) ) return false;
	
	global $thisarticle;
	$thisid = $thisarticle['thisid'];
		
	$page_ids = $soo_multidoc['next_array'];
	
	$num_pages = count($page_ids);
	$page_nums = array_flip($page_ids);
	$this_page = $page_nums[$thisid] + 1;
	
	$format = str_replace('{page}', $this_page, $format);
	$format = str_replace('{total}', $num_pages, $format);
	
	return doWrap(array($format), $wraptag, '', $class, '', '', '', $html_id);

}

function soo_multidoc_toc( $atts )
{
// Output tag: display table of contents as tiered list
// Requires article context

	extract(lAtts(array(
 		'wraptag'		=>	'ul',
 		'root'			=>	'',
 		'add_start'		=>	false,
		'html_id'		=>	'',
		'class'			=>	'',
 		'active_class'	=>	'',
	), $atts));
	
	global $soo_multidoc;
	if ( ! ( _soo_multidoc_init() and $soo_multidoc['status'] ) ) return false;
	
	global $thisarticle;
	
	if ( $root )
	{
		$start_id = is_numeric($root) ? intval($root) : $thisarticle['thisid'];
		$object_array = $soo_multidoc['rowset']->subtree($start_id, 'id')->as_object_array();
	}
	else
		$object_array = $soo_multidoc['rowset']->as_object_array(); 

	if ( ! $add_start )
		$object_array = $object_array[1];
		
	array_walk_recursive($object_array, '_soo_multidoc_toc_prep', array('id' => $thisarticle['thisid'], 'class' => $active_class));
	
	$wraptag = trim(strtolower($wraptag));
	if ( ! in_array($wraptag, array('ul', 'ol')) )
		return false;
	$wrap_obj_class = 'soo_html_' . $wraptag;
	
	$out = new $wrap_obj_class(array(), $object_array);
	return $out->class($class)->id($html_id)->tag();

}

function soo_multidoc_page_title( $atts )
{
// Output tag: replacement for <txp:page_title />
// Requires article context
// If a Multidoc non-Start page, Start title will be added to output. 
// Otherwise standard page_title() is returned

	extract(lAtts(array(
 		'separator'		=>	': ',
	), $atts));
	
	global $soo_multidoc, $sitename, $thisarticle;
	if ( ! ( _soo_multidoc_init() and $soo_multidoc['status'] ) ) 
		return page_title($atts);

	$thisid = $thisarticle['thisid'];
	$rs = &$soo_multidoc['rowset']->rows;
	$root = $soo_multidoc['id_root'][$thisid];

	return htmlspecialchars($sitename . $separator . $rs[$root]->title . 
		( $root != $thisid ? $separator . $rs[$thisid]->title : '' )
	);
}	

function soo_multidoc_breadcrumbs( $atts )
{
// Output tag: show higher levels in Collection
// Requires article context

	extract(lAtts(array(
 		'separator'		=>	': ',
	), $atts));
	
	global $soo_multidoc, $thisarticle;
	if ( ! ( _soo_multidoc_init() and $soo_multidoc['status'] ) ) 
		return;
	
	$data = &$soo_multidoc['data'];
	$this_id = $thisarticle['thisid'];
	$crumbs[] = $data[$this_id]['title'];
	
	while ( $this_id = $soo_multidoc['id_parent'][$this_id] and intval($this_id) )
	{
		$tag = new soo_html_anchor(array('href' => $data[$this_id]['url']), 
			escape_title($data[$this_id]['title']));
		array_unshift($crumbs, $tag->tag());
	}
	
	return implode($separator, $crumbs);
}	

function soo_if_multidoc( $atts, $thing )
{
// Conditional: does current page belong to one of the specified Multidoc collections?
// If no collections specified, looks for any Multidoc collection
// Requires article context.

	extract(lAtts(array(
		'start_id'		=>	'',	
	), $atts));
		
	global $soo_multidoc;
	
	if ( ! ( _soo_multidoc_init() and $soo_multidoc['status'] ) )
		return parse(EvalElse($thing, false));
	
	global $thisarticle;
	$thisid = $thisarticle['thisid'];
	$start_ids = do_list($start_id);
	
	$ok = ( ! $start_id or in_array($soo_multidoc['id_root'][$thisid], $start_ids) );
	
	return parse(EvalElse($thing, $ok));
}

  //---------------------------------------------------------------------//
 //							Support Functions							//
//---------------------------------------------------------------------//

function _soo_multidoc_init()
{
// Initialization controller. Most Multidoc tags will return blank if this 
// function returns false.

	global $soo_multidoc;
	
	// only run init() once per page	
	if ( $soo_multidoc['init'] ) return true;
	$soo_multidoc['init'] = true;
	
	global $is_article_list, $thisarticle;

	// Multidoc tags not allowed in lists
	if ( $is_article_list ) return false; 

	// article context check
	assert_article();
	if ( empty($thisarticle) ) return false;
	
	// populate global arrays of Multidoc article IDs
	_soo_multidoc_ids_init($thisid = $thisarticle['thisid']);	

	// is this a Multidoc article?
	if ( empty($soo_multidoc['id_parent'][$thisid]) ) return false;

	// All systems go!!!
	return $soo_multidoc['status'] = true;
}

function _soo_multidoc_ids_init( $thisid = null, $force = false )
{
// populate $soo_multidoc global arrays
// if $thisid is set, populate global data array for this article's collection

	global $soo_multidoc;
	
	if ( is_array($soo_multidoc['id_parent']) and ! $force )
		return true;
	
	$query = new soo_txp_left_join('soo_multidoc', 'textpattern', 'id', 'ID');
	$query->select(array('id', 'root', 'lft', 'rgt', 'children', 'type as link_type'))
		->select_join(array('ID', 'Title as title', 'url_title', 'Section', 'Posted'))
		->order_by(array('root', 'lft'));
		
	// Draft or hidden articles visible in admin only
	if ( @txpinterface != 'admin' ) 
		$query->where_join('Status', 3, '>');	// live or sticky status
	
	if ( ! get_pref('publish_expired_articles') )
		$query->where_clause('(now() <= Expires or Expires = ' . 
			NULLDATETIME . ')');
	
	if ( $thisid )
		$query->in('root', 
			new soo_txp_select('soo_multidoc', array('id' => $thisid), 'root')
		);
	
	switch ( $soo_multidoc['posted_time'] )
	{
		case 'past':
			$query->where_clause('Posted <= now()');
			break;
		case 'future':
			$query->where_clause('Posted > now()');
	}
	
	if ( ! $query->count() )
		return false;
	
	$rs = new soo_multidoc_rowset($query);
	$ids = $rs->field_vals('id');
	$rs->rows = array_combine($ids, $rs->rows);
	$id_parent = array();
	$id_children = array();
		
	foreach ( $rs->rows as $r )
	{
		if ( $r->link_type == 'start' )
			$id_parent[$r->id] = 'Start';
		if ( $r->children )
		{
			$id_children[$r->id] = do_list($r->children);
			foreach ( $id_children[$r->id] as $child )
				$id_parent[$child] = $r->id;
		}
		else
			$id_children[$r->id] = array();
	}
	
	$soo_multidoc['id_parent'] = $id_parent;	// key = id, value = parent id
	$soo_multidoc['id_children'] = $id_children;		// key = id, value = array
	$soo_multidoc['id_root'] = $rs->field_vals('root', 'id');	// key = id, value = root id
	$soo_multidoc['id_link_type'] = $rs->field_vals('link_type', 'id');	// key = id, value = link_type
	$soo_multidoc['rowset'] = $rs;
	
	// if individual article context, populate data array for this collection
	if ( $thisid and array_key_exists($thisid, $id_parent) )
	{
		$next = $soo_multidoc['rowset']->field_vals('id');
		$soo_multidoc['next_array'] = $next;
		array_shift($next);
		foreach ( $soo_multidoc['rowset']->rows as $id => $r )
			$soo_multidoc['data'][$id] = array_merge($r->data, array(
				'parent' => $id_parent[$id],
				'url' => permlinkurl($r->data),
				'next' => array_shift($next),
			));
	}
	return true;
}

/// Convert a row object to an html element object.
//  Intended for use with array_walk_recursive()
function _soo_multidoc_toc_prep ( &$row, $k, $active )
{
	global $soo_multidoc;
	if ( $row->id == $active['id'] )
		$row = new soo_html_span(array('class' => $active['class']), $row->title);
	else
		$row = new soo_html_anchor(array('href' => $soo_multidoc['data'][$row->id]['url']), $row->title);
}

function _soo_multidoc_temp_table ( )
{
// MySQL temporary table to filter Multidoc interior pages from article lists
	global $pretext, $is_article_list, $soo_multidoc;
	if ( ! $is_article_list or
		$pretext['q'] or
		! empty($soo_multidoc['list_all']) or
		! getRow("show tables like 'soo_multidoc'") or
		! _soo_multidoc_ids_init() 
	)
		return;
	$t1 = safe_pfx('textpattern');
	$t2 = safe_pfx('soo_multidoc');
	safe_query(
		"create temporary table $t1 select * from $t1 where ID not in (select id from $t2 where id != root)");
}

register_callback('_soo_multidoc_temp_table', 'pretext_end');

# --- END PLUGIN CODE ---

if (0) {
?>
<!-- CSS & HELP
# --- BEGIN PLUGIN CSS ---
<style type="text/css">
div#sed_help pre {padding: 0.5em 1em; background: #eee; border: 1px dashed #ccc;}
div#sed_help h1, div#sed_help h2, div#sed_help h3, div#sed_help h3 code {font-family: sans-serif; font-weight: bold;}
div#sed_help h1, div#sed_help h2, div#sed_help h3 {margin-left: -1em;}
div#sed_help h2, div#sed_help h3 {margin-top: 2em;}
div#sed_help h1 {font-size: 2.4em;}
div#sed_help h2 {font-size: 1.8em;}
div#sed_help h3 {font-size: 1.4em;}
div#sed_help h4 {font-size: 1.2em;}
div#sed_help h5 {font-size: 1em;margin-left:1em;font-style:oblique;}
div#sed_help li {list-style-type: disc;}
div#sed_help li li {list-style-type: circle;}
div#sed_help li li li {list-style-type: square;}
div#sed_help li a code {font-weight: normal;}
div#sed_help li code:first-child {background: #ddd;padding:0 .3em;margin-left:-.3em;}
div#sed_help li li code:first-child {background:none;padding:0;margin-left:0;}
div#sed_help dfn {font-weight:bold;font-style:oblique;}
div#sed_help .required, div#sed_help .warning {color:red;}
div#sed_help .default {color:green;}
</style>
# --- END PLUGIN CSS ---
# --- BEGIN PLUGIN HELP ---
 <div id="sed_help">

h1. soo_multidoc

*Multidoc* is a system for creating and managing structured, multiple-page documents in the "Textpattern content management system":http://textpattern.com/.

This is just a bare-bones tag reference. All of these tags require that you have set up the *Multidoc* system on your Textpattern website. A full "User Guide":http://ipsedixit.net/txp/24/multidoc is available at the "author's website":http://ipsedixit.net/.

h2. Contents

* "Tags":#tags
** "soo_multidoc_link":#soo_multidoc_link
** "soo_multidoc_pager":#soo_multidoc_pager
** "soo_multidoc_page_number":#soo_multidoc_page_number
** "soo_multidoc_toc":#soo_multidoc_toc
** "soo_multidoc_page_title":#soo_multidoc_page_title
** "soo_multidoc_breadcrumbs":#soo_multidoc_breadcrumbs
** "soo_if_multidoc":#soo_if_multidoc
* "Version history":#history

h2(#tags). Tags

h3(#soo_multidoc_link). soo_multidoc_link

h4. Usage

Generates an HTML anchor element, based on document relationship. Can be used as a single or container tag. Requires individual article context.

pre. <txp:soo_multidoc_link rel="Prev" />
<txp:soo_multidoc_link rel="Next">Continue reading ... </txp:soo_multidoc_link> 

When used as a single tag, the @rel@ value is used for the link text.

h4. Attributes

* @rel="LinkType"@ The "link type":http://www.w3.org/TR/REC-html40/types.html#type-links describing the linked document's relationship to the current page
* @add_title="boolean"@ Add the linked document's title to the link text
* @class="HTML class"@ HTML class attribute value for the anchor tag
* @active_class="HTML class"@ HTML class attribute value if @rel@ refers to the current page (tag will be a @span@ instead of @a@)
* @html_id="HTML ID"@ HTML ID attribute value for the anchor tag
* @wraptag="tag name"@ Tag name (no brackets) for element to wrap the anchor


h3(#soo_multidoc_pager). soo_multidoc_pager

h4. Usage

Generates a numbered page navigation widget. Requires individual article context.

pre. <txp:soo_multidoc_pager />

h4. Attributes

* @limit="integer"@ Number of pages to show before and after the current page before inserting the @placeholder@ text. Default is @0@, no limit (show all).
* @placeholder="text"@ Text to show for missing pages (see @limit@). Default is @&hellip;@ (horizontal ellipsis).
* @class="HTML class"@ HTML class attribute value for anchor tags
* @active_class="HTML class"@ HTML class attribute value for the @span@ tag for the current page
* @html_id="HTML ID"@ HTML ID attribute value for the wraptag
* @wraptag="tag name"@ Tag name (no brackets) for element to wrap the anchor
* @break="mixed"@ Tag name (no brackets) or text to add between items
* @wrapclass="HTML class"@ HTML class attribute for the wraptag
* @breakclass="HTML class"@ HTML class attribute for the breaktag

h3(#soo_multidoc_page_number). soo_multidoc_page_number

h4. Usage

Outputs current page number and total number of pages. Requires individual article context.

pre. <txp:soo_multidoc_page_number />

h4. Attributes

* @class="HTML class"@ HTML class attribute value for the output tag
* @html_id="HTML ID"@ HTML ID attribute value for the output tag
* @wraptag="tag name"@ Tag name (no brackets) for output tag. Default is "span"
* @format="format string"@ Default is "Page {page} of {total}". The tag will output this string, after any occurences of "{page}" have been replaced with the current page number; likewise for "{total}" and the total number of pages.

h3(#soo_multidoc_toc). soo_multidoc_toc

h4. Usage

Generates a structured table of contents. Requires individual article context.

pre. <txp:soo_multidoc_toc />

h4. Attributes

* @class="HTML class"@ HTML class attribute value for the list tag
* @active_class="HTML class"@ HTML class attribute value for the @span@ tag for the current page
* @html_id="HTML ID"@ HTML ID attribute value for the list tag
* @wraptag="tag name"@ Tag name (no brackets) for type of list. *Must be "ul" or "ol"*; the default is "ul"
* @root="mixed"@ starting point for the table of contents. If empty (the default), will show the entire table. If set to an article ID number, will show only the pages below that article in the document tree. If set to "this" or any other word, will use the current page as the root.
* @add_start="boolean"@ If set, the root page is added as the first item in the table. Unset by default.

h3(#soo_multidoc_page_title). soo_multidoc_page_title

h4. Usage

Drop-in replacement for the core @title@ tag. On a *Multidoc* page, adds the Start page title before the page title (unless it is the Start page). Otherwise the tag simply returns standard @title@ output.

pre. <txp:soo_multidoc_page_title />

h4. Attributes

* @separator="text"@ text between title segments; default is ": "

h3(#soo_multidoc_breadcrumbs). soo_multidoc_breadcrumbs

h4. Usage

Output a linked breadcrumb trail, useful for hierarchical collections.

pre. <txp:soo_multidoc_breadcrumbs />

h4. Attributes

* @separator="text"@ text between segments; default is ": "

h3(#soo_if_multidoc). soo_if_multidoc

h4. Usage

Conditional tag. Requires individual article context.

pre. <txp:soo_if_multidoc>
    ...show if true...
<txp:else />
    ...show if false...
</txp:soo_if_multidoc>

h4. Attributes

* @start_id="Txp article ID"@ comma-separated list of Txp article IDs. Empty by default.

*soo_if_multidoc* evaluates whether or not the current page belongs to a *Multidoc* collection. If @start_id@ is set, it evaluates whether or not the current page belongs to one of the specified collections.

Typically you would use this in an article form, or in a form called by an article form.

h2(#history). Version History

h3. 2.0.0 (2011-01-07)

* Bugfix for @soo_multidoc_link@ when combining rel types (e.g. rel="prev Section")

h3. 2.0.b.3 (1/3/2011)

* Miscellaneous tag warning fixes
* Code cleaning to take advantage of the version 2 move to storing Multidoc data in its own table

h3. 2.0.b.2 (9/14/2010)

* Bugfix: collections consisting solely of "only children" and with three or more articles triggered a recursion error.

h3. 2.0.b.1 (9/1/2010)

* Comprehensive backend overhaul, introducing:
** Easier collection management, through the new companion soo_multidoc_admin plugin (required)
** Faster page rendering

h3. 1.0.b.3 (1/27/2010)

* Articles without @live@ or @sticky@ status now excluded
* Expired articles now excluded or not, according to site preference
* New preference for showing past, future, or all articles

h3. 1.0.b.2 (9/18/2009)

* New tags: 
** @soo_multidoc_page_title@ (drop-in replacement for @page_title@)
** @soo_multidoc_breadcrumbs@ breadcrumb trail within Collections
* changed to new *soo_plugin_pref* plugin for preference management

h3. 1.0.b.1 (7/5/2009)

* adapted to v1.0.b.1 of the underlying *soo_txp_obj* library
* code cleaning
* downshift to v2.0 of the GPL

h3. 1.0.a.7 (6/2/2009)

* @soo_multidoc_pager@: 
** changed @html_id@ to only apply to @wraptag@
** added @wrapclass@ and @breakclass@ attributes
** generally improved @break@ behavior
* @soo_multidoc_article@ and @soo_multidoc_article_custom@ no longer needed, and have been eliminated (thanks to "net-carver":http://txp-plugins.netcarving.com/ for the suggestion to use MySQL temporary tables, allowing the replacement of about 270 lines of code with about 12)

h3. 1.0.a.6 (5/23/2009)

* Bug fix for using custom field #10 or higher
* Bug fix for error message when there are no Multidoc articles
* Now uses v0.2 of *soo_plugin_prefs*

h3. 1.0.a.5 (5/1/2009)

* Added @<txp:soo_multidoc_page_number />@.
* Fixed a bug regarding multiple @soo_multidoc_pager@ tags on the same page.
* Added compatability with *soo_plugin_prefs* plugin.

h3. 1.0.a.4 (4/2/2009)

* Added @limit@ and @placeholder@ attributes to @<txp:soo_multidoc_pager />@.

h3. 1.0.a.3 (2/6/2009)

* Fixed @add_title@ bug in @<txp:soo_multidoc_link />@ (caused during upgrade to the newer version of the *soo_txp_obj* library).

h3. 1.0.a.2 (2/5/2009)

* No significant changes, but based on a newer version of the *soo_txp_obj* library.

h3. 1.0.a.1 (2/4/2009)

* Initial release.

p. &nbsp;

 </div>
# --- END PLUGIN HELP ---
-->
<?php
}

?>