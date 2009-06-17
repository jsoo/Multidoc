<?php

// soo_multidoc
//
// Multiple-page document plugin for the Textpattern content management system
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
// 
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with this program. If not, see <http://www.gnu.org/licenses/>.

$plugin['version'] = '1.0.a.7';
$plugin['author'] = 'Jeff Soo';
$plugin['author_uri'] = 'http://ipsedixit.net/';
$plugin['description'] = 'Textpattern plugin';
$plugin['type'] = 1; 

@include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

require_plugin('soo_txp_obj');

  //---------------------------------------------------------------------//
 //									Globals								//
//---------------------------------------------------------------------//

global $soo_multidoc, $plugins;

$soo_multidoc = array(
	
	'custom_field_name'	=>	'Multidoc',
	'custom_field'		=>	'',
	'dev_domain'		=>	'my.development.domain',
	'init'				=>	false,
	'status'			=>	false,
	'list_all'			=>	false,
	'collection'		=>	'',
	'noindex'			=>	'',
	'id_parent'			=>	'',
	'data'				=>	'',
	
);

if ( in_array('soo_plugin_prefs', $plugins) ) {
	require_plugin('soo_plugin_prefs');
	$soo_multidoc = soo_plugin_prefs($soo_multidoc, 'soo_multidoc');
}

  //---------------------------------------------------------------------//
 //							MLP Pack definitions						//
//---------------------------------------------------------------------//

define('SOO_MULTIDOC_PREFIX', 'soo_mdoc');
global $soo_multidoc_strings;
$soo_multidoc_strings = array(
	'start'				=>	'start',
	'up'				=>	'up',
	'next'				=>	'next',
	'prev'				=>	'prev',
	'recursion_error'	=>	'Recursion warning: initialization aborted',
	'custom_field'		=>	'Custom field "',
	'not_found'			=>	'" not found',
	'multiple_listings'	=>	'Multiple listings for at least one article. Start by checking articles ',
	'invalid_id_1'		=>	'Invalid ID detected: Article ',
	'invalid_id_2'		=>	' has a Multidoc listing for article ',
);

register_callback('soo_multidoc_enumerate_strings', 'l10n.enumerate_strings');

function soo_multidoc_enumerate_strings( $event, $step = '', $pre = 0 ) {
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

function soo_multidoc_gTxt( $what , $args = array() ) {
	global $textarray;
	global $soo_multidoc_strings;

	$key = SOO_MULTIDOC_PREFIX . '-' . $what;
	$key = strtolower($key);

	if(isset($textarray[$key]))
		$str = $textarray[$key];

	else {
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


class Soo_Multidoc_Node extends Soo_Obj {

	protected $id				= '';
	protected $link_type		= '';
	protected $title			= '';
	protected $children			= '';
	protected $next				= '';
	protected $prev				= '';
	protected $up				= '';
	

	public function __construct( $id ) {
		if ( is_numeric($id) ) 
			$this->id = intval($id);
	}

	// Getters /////////////////////////////////////
	
	public function get_link_type( $id = null ) { 

		if ( $id == null or $id == $this->id ) {
			return $this->link_type;
		}
		elseif ( is_array($this->children ) ) {
			foreach ( $this->children as $child ) {
				if ( empty($out) )
					$out = $child->get_link_type($id);
			}
		}
		return isset($out) ? $out : false;
	}
		
	public function get_next( $id = null ) { 

		if ( $id == null or $id == $this->id ) {
			return $this->next;
		}
		elseif ( is_array($this->children ) ) {
			foreach ( $this->children as $child ) {
				if ( empty($out) )
					$out = $child->get_next($id);
			}
		}
		return isset($out) ? $out : false;
	}
	
	public function get_prev( $id = null ) { 

		if ( $id == null or $id == $this->id ) {
			return $this->prev;
		}
		elseif ( is_array($this->children ) ) {
			foreach ( $this->children as $child ) {
				if ( empty($out) )
					$out = $child->get_prev($id);
			}
		}
		return isset($out) ? $out : false;
	}
	
	public function get_up( $id = null ) { 

		if ( $id == null or $id == $this->id ) {
			return $this->up;
		}
		elseif ( is_array($this->children ) ) {
			foreach ( $this->children as $child ) {
				if ( empty($out) )
					$out = $child->get_up($id);
			}
		}
		return isset($out) ? $out : false;
	}
		
	// Utilities /////////////////////////////////////
	
	public function set_next_by_id( $id, $next ) {
	
		static $_soo_multidoc_failsafe;
		$_soo_multidoc_failsafe ++;
		if ( $_soo_multidoc_failsafe > 999 ) 
			return soo_multidoc_gTxt('recursion_error');
		
		if ( $id == $this->id ) {
			$this->next = $next;
			return $this->prev;
		}
		
		if ( ! is_array($this->children) )
			return false;
		
		foreach ( $this->children as $child ) {
			$done = $child->set_next_by_id($id, $next);
			if ( $done ) return $done;
		}
		return false;
	}
	
	public function youngest() {
	// return the id of this node's youngest descendant
	
		if ( is_array($this->children) ) {
		
			$my_children = $this->children;
			
			$my_youngest = array_pop($my_children);
			
			$out = $my_youngest->youngest();
		}
		else
			return $this->id;
		
		return $out;
	
	}
	
	public function are_you_my_ancestor( $me, $you ) {
	
		$my_parent = $this->get_up($me);
		if (  $my_parent == $you ) {
			return true;
		}
		elseif ( $my_parent ) {
			return $this->are_you_my_ancestor($my_parent, $you);
		}
		else	
			return false;
	}
	
	public function get_id_by_link_type( $link_type ) {
	
		if ( strtolower($link_type) == $this->link_type ) {
			return $this->id;
		}
		elseif ( is_array($this->children ) ) {
			foreach ( $this->children as $child ) {
				if ( empty($out) )
					$out = $child->get_id_by_link_type($link_type);
			}
		}
		return isset($out) ? $out : false;
	
	}
	
	public function get_next_by_link_type( $id, $link_type ) {
	
		$next = $this->get_next($id);

		if ( $next ) {
			if ( strtolower($this->get_link_type($next)) == $link_type )
				return $next;
			
			else 
				return $this->get_next_by_link_type($next, $link_type);
		}
		else
			return false;
	}
	
	public function get_prev_by_link_type( $id, $link_type ) {
	
		$prev = $this->get_prev($id);

		if ( $prev ) {
			if ( strtolower($this->get_link_type($prev)) == $link_type )
				return $prev;
			
			else 
				return $this->get_prev_by_link_type($prev, $link_type);
		}
		else
			return false;
	}
	
	public function get_sub_node( $id ) {
	
		if ( $this->id == $id )
			return $this;
		
		elseif ( is_array($this->children) )
			foreach ( $this->children as $child ) {
				if ( empty($out) )
					$out = $child->get_sub_node($id);
			}
		
		if ( isset($out) )
			return $out;
	}
	
	public function next_array() {
		
		static $next_array_out;
		global $soo_multidoc;
		
		if ( isset($soo_multidoc['next_array']) )
			return $soo_multidoc['next_array'];
	
		$next_id = $this->get_next();
		
		if ( $next_id ) {
			$next_array_out[] = $next_id;
			$next_node = $soo_multidoc['collection']->get_sub_node($next_id);
			$next_node->next_array();
		}
		$soo_multidoc['next_array'] = $next_array_out;
		return $next_array_out;
	}
		

	function toc( $type, $current_page, $active_class, $include_self ) {
	// returns table of contents for this node, as a list object
		
		global $soo_multidoc;
		
		if ( is_array($this->children) ) {
		
			if ( $type == 'ul' )
				$out = new Soo_Html_Ul;
			elseif ( $type == 'ol' )
				$out = new Soo_Html_Ol;
			else
				return false;

			if ( $include_self ) {
				$li = new Soo_Html_Li;
				$url = $soo_multidoc['data'][$this->id]['url'];
				$anchor = new Soo_Html_Anchor($url);
				$anchor->contents($this->title);
				$li->contents($anchor);
				$out->contents($li);
				unset($anchor);
				unset($li);
				$include_self = false;
			}
		
			foreach ( $this->children as $child ) {
				if ( is_array($child->children) ) {
					if ( $child->id == $current_page ) {
						$item = new Soo_Html_Span;
						$item->class($active_class);
					}
					else {
						$item = new Soo_Html_Anchor;
						$url = $soo_multidoc['data'][$child->id]['url'];
						$item->href($url);
					}
					$item->contents($child->title);
				}

				$li = new Soo_Html_Li;
				if ( isset($item) )
					$li->contents($item);
				$li->contents($child->toc($type, $current_page, $active_class, $include_self));
				$out->contents($li);
				unset($item);
				unset($li);
			}

		}
		else {
			
			if ( $this->id == $current_page ) {
				$out = new Soo_Html_Span;
				$out->class($active_class);
			}
			else {
				$out = new Soo_Html_Anchor;
				$url = $soo_multidoc['data'][$this->id]['url'];
				$out->href($url);
			}
			$out->contents($this->title);
		}
		return isset($out) ? $out : false;
	}

}

///////////////////// end of class Soo_Multidoc_Node ///////////////////////

  //---------------------------------------------------------------------//
 //									Tags								//
//---------------------------------------------------------------------//

function soo_multidoc_link( $atts, $thing = null ) {
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
	
	$start = soo_multidoc_gTxt('start');
	$up = soo_multidoc_gTxt('up');
	$next = soo_multidoc_gTxt('next');
	$prev = soo_multidoc_gTxt('prev');
	
	
	global $thisarticle;
	$collection = $soo_multidoc['collection'];
	$thisid = $thisarticle['thisid'];
	$rel = trim($rel);
	
	// $rel value may be space-separated list of link types
	// this tag allows only 'prev' or 'next' in combination with another type
	if ( preg_match("/^($next|$prev)\s+(\w+)/i", $rel, $match) ) {
		$rel_dir = strtolower($match[1]);
		$rel_type = strtolower($match[2]);
		
		if ( $rel_dir == 'next' )
			$link_id = $collection->get_next_by_link_type($thisid, $rel_type);
		
		// if I have an ancestor of the requested link type, that ancestor will
		// be the prev link of that type, which isn't what the user wants.
		// So continue back one more step.
		elseif ( $rel_dir == 'prev' ) {
			$link_id = $collection->get_prev_by_link_type($thisid, $rel_type);
			if ( $collection->are_you_my_ancestor($thisid, $link_id) ) {
				$next_link = $collection->get_prev_by_link_type($link_id, $rel_type);
				if ( $next_link != $link_id and is_numeric($next_link) )
					$link_id = $next_link;
				else
					unset($link_id);
			}
		}
	}
	elseif (preg_match("/^($next|$prev|$up|$start)$/i", $rel, $match) ) {
		
		switch ( strtolower($match[0]) ) {
			case $start:
				$link_id = $collection->id;
				break;
			case $prev:
				$link_id = $collection->get_prev($thisid);
				break;
			case $next:
				$link_id = $collection->get_next($thisid);
				break;
			case $up:
				$link_id = $collection->get_up($thisid);
				break;
		}
	}
	else
		$link_id = $collection->get_id_by_link_type($rel);
	
	if ( ! empty($link_id) )
		$url = $soo_multidoc['data'][$link_id]['url'];
	
	if ( ! isset($url) ) return false;
	
	if ( $add_title ) {
		$data = new Soo_Txp_Article($link_id);
		$thing .= $data->Title;
		unset($data);
	}
	
	if ( $link_id == $thisid ) {
		$tag = new Soo_Html_Span;
		$tag->class($active_class);
	}
	else {
		$tag = new Soo_Html_Anchor;
		$tag->href($url)
			->rel($rel);
	}
	
	$tag->contents( $thing ? $thing : $rel );
		
	if ( $wraptag ) {
		$tag_class = 'soo_html_' . $wraptag;
		if ( class_exists($tag_class) ) {
			$wraptag = new $tag_class;
			return $wraptag
				->contents($tag)
				->class($class)
				->id($html_id)
				->tag();
		}
	}
	
	else {
		$tag->id($html_id);
		if ( $link_id != $thisid )
			$tag->class($class);
		return $tag->tag();
	}
}
///////////////////// end of soo_multidoc_link() ///////////////////////

function soo_multidoc_pager( $atts ) {
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
	$collection = $soo_multidoc['collection'];
	$thisid = $thisarticle['thisid'];
	
	$wraptag = trim(strtolower($wraptag));
	if ( $wraptag == 'table' )
		$break = 'td';
	elseif ( $wraptag == 'ul' or $wraptag == 'ol' )
		$break = 'li';
	
	if ( $break ) {
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
	
	$page_ids = $collection->next_array();
	array_unshift($page_ids, $collection->id);
	
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
	
	while ( $show_nums ) {
		$n = array_shift($show_nums);
		if ( $n == $this_num )
			$objs[] = new Soo_Html_Span($n, array('class' => $active_class));
		else {
			$url = $soo_multidoc['data'][$page_ids[$n - 1]]['url'];
			$page = new Soo_Html_Anchor($url);
			$objs[] = $page->contents($n)->class($class);
		}			
		$fill = $show_nums ? 
			( $show_nums[0] > $n + 1 ? $placeholder : $break ) : '';
		if ( $fill )
			$objs[] = new Soo_Html_Span($fill);
	}
	
	if ( $break_obj ) {
		if ( $empty_break ) {
			while ( $objs ) {
				$broken_objs[] = array_shift($objs);
				$broken_objs[] = new $break_obj;
			}
			array_pop($broken_objs);
			$objs = $broken_objs;
		}
		else
			foreach ( $objs as $i => $obj )
				$objs[$i] = new $break_obj($obj);
		foreach ( $objs as $obj )
			if ( $obj instanceof $break_obj )
				$obj->class($breakclass);
	}
	
	if ( $wraptag == 'table' )
		$wrap_obj = new Soo_Html_Tr;
	
	else {
		$wrap_obj_class = 'soo_html_' . $wraptag;
		if ( class_exists($wrap_obj_class) )
			$wrap_obj = new $wrap_obj_class;
	}
	if ( isset($wrap_obj) ) {
		$wrap_obj->class($wrapclass);
		foreach ( $objs as $obj )
			$wrap_obj->contents($obj);

		if ( $wraptag == 'table' ) {
			$tbody = new Soo_Html_Tbody;
			$tbody->contents($wrap_obj);
			$table = new Soo_Html_Table;
			return $table->contents($tbody)
				->id($html_id)->tag();
		}
		else
			return $wrap_obj
				->id($html_id)->tag();
	}
	else {
		$out = array();
		foreach ( $objs as $obj )
			$out[] = $obj->tag();
		return implode("\n", $out);
	}
}
///////////////////// end of soo_multidoc_pager() ///////////////////////

function soo_multidoc_page_number( $atts ) {
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
	$collection = $soo_multidoc['collection'];
	$thisid = $thisarticle['thisid'];
		
	$page_ids = $collection->next_array();
	array_unshift($page_ids, $collection->id);
	
	$num_pages = count($page_ids);
	$page_nums = array_flip($page_ids);
	$this_page = $page_nums[$thisid] + 1;
	
	$format = str_replace('{page}', $this_page, $format);
	$format = str_replace('{total}', $num_pages, $format);
	
	return doWrap(array($format), $wraptag, '', $class, '', '', '', $html_id);

}

function soo_multidoc_toc( $atts ) {
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
	$collection = $soo_multidoc['collection'];
	$thisid = $thisarticle['thisid'];

	$wraptag = trim(strtolower($wraptag));
	if ( $wraptag != 'ul' and $wraptag != 'ol' )
		return false;
	
	if ( $root ) {
		if ( is_numeric($root) )
			$start_id = intval($root);
		else
			$start_id = $thisarticle['thisid'];
		$start_node = $collection->get_sub_node($start_id);
		$out = $start_node->toc($wraptag, $thisid, $active_class, $add_start);
	}
	else
		$out = $collection->toc($wraptag, $thisid, $active_class, $add_start);

	return $out
		->class($class)
		->id($html_id)
		->tag();

}	

function soo_if_multidoc( $atts, $thing ) {
// Conditional: does current page belong to one of the specified Multidoc collections?
// If no collections specified, looks for any Multidoc collection
// Requires article context.

	extract(lAtts(array(
		'start_id'		=>	'',	
	), $atts));
		
	global $soo_multidoc;
	
	if ( ! ( _soo_multidoc_init() and $soo_multidoc['status'] ) )
		return parse(EvalElse($thing, false));
	
	$collection = $soo_multidoc['collection'];
	
	$start_ids = do_list($start_id);
	
	$ok = ( ! $start_id or in_array($collection->id, $start_ids) )
		? true : false;
	
	return parse(EvalElse($thing, $ok));
}

  //---------------------------------------------------------------------//
 //							Support Functions							//
//---------------------------------------------------------------------//

function _soo_multidoc_init() {
// The central controller for the intialization routines: retrieve and parse 
// Multidoc field data, build document tree ('collection'). Abort at any sign 
// of trouble. Most Multidoc tags will abort if this function returns false.

	global $soo_multidoc, $is_article_list, $thisarticle, $prefs;
	
	// Multidoc tags not allowed in lists...
	if ( $is_article_list ) return false; 

	// only run init() once per page	
	if ( $soo_multidoc['init'] ) return true;
	
	// populate global arrays of Multidoc article IDs
	_soo_multidoc_ids_init();	
	
	// article context check ...............................................
	assert_article();
	if ( empty($thisarticle) ) {
		$soo_multidoc['init'] = true;
		return false;
	}
	$thisid = $thisarticle['thisid'];
	
	
	// find Multidoc custom field ..........................................
	$custom_field = _soo_multidoc_custom_field();	// e.g. 'custom_2'
	if ( empty($custom_field) ) {
		$soo_multidoc['init'] = true;
		return false;
	}
	
	if ( ! _soo_multidoc_data_init() ) {
		$soo_multidoc['init'] = true;
		return false;
	}

	extract($soo_multidoc);
	
	// is this a Multidoc article?
	if ( ! in_array($thisid, array_keys($id_parent)) ) {
		$soo_multidoc['init'] = true;
		return false;
	}
	
	// Start page for current article's Multidoc collection
	$start = isset($id_root[$thisid]) ? $id_root[$thisid] : $thisid;
	
	
	// build document tree ///////...........................................
	$tree = _soo_multidoc_build_tree($start);
	if ( ! is_array($tree) ) {
		_soo_multidoc_debug($tree);
		return false;
	}
	
	// _soo_multidoc_build_tree() gets everything but the Start node itself
	$collection = new Soo_Multidoc_Node($start);
	$collection->link_type('Start')
		->title($data[$start]['Title'])
		->children($tree);
		
		
	// go back and set 'next' values ///////.................................
	$next_node = array_shift($tree);
	$collection->next($next_node->id);
	unset($next_node);
	$next = $collection->youngest();
	$to_set = $collection->get_prev($next);
	while ( is_numeric($to_set) and $to_set > 0 ) {
		$next_to_set = $collection->set_next_by_id($to_set, $next);
		$next = $to_set;
		$to_set = $next_to_set;
	}
	if ( $to_set ) {
		_soo_multidoc_debug($to_set);
		return false;
	}


	// Success!!! ///////...................................................
	$soo_multidoc['collection'] = $collection;
	unset($collection);
	$soo_multidoc['init'] = true;
	$soo_multidoc['status'] = true;
	return true;
}

function _soo_multidoc_build_tree($start_id) {
	
	global $soo_multidoc;
	extract($soo_multidoc);
	
	static $_soo_multidoc_failsafe;
	static $_soo_multidoc_prev;
	$_soo_multidoc_prev = $start_id;
	$_soo_multidoc_failsafe ++;
	if ( $_soo_multidoc_failsafe > 999 ) 
		return soo_multidoc_gTxt('recursion_warning') . ": ID $start_id";
	
	$out = array();
	
	foreach ( $id_children[$start_id] as $child ) {
	
		extract($data[$child]);
		$node = new Soo_Multidoc_Node($child);
		$node->link_type($link_type)
			->title($Title)
			->up($parent);	// this node's parent
		$node->prev($_soo_multidoc_prev);
		$_soo_multidoc_prev = $child;
		
		if ( isset($id_children[$child]) ) {
			$result = _soo_multidoc_build_tree($child);
			if ( ! is_array($result) )	// recursion limit warning
				return $result;
			$node->children($result);
			$out[$child] = $node;
		}
		else 
			$out[$child] = $node;
		
		unset($node);
	}

	return $out;
}

function _soo_multidoc_custom_field() {
// find Multidoc custom field
	
	global $soo_multidoc, $prefs;
	$f = $soo_multidoc['custom_field'];
	
	if ( $f == -1 )
		return false;
	
	if ( $f )
		return $f;
	
	$name = $soo_multidoc['custom_field_name'];

	foreach ( $prefs as $key => $value )
		if ( preg_match('/^(custom_\d+)/i', $key, $match) )
			if ( $value == $name ) {
				$soo_multidoc['custom_field'] = $match[1];	// e.g. 'custom_2'
				return $match[1];
			}
	_soo_multidoc_debug(
		soo_multidoc_gTxt('custom_field') . $name . soo_multidoc_gTxt('not_found')
	);
	$soo_multidoc['custom_field'] = -1;
	return false;
}

function _soo_multidoc_ids_init() {
// find all articles belonging to Multidoc collections
// check for duplicate listings; abort if found

	global $soo_multidoc;
	
	if ( is_array($soo_multidoc['noindex']) )
		return true;
	
	$noindex = array();
	$id_parent = array();
	$id_link_type = array();
	$id_children = array();
	$duplicates = array();
	
	$custom_field = _soo_multidoc_custom_field();	// e.g. 'custom_2'
	if ( empty($custom_field) ) {
		return false;
	}
	
	$query = new Soo_Txp_Article;
	$regexp = '[[:digit:]]';
	$data = $query->select(array('ID', $custom_field))
		->regexp($regexp, $custom_field)
		->extract_field($custom_field, 'ID');
	unset($query);
	
	foreach ( $data as $parent => $field ) {
		$groups = do_list(strtolower($field));
		foreach ( $groups as $group ) {
			preg_match_all('/\s(\d+)/', $group, $children);
			preg_match('/^\s*(\w+)\s/', $group, $link_type);
			if ( isset($id_children[$parent]) )
				foreach ( $children[1] as $child )
					array_push($id_children[$parent], $child);
			else
				$id_children[$parent] = $children[1];
			foreach ( $children[1] as $child ) {
				if ( array_key_exists($child, $noindex) ) {
					$duplicates[] = $child;
					$duplicates[] = $parent;
				}
				else {
					$noindex[$child] = $parent;
					$id_link_type[$child] = $link_type[1];
				}
			}
		}
	}	

	$duplicates = array_unique($duplicates);
	
	if ( count($duplicates) ) {
		_soo_multidoc_debug(
			soo_multidoc_gTxt('multiple_listings') . implode(', ', $duplicates));
		$soo_multidoc['status'] = false;
		$soo_multidoc['noindex'] = array();
		return false;
	}
	
	$id_parent = $noindex;
	
	$start_ids = array_diff(array_keys($data), array_keys($noindex));
	
	foreach ( $start_ids as $id ) {
		$id_parent[$id] = 'start';
		$id_link_type[$id] = 'start';
	}
	
	$id_root = array();
	foreach ( $noindex as $id => $parent ) 
		$id_root[$id] = _soo_multidoc_find_root($id, $id_parent);
	
	$soo_multidoc['id_parent'] = $id_parent;	// key = id, value = parent id
	$soo_multidoc['id_root'] = $id_root;		// key = id, value = root id
	$soo_multidoc['id_link_type'] = $id_link_type;		// key = id, value = link_type
	$soo_multidoc['id_children'] = $id_children;		// key = id, value = array
	$soo_multidoc['noindex'] = array_keys($noindex);	// just the ids
	return true;

}

function _soo_multidoc_find_root($id, $id_parent) {
// little recursive function to find each article's Collection ID
	if ( is_numeric($id_parent[$id]) )
		return _soo_multidoc_find_root($id_parent[$id], $id_parent);
	return $id;
}

function _soo_multidoc_data_init() {
// assemble master array of Multidoc article info

	global $soo_multidoc, $permlinks;
	extract($soo_multidoc);
	
	if ( is_array($data) )
		return true;
	
	if ( empty($id_parent) or $custom_field == '' or $custom_field == -1 )
			return false;
	
	$query = new Soo_Txp_Article;
	$rs = $query
		->select(array('ID', 'Title', 'url_title', 'Section', 'unix_timestamp(Posted) as posted', $custom_field))
		->rows();
	unset($query);
	
	$out = array();
	
	$all_ids = array();
	
	foreach ( $rs as $r ) {
		$id = $r['ID'];
		$all_ids[$id] = $id;
		if ( in_array($id, array_keys($id_parent)) ) {
			if ( in_array($id, array_keys($id_root)) )
				$r['root'] = $id_root[$id];
			else
				$r['root'] = $id;
			$r['parent'] = $id_parent[$id];
			$r['link_type'] = $id_link_type[$id];
			$r['url'] = _soo_multidoc_url($r);	
			$out[$id] = $r;
		}
	}
	
	$invalid_ids = array_diff(array_keys($id_parent), $all_ids);
	
	if ( count($invalid_ids) ) {
		foreach ( $invalid_ids as $i )
			_soo_multidoc_debug(soo_multidoc_gTxt('invalid_id_1') . $id_parent[$i] .
				soo_multidoc_gTxt('invalid_id_2') . $i);
		return false;
	}
	
	$soo_multidoc['data'] = $out;
	return true;
}

function _soo_multidoc_url( $article_array ) {
// basically a copy of permlinkurl(), to reduce the number of db calls

	global $permlink_mode, $prefs;

	if (isset($prefs['custom_url_func']) and is_callable($prefs['custom_url_func']))
		return call_user_func($prefs['custom_url_func'], $article_array, PERMLINKURL);

	if (empty($article_array)) return;

	extract($article_array);

	$Section = urlencode($Section);
	$url_title = urlencode($url_title);

	switch($permlink_mode) {
		case 'section_id_title':
			if ($prefs['attach_titles_to_permalinks'])
			{
				$out = hu."$Section/$ID/$url_title";
			}else{
				$out = hu."$Section/$ID/";
			}
			break;
		case 'year_month_day_title':
			list($y,$m,$d) = explode("-",date("Y-m-d",$posted));
			$out =  hu."$y/$m/$d/$url_title";
			break;
		case 'id_title':
			if ($prefs['attach_titles_to_permalinks'])
			{
				$out = hu."$ID/$url_title";
			}else{
				$out = hu."$ID/";
			}
			break;
		case 'section_title':
			$out = hu."$Section/$url_title";
			break;
		case 'title_only':
			$out = hu."$url_title";
			break;
		case 'messy':
			$out = hu."index.php?id=$ID";
			break;
	}
	return $out;

}

function _soo_multidoc_debug( $message ) {
// display error message

	global $soo_multidoc;
	
	$prefix = 'soo_multidoc: ';
	$postfix = n;
	
	$domain = $_SERVER['HTTP_HOST'];
	$is_dev_site = $domain == $soo_multidoc['dev_domain'] ? true : false;
	if ( ! $is_dev_site ) {
		$prefix = '<!-- ' . $prefix;
		$postfix = ' -->' . $postfix;
	}
	else
		$postfix = '<br />' . $postfix;
	
	echo $prefix . $message . $postfix;

	$soo_multidoc['init'] = true;
}

function _soo_multidoc_temp_table ( ) {
// MySQL temporary table to filter Multidoc interior pages from article lists
	global $pretext, $is_article_list, $soo_multidoc;
	if ( ! $is_article_list 
		or $pretext['q'] 
		or $soo_multidoc['list_all'] 
		or ! _soo_multidoc_ids_init()
		or empty($soo_multidoc['noindex'])
	)
		return;
	$table = safe_pfx('textpattern');
	safe_query("create temporary table $table 
		select * from $table where ID not in (" 
		. implode(',', $soo_multidoc['noindex']) . ")");
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

This is just a bare-bones tag reference. All of these tags require that you have set up the *Multidoc* system on your Textpattern website. A full "User Guide":http://ipsedixit.net/txp/24/multidoc is available at the "author's website":http://ipsedixit.net/.

h2. Contents

* "Tags":#tags
** "soo_multidoc_link":#soo_multidoc_link
** "soo_multidoc_pager":#soo_multidoc_pager
** "soo_multidoc_page_number":#soo_multidoc_page_number
** "soo_multidoc_toc":#soo_multidoc_toc
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
* @class="HTML class"@ HTML class attribute value for the anchor tag
* @active_class="HTML class"@ HTML class attribute value for the @span@ tag for the current page
* @html_id="HTML ID"@ HTML ID attribute value for the anchor tag
* @wraptag="tag name"@ Tag name (no brackets) for element to wrap the anchor
* @break="mixed"@ Tag name (no brackets) or text to add between items

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

h3. 1.0.a.8 (in development)

* change to new method names in soo_txp_obj dev branch

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

 </div>
# --- END PLUGIN HELP ---
-->
<?php
}

?>