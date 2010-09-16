<?php
$plugin['name'] = 'soo_multidoc_admin';
$plugin['version'] = '0.1.1';
$plugin['author'] = 'Jeff Soo';
$plugin['author_uri'] = 'http://ipsedixit.net/';
$plugin['description'] = 'Administer Multidoc collections';
$plugin['type'] = 3; // admin-side only

if (!defined('txpinterface')) @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

require_plugin('soo_multidoc');
include_once(txpath . '/publish/taghandlers.php'); // for permlinkurl()

if ( @txpinterface == 'admin' ) 
{
	add_privs('soo_multidoc_admin','1,2');
	register_tab('extensions', 'soo_multidoc_admin', 'Multidoc');
	register_callback('soo_multidoc_admin', 'soo_multidoc_admin');
	register_callback('soo_multidoc_admin_css', 'admin_side', 'head_end');
}

// controller: installation check and event router
function soo_multidoc_admin( $event, $step, $message = '' ) 
{
	if ( soo_multidoc_table_exists() )
	{
		if ( $step == 'uninstall' )
			$message .= soo_multidoc_uninstall();
		elseif ( ! soo_multidoc_version(2) )
		{
			$message .= soo_multidoc_gTxt('upgrade');
			$step = 'admin';
		}
		else switch ( $step )
		{
			case 'edit':
			case 'detail':
				if ( $id = intval(gps('id')) )
				{
					if ( ! _soo_multidoc_ids_init($id) )
					{
						$step = 'list';
						$message .= soo_multidoc_gTxt('init_fail');
					}
					elseif ( $step == 'edit' )
						$message .= soo_multidoc_edit($id);
				}
				else
				{
					$message .= soo_multidoc_gTxt('invalid_id');
					_soo_multidoc_ids_init();
					$step = 'list';
				}
				break;
			case 'admin':
				break;
			case 'help':
				break;
			case 'new':
				if ( $new_root = assert_int(gps('new_root')) )
					soo_multidoc_new($new_root);
			case 'delete':
				if ( $delete_id = intval(gps('delete_id')) )
					soo_multidoc_delete($delete_id);
			default:
				$step = 'list';
				_soo_multidoc_ids_init();
		}
	}
	else switch ( $step )
	{
		case 'install':
			$message .= soo_multidoc_install();
			break;
		default:
			$step = 'admin';
	}
		
	soo_multidoc_admin_ui($step, $message);
}

// view: admin interface
function soo_multidoc_admin_ui( $step, $message ) 
{
	pagetop('Multidoc', $message);
	$display[] = hed(soo_multidoc_gTxt('admin_header'), 1);
	
	foreach ( array('list', 'admin', 'help') as $tab )
		$tabs[] = sLink('soo_multidoc_admin', $tab, soo_multidoc_gTxt("tab_$tab"), 'navlink' . ( $tab == $step ? '-active' : '' ));
	$display[] = implode(sp, $tabs);
	
	if ( ! soo_multidoc_table_exists() ) 
	{
		$display[] = soo_multidoc_gTxt('table_missing');
		$display[] = new soo_html_form(
			array(
				'id' => 'soo_multidoc_install_form',
				'action'	=> array(
					'event'		=> 'soo_multidoc_admin',
					'step'		=> 'install',
				),
			),
			sInput('install') .
			eInput('soo_multidoc_admin') .
			'<label>' .
			fInput('submit', 'soo_multidoc_install', gTxt('install')) .
			soo_multidoc_gTxt('install_info')
		);
	}
	
	else switch ( $step )
	{	
		case 'edit':
		case 'detail':
			global $soo_multidoc;
			$collection = $soo_multidoc['rowset']->as_object_array();
			$start = $collection[0];
			if ( $id = intval(gps('id')) and $step == 'edit' )
			{
				$extra['edit_id'] = $id;
				$title = $soo_multidoc['data'][$id]['title'];
			}
			$extra['start_children'] = do_list($start->children);
			array_walk_recursive($collection, '_soo_multidoc_detail', $extra);
			
			if ( $action = gps('action') and in_array($action, array('add', 'change_type')) )
			{
				$form_content = array(
						new soo_html_label(array(), array(
							hed(soo_multidoc_gTxt('select_link_type'), 5),
							new soo_html_select(array(
								'name'		=> 'link_type'
							), soo_multidoc_link_types()))
						),
						br, 
						new soo_html_label(array(), array(
							hed(soo_multidoc_gTxt('create_link_type'), 5),
							new soo_html_input('text', array('name' => 'new_link_type')
							))
						),
						br,
						br,
						new soo_html_input('submit', array('value'	=> $action == 'add' ? gTxt('add') : gTxt('update'))
					));
				if ( $action == 'add' )
					array_unshift($form_content, new soo_html_label(array(), array(
						hed(soo_multidoc_gTxt('select_to_add') . "&ldquo;$title&rdquo;", 4),
						new soo_html_select(array(
							'multiple'	=> 'multiple',
							'name'		=> 'add_nodes[]',
						), soo_non_multidoc_articles(10)))
					));
				if ( $action == 'change_type' )
					array_unshift($form_content, hed(gTxt('update') . " &ldquo;$title&rdquo;", 4));
				$update_form = new soo_html_form(
					array(	// form attributes
						'action'	=> array(
							'event'		=> 'soo_multidoc_admin',
							'step'		=> 'edit',
							'id'		=> $id,
							'action'	=> $action,
						),
						'id'		=> 'soo_multidoc_add_node',
					), $form_content
				);
			}
			$display[] = hed(soo_multidoc_gTxt('collection') . ': ' .$start->title, 2);
			if ( isset($update_form) )
				$display[] = array(new soo_html_ul(array(), $collection), $update_form);
			else
				$display[] = new soo_html_ul(array(), $collection);
			break;
		
		case 'admin':
			$display[] = new soo_html_form(
				array(
					'id'		=> 'soo_multidoc_uninstall_form',
					'action'	=> array(
						'event'		=> 'soo_multidoc_admin',
						'step'		=> 'uninstall',
					),
					'onsubmit'	=> "return verify('" . soo_multidoc_gTxt('uninstall_warning') . "')",
				),
					sInput('uninstall') .
					eInput('soo_multidoc_admin') .
					tag(fInput('submit', 'soo_multidoc_uninstall', 'Uninstall') . soo_multidoc_gTxt('uninstall_info'), 'label')
			);
			break;
		
		case 'help':
			$display[] = new soo_html_ul(array(), array(
				new soo_html_anchor('?event=plugin&amp;step=plugin_help&amp;name=soo_multidoc', 'soo_multidoc help file'),
				new soo_html_anchor('?event=plugin&amp;step=plugin_help&amp;name=soo_multidoc_admin', 'soo_multidoc_admin help file'),
				new soo_html_anchor('http://ipsedixit.net/txp/24/multidoc', 'Online Multidoc user guide'),
				));
			break;
		
		default:
			global $soo_multidoc;
			if ( isset($soo_multidoc['rowset']) )
			{
				$rs =  $soo_multidoc['rowset']->rows;
				foreach ( array_unique($soo_multidoc['id_root']) as $root )
				{
					$items[] = eLink('soo_multidoc_admin', 'detail', 'id', $root, $rs[$root]->title) . ' (' . $rs[$root]->rgt/2 . ')';
				}
			}
			if ( empty($items) )
				$display[] = $step == 'install' ? soo_multidoc_gTxt('upgrade') : soo_multidoc_gTxt('no_records');
			else
				$display[] = new soo_html_ul(array(), $items);
			$display[] = new soo_html_form(
				array(	// form attributes
					'id'		=> 'soo_multidoc_create_new',
					'action'	=> array(
						'event'		=> 'soo_multidoc_admin',
						'step'		=> 'new',
					),
				), array(	// form contents
					hed(soo_multidoc_gTxt('new_collection'), 2), 
					new soo_html_select(array('name' => 'new_root'), soo_non_multidoc_articles()),
					new soo_html_input('submit', array(
						'value' => 'Create'
					))
				)
			);
			if ( ! empty($rs) )
			{
				$id_title = array('default' => '');
				foreach ( $rs as $i => $r )
					if ( in_array($i, $soo_multidoc['id_root']) )
						$id_title[$i] = $r->title;
				$display[] = new soo_html_form(
					array(	// form attributes
						'id'		=> 'soo_multidoc_delete',
						'action'	=> array(
							'event'		=> 'soo_multidoc_admin',
							'step'		=> 'delete',
						),
						'onsubmit'	=> "return verify('" . soo_multidoc_gTxt('delete_warning') . "')",
					), array(	// form contents
						hed(soo_multidoc_gTxt('delete_collection'), 2), 
						new soo_html_select(array('name' => 'delete_id'), $id_title),
						new soo_html_input('submit', array(
							'value' => 'Delete'
						))
					)
				);
			}
	}
	
	$table = new soo_html_table(array(
		'id'		=> 'list',
		'class'		=> 'soo_multidoc_admin',
	), $display);
	echo $table->tag();
	
}

function _soo_multidoc_detail( &$node, $k, $extra )
{
	if ( is_object($node) )
	{
		extract($extra);
		global $soo_multidoc;
		$id = $node->id;
		$widget = new soo_html_span(array('class' => 'edit_widget'));
		$disabled = array('class' => 'disabled');
		if ( $node->link_type != 'start' )
		{
			$parent = $soo_multidoc['id_parent'][$id];
			$me_and_my_sibs = $soo_multidoc['id_children'][$parent];
			$is_only_child = count($me_and_my_sibs) == 1;
			$is_first_child = $me_and_my_sibs[0] == $id;
			$is_primary = in_array($id, $start_children);
		}
		else
			$parent = $me_and_my_sibs = $is_only_child = $is_first_child = $is_primary = null;
			
		foreach ( array(
			'add'		=> '+',
			'left'		=> '&larr;',
			'up'		=> '&uarr;',
			'right'		=> '&rarr;',
			'down'		=> '&darr;',
			'delete'	=> 'X',
		) as $action => $glyph )
		
			if ( ( empty($parent) and $action != 'add' ) or ( $is_primary and $action == 'left' ) or ( $is_only_child and in_array($action, array('right', 'up', 'down')) ) or ( $is_first_child and $action == 'right' ) )
				$widget->contents(new soo_html_span($disabled, $glyph));
			else
			{
				$atts = array('href' => '?' . implode(a, array(
					'event=soo_multidoc_admin',
					'step=edit',
					"id=$id",
					"action=$action",
				)));
				if ( $action == 'delete' )
					$atts['onclick'] = "return verify('" . soo_multidoc_gTxt('delete_warning') . "')";
				$widget->contents(new soo_html_anchor($atts, $glyph));
			}
		
		$node = eLink('article', 'edit', 'ID', $id, $node->title) . $widget->tag() . '<span class="type">[' . eLink('soo_multidoc_admin', 'edit', 'id', $id, $node->link_type, 'action', 'change_type') . ']</span>';
		
//		'<span class="type">[' . $node->link_type . ']</span>';
		if ( isset($edit_id) and $edit_id == $id )
			$node = tag($node, 'div', ' class="highlight"');
	}
}

function soo_multidoc_new($new_root)
{
	$new = new soo_txp_upsert(new soo_txp_row(array(
		'lft'	=> 1,
		'rgt'	=> 2,
		'id'	=> $new_root,
		'root'	=> $new_root,
		'type'	=> 'start',
	), 'soo_multidoc'));
	$new->upsert();
}

function soo_multidoc_delete($delete_id)
{
	$query = new soo_txp_select('soo_multidoc');
	$root_node = $query->where('id', $delete_id)->row();
	$query = new soo_txp_delete('soo_multidoc');
	$query->where('root', $root_node['root'])
		->where('lft', $root_node['lft'], '>=')
		->where('rgt', $root_node['rgt'], '<=')
		->delete();
}

function soo_multidoc_edit( $id )
{
	global $soo_multidoc;
	$parent = $soo_multidoc['id_parent'][$id];
	$root = $soo_multidoc['id_root'][$id];
	if ( is_numeric($parent) )
	{
		$lft = $soo_multidoc['rowset']->rows[$parent]->lft;
		$me_and_my_sibs = $soo_multidoc['id_children'][$parent];
		$my_position = array_search($id, $me_and_my_sibs);
	}
	
	$action = doSlash(gps('action'));
	switch ( $action )
	{
		case 'add':
			if ( $add_nodes = gps('add_nodes') )
			{
				$add_nodes = array_map('intval', $add_nodes);
				$update_children[$id] = array_merge($soo_multidoc['id_children'][$id], $add_nodes);
				$lft = 1;
				$parent = $root;
				foreach ( $add_nodes as $add_node )
				{
					$row_data[] = array(
						'id'	=> $add_node,
						'root'	=> $root,
						'type'	=> soo_multidoc_new_type(),
					);
				}
				$insert = new soo_txp_upsert(new soo_txp_rowset($row_data, 'soo_multidoc'), array('id', 'root', 'type'));
				$insert->upsert();
			}
			break;
		
		case 'delete':
			unset($me_and_my_sibs[$my_position]);
			$update_children[$parent] = $me_and_my_sibs;
			soo_multidoc_delete($id);
			$lft = 1;
			$parent = $root;
			break;
			
		case 'left':
			unset($me_and_my_sibs[$my_position]);
			$update_children[$parent] = $me_and_my_sibs;
			$new_parent = $soo_multidoc['id_parent'][$parent];
			$new_sibs = $soo_multidoc['id_children'][$new_parent];
			$new_position = array_search($parent, $new_sibs);
			$new_sibs = array_merge(
				array_slice($new_sibs, 0, $new_position + 1),
				array($id),
				array_slice($new_sibs, $new_position + 1)
			);
			$update_children[$new_parent] = $new_sibs;
			$parent = $new_parent;
			$lft = $soo_multidoc['rowset']->rows[$parent]->lft;
			break;
			
		case 'right':
			$next_older_sib = $me_and_my_sibs[$my_position - 1];
			$new_sibs = $soo_multidoc['id_children'][$next_older_sib];
			array_push($new_sibs, $id);
			unset($me_and_my_sibs[$my_position]);
			$update_children[$parent] = $me_and_my_sibs;
			$update_children[$next_older_sib] = $new_sibs;
			break;
			
		case 'up':
		case 'down':
			$older = array_slice($me_and_my_sibs, 0, $my_position);
			$younger = array_slice($me_and_my_sibs, $my_position + 1);
			if ( $action == 'up' )
			{
				if ( empty($older) )
					array_push($younger, $id);
				else
				{
					array_unshift($younger, array_pop($older));
					array_push($older, $id);
				}
			}
			elseif ( $action == 'down' )
			{
				if ( empty($younger) )
					array_unshift($older, $id);
				else
				{
					array_push($older, array_shift($younger));
					array_unshift($younger, $id);
				}
			}
			$new_order = array_merge($older, $younger);
			
			$update_children[$parent] = $new_order;
			break;
		case 'change_type':
			if ( $new_type = soo_multidoc_new_type() )
			{
				$query = new soo_txp_upsert('soo_multidoc');
				$query->where('id', $id)
					->set('type', $new_type)
					->upsert();
				_soo_multidoc_ids_init($id, true);
			}
			break;
			
	}
	if ( ! empty($update_children) )
	{
		foreach ( $update_children as $i => $children )
		{
			$query = new soo_txp_upsert('soo_multidoc');
			$query->where('id', $i)
				->set('children', implode(',', $children))
				->upsert();
		}
		soo_multidoc_rebuild_tree($parent, $lft, $root);
		_soo_multidoc_ids_init($root, true);
	}
}

function soo_multidoc_rebuild_tree($parent, $left, $root)
{
	// modified from txplib_db.php rebuild_tree()
	
	$left  = assert_int($left);
	$right = $left + 1;
	
	$query = new soo_txp_select('soo_multidoc');
	$result = $query->select('children')->where('id', $parent)->row();
	if ( current($result) )
		foreach ( do_list(current($result)) as $child )
			$right = soo_multidoc_rebuild_tree($child, $right, $root);

	safe_update(
		'soo_multidoc',
		"lft=$left, rgt=$right",
		"id='$parent' and root='$root'"
	);
	return $right + 1;
}

function soo_non_multidoc_articles( $limit = 0 )
// return array of non-Multidoc article titles indexed by ID
{
	$query = new soo_txp_left_join('textpattern', 'soo_multidoc', 'ID', 'id');
	$rowset = new soo_txp_rowset($query->select(array('ID', 'Title'))
		->where_join_null('id')
		->limit($limit)
		->order_by('LastMod', 'desc'));
	return $rowset->field_vals('Title', 'ID');
}

function soo_multidoc_link_types()
{
	static $link_types = null;
	if ( is_null($link_types) )
	{
		global $soo_multidoc;
		$link_types = array_diff(array_unique($soo_multidoc['id_link_type']), array('start'));
		sort($link_types);
		$link_types = array_combine($link_types, $link_types);
	}
	return $link_types;
}

function soo_multidoc_new_type()
{
	$link_type = doSlash(gps('new_link_type'));
	$link_type = $link_type ? $link_type : doSlash(gps('link_type'));
	return preg_match('/\W/', $link_type) ? null : $link_type;
}

function soo_multidoc_install()
{
	// soo_multidoc table structure
	// id:	article id#
	// root: root article id#
	// children: comma-separated list of child id#s
	// lft: preorder tree value
	// rgt: as above
	// type: link type (Page|Section|Contents etc.)
	safe_query(
		"CREATE TABLE IF NOT EXISTS " . safe_pfx('soo_multidoc') . " (
			`id` int(11) NOT NULL DEFAULT 0,
			`root` int(11) NOT NULL DEFAULT 0,
			`children` varchar(255) NOT NULL DEFAULT '',
			`lft` int(6) NOT NULL DEFAULT '0',
			`rgt` int(6) NOT NULL DEFAULT '0',
			`type` varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY (`id`)
		)"
	);
	if ( ! soo_multidoc_table_exists() )
		return soo_multidoc_gTxt('create_fail');
	
	$message = soo_multidoc_gTxt('create_success');
		
	$rs = new soo_txp_rowset(new soo_txp_select('soo_multidoc'));
	if ( count($rs->rows) )
		return soo_multidoc_gTxt('table_exists');
	if ( ! ( function_exists('_soo_multidoc_ids_init') and soo_multidoc_version() < 2 ) )
		return $message . soo_multidoc_gTxt('convert_unable');
	if ( ! _soo_multidoc_ids_init() )
		return $message . soo_multidoc_gTxt('nothing_to_convert');
	
	global $soo_multidoc;
	extract($soo_multidoc);
	$data = array('lft' => 1, 'rgt' => 2);
	foreach ( array_combine(array_unique($id_root), array_unique($id_root)) + $id_root as $id => $root )
	{
		$data['id'] = $id;
		$data['root'] = $root;
		$data['type'] = isset($id_link_type[$id]) ? $id_link_type[$id] : '';
		$data['children'] = ! empty($id_children[$id]) ? implode(',', $id_children[$id]) : '';
		$rs->add_row($data);
	}
	
	$query = new soo_txp_upsert($rs, array_keys($data));
	
	if ( $query->upsert() and $num_rows = mysql_affected_rows() )
	{
		foreach ( array_unique($id_root) as $root )
			soo_multidoc_rebuild_tree($root, 1, $root);
		$message .= ', ' . $num_rows . soo_multidoc_gTxt('converted_num');
	}
	else
		$message .= soo_multidoc_gTxt('convert_fail');
	
	return $message;
}

function soo_multidoc_uninstall()
{
	if ( safe_query('DROP TABLE IF EXISTS ' . safe_pfx('soo_multidoc') . ';') and ! soo_multidoc_table_exists() )
		return soo_multidoc_gTxt('table_deleted');
	else
		return soo_multidoc_gTxt('table_not_deleted');
}

function soo_multidoc_table_exists( )
{
	return mysql_fetch_row(safe_query("show tables like '%soo_multidoc'"));
}

function soo_multidoc_version( $min = null )
{
	static $cache = null;
	if ( is_null($cache) )
	{
		global $plugins_ver;					//var_dump($plugins_ver);
		$cache = isset($plugins_ver['soo_multidoc']) ? floatval($plugins_ver['soo_multidoc']) : 0;
	}
	return $min = floatval($min) ? $cache >= $min : $cache;
}

global $soo_multidoc_strings;
if ( ! is_array($soo_multidoc_strings) ) $soo_multidoc_strings = array();
$soo_multidoc_strings = array_merge($soo_multidoc_strings, array(
	'admin_header'		=>	'Multidoc Admin',
	'tab_list'			=>	'Collections',
	'tab_detail'		=>	'Detail',
	'tab_admin'			=>	'Admin',
	'tab_help'			=>	'Help',
	'table_missing'		=>	'<b>Multidoc&rsquo;s</b> table does not exist.',
	'table_exists'		=>	'soo_multidoc table already exists.',
	'table_deleted'		=>	'The soo_multidoc table has been deleted. To finish uninstalling, delete the soo_multidoc_admin and soo_multidoc plugins (and soo_plugin_pref and soo_txp_obj if not otherwise required).',
	'table_not_deleted'	=>	'Table deletion failed.',
	'install_info'		=>	' the soo_multidoc table</label>',
	'create_fail'		=>	'Table creation failed.',
	'create_success'	=>	'soo_multidoc table created',
	'convert_unable'		=>	', but failed to check for existing Multidoc records. See "Installation" in plugin help.',
	'nothing_to_convert'=>	'. No existing Multidoc records to convert.',
	'converted_num'		=>	' entries automatically converted from old format.',
	'convert_fail'		=>	', but automatic conversion of existing <b>Multidoc</b> entries failed. This might be because of invalid Multidoc data.',
	'uninstall_info'	=>	' the <b>Multidoc</b> table',
	'uninstall_warning'	=>	'Really delete the Multidoc table? You cannot undo this.',
	'upgrade'			=>	' To complete the upgrade you must now upgrade the soo_multidoc plugin.',
	'init_fail'			=>	'Data initialization failed.',
	'invalid_id'		=>	'Invalid ID value',
	'no_records'		=>	'No Multidoc collections found.',
	'collection'		=>	'Collection',
	'select_to_add'		=>	'Select articles to add under ',
	'select_link_type'	=>	'Select link type',
	'create_link_type'	=>	'or create new link type',
	'new_collection'	=>	'Create new Collection based on',
	'delete_warning'	=>	'This article (and any children) will be removed from Multidoc; this cannot be undone. Proceed?',
	'delete_collection'	=>	'Delete Collection',
));


function soo_multidoc_admin_css( )
{
	if ( gps('event') == 'soo_multidoc_admin' )
		echo <<<EOF
<style type="text/css"><!--
.soo_multidoc_admin ul ul, .soo_multidoc_admin ol ol { margin-left: 3em; }
.soo_multidoc_admin li { margin: 0.5em 0 !important; }
.soo_multidoc_admin span.type 
{ 
	font-size: smaller; 
	color: #aaa;
	float: right;
	margin: 0 2em;
}
table.soo_multidoc_admin  { margin: 0 0 0 35%; }
.soo_multidoc_admin .edit_widget { float: right; margin-left: 2em; }
.soo_multidoc_admin .edit_widget a, .soo_multidoc_admin .edit_widget .disabled
{
	border: 1px solid #bbb;
	padding: 0 0.2em;
	margin:  0 0.2em;
}
.soo_multidoc_admin .edit_widget .disabled { color: #fff; border-color: #fff }
.soo_multidoc_admin .highlight { background: #dfc; }
.left { color: blue; }
--></style>
	
EOF;
}

# --- END PLUGIN CODE ---

if (0) {
?>
<!-- CSS SECTION
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
div#sed_help h6 {font-size: 1em;margin-left:2em;font-style:oblique;}
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
-->
<!-- HELP SECTION
# --- BEGIN PLUGIN HELP ---
 <div id="sed_help">

h1. soo_multidoc_admin

h2. Contents

* "Overview":#overview
* "The Multidoc admin panel":#panel
** "The Collections mini-tab":#collections
** "The Admin mini-tab":#admin
** "The Help mini-tab":#help
* "Detail view":#detail
** "Add pages":#add
** "Move current page":#move
** "Remove current page":#remove
* "Installing":#installing
* "Uninstalling":#uninstalling
* "Upgrading":#upgrading
* "History":#history

h2(#overview). Overview

This is an admin-side plugin for managing "Multidoc":http://ipsedixit.net/txp/24/multidoc collections. If you're new to *Multidoc*, look at the "user guide":http://ipsedixit.net/txp/24/multidoc first.

h2(#panel). The Multidoc admin panel

The *Multidoc* admin panel is located under the "Extensions tab":http://textbook.textpattern.net/wiki/index.php?title=Extensions. It is divided into three areas, accessed by mini-tabs near the top of the page: Collections, Admin, and Help.

h3(#collections). The Collections mini-tab

This is the default view, showing a list of all *Multidoc* Collections and the number of articles in each. Clicking a Collection name brings up the "Detail view":#detail for that Collection.

Below the list are controls for adding and deleting Collections.

h3(#admin). The Admin mini-tab

This allows you to install or uninstall the soo_multidoc database table.

h3(#help). The Help mini-tab

This contains links to the help files for the soo_multidoc and soo_multidoc_admin plugins, plus a link to the online "Multidoc user guide":http://ipsedixit.net/txp/24/multidoc.

h2(#detail). Detail view

Click on a Collection name in the "Collections mini-tab":#collections to bring up the Detail view. This shows all articles in the Collection, with indentation showing the document structure. Clicking an article title brings up that article for editing in the "Write panel":http://textbook.textpattern.net/wiki/index.php?title=Write.

To the right of the title is the article's link type as assigned in *Multidoc*. Clicking on this brings up a form for changing the link type for that article.

To the right of the link type is a set of controls for managing each article's position in the Collection.

h3(#add). Add pages

Clicking a [+] brings up a form for adding pages below that page. That is, new pages will be at the next level of indentation, with the current page as "parent". The list of available pages shows the most recently modified articles that aren't already part of a Collection. Choose an existing link type or create a new one.

h3(#move). Move current page

Clicking one of the arrows ( [&larr;] [&uarr;] [&rarr;] [&darr;] ) moves that page. Moving a page up or down keeps it in the same sub-section (i.e., with the same parent article). Moving it left moves it out one level of indentation, and moving it right moves it in one level.

Some arrow controls are disabled. For example, a page can only be moved right if it has a "sibling" page immediately above. A page can only be moved up or down if it has at least one sibling.

h3(#remove). Remove current page and all sub-pages

Clicking the [X] removes that page and all its sub-pages from the Collection. The articles themselves are not deleted.

h2(#installing). Installing

These instructions are for new *Multidoc* installations. If you are upgrading an existing installation, see "Upgrading":#upgrading, below.

The *Multidoc* system has two required plugins and two optional plugins; make sure to get the latest version of each. "Install and activate":http://textbook.textpattern.net/wiki/index.php?title=Plugins in this order:

* soo_txp_obj %(required)required%
* soo_multidoc %(required)required%
* soo_multidoc_admin (technically optional, but you'll want it)

soo_plugin_pref is optional and can be installed at any point.

To begin creating and managing *Multidoc* Collections, go to the *Multidoc* sub-tab under the "Extensions tab":http://textbook.textpattern.net/wiki/index.php?title=Extensions. (Note that you cannot see the Extensions tab on the "Plugins sub-tab":http://textbook.textpattern.net/wiki/index.php?title=Plugins.) Click the "Install" button to add the soo_multidoc table to your site's database.

Now you are ready to begin adding Collections.

h2(#uninstalling). Uninstalling

For a clean uninstall follow these steps:

* Remove any *Multidoc* tags from your pages, forms, and articles (the "smd_where_used plugin":http://stefdawson.com/sw/plugins/smd_where_used is helpful for this)
* Go to the *Multidoc* sub-tab, click the "Admin" sub-sub-tab, and click "Uninstall"
* Delete plugins in this order:
** soo_multidoc_admin
** soo_multidoc
** soo_txp_obj (if not otherwise needed)
** soo_plugin_pref (if not otherwise needed)

h2(#upgrading). Upgrading

If your site already uses *Multidoc* and you are adding soo_multidoc_admin, follow these steps in order to convert your existing Collection data to the new system: 

* "Install and activate":http://textbook.textpattern.net/wiki/index.php?title=Plugins the latest version of soo_txp_obj
* Install and activate soo_multidoc_admin
* Go to the *Multidoc* sub-tab under the "Extensions tab":http://textbook.textpattern.net/wiki/index.php?title=Extensions and click "Install"
* Install and activate the latest version of soo_multidoc

If all goes correctly, after the third step you will see a message confirming successful conversion of your existing Collections to the new format. Confirm that your Collections work as expected, then you are free to remove the *Multidoc* custom field. 

Note that removing the field name in "Advanced Preferences":http://textbook.textpattern.net/wiki/index.php?title=Advanced_Preferences#Custom_Fields does not remove the data, which could cause unexpected results if you re-use the field for something else. To clear the data run the following query in your database manager, taking great care to get the correct custom field, and backing up your database first:

bc. UPDATE textpattern SET custom_n = ''

%(required)Notes:% 

* Substitute "custom_n" with the correct custom field name as used in the textpattern table (i.e., replace the "n" with the correct number).
* If you use a database table prefix, prepend it to "textpattern" in the query.

h2(#history). Version History

h3. 0.1 (9/2010)

Initial release. Features:

* Dedicated *Multidoc* admin page, under Extensions tab
* List, create, and delete *Multidoc* Collections
* Add, delete, and rearrange pages within Collections
* Collection data now stored in a dedicated database table:
** Custom field no longer required
** Faster page rendering


 </div>
# --- END PLUGIN HELP ---
-->
<?php
}

?>