<?php
/*
Plugin Name: iPaper
Plugin URI: http://www.telesphore.org/plugins/ipaper
Description: Manage and embed Scribd iPaper documents.
Version: 1.2
Author: Telesphore
Author URI: http://www.telesphore.org
*/
include_once dirname(__FILE__).'/scribd.php';
session_start();
class iPaper
{
	const iPaperOptionName = 'ipaper';
	const version = '1.2';

	function getFolder()
	{
		return dirname(plugin_basename(__FILE__));
	}

	function getPluginUrl()
	{
		return get_admin_page_parent().'?page='.iPaper::getFolder()."/ipaper.php&";
	}

	function parse($content)
	{
		$fct = 'render';
		if(is_feed())
		{
			$fct = 'renderRss';
		}
		$content = preg_replace_callback("/\[ipaper([^]]*)\]/i", array('iPaper', $fct), $content);
		return $content;
	}

	function setDefaults()
	{
		if(iPaper::getOption('width')==null)
		{
			iPaper::setOption('width', 500);
		}
		if(iPaper::getOption('height')==null)
		{
			iPaper::setOption('height', 400);
		}
		if(iPaper::getOption('mode')==null)
		{
			iPaper::setOption('mode', 'list');
		}
	}

	function renderRss($matches)
	{
		preg_match_all('/ (\w*)=([^\]\ ]*)/i', $matches[1], $attributes);
		$arguments = array();

		foreach($attributes[1] as $key => $value )
		{
			$arguments[$value] = $attributes[2][$key];
		}
		if (!isset($arguments['id']))
		{
			return "";
		}
		$docId = $arguments['id'];
		$list = iPaper::getOption('docs');
		$doc = $list[$docId];
		return $doc['dlurl'];
	}

	function render($matches)
	{
		preg_match_all('/ (\w*)=([^\]\ ]*)/i', $matches[1], $attributes);
		$arguments = array();

		foreach($attributes[1] as $key => $value )
		{
			$arguments[$value] = $attributes[2][$key];
		}
		if (!isset($arguments['id']))
		{
			return "";
		}

		$docId = $arguments['id'];
		if(!isset($arguments['key']))
		{
			$list = iPaper::getOption('docs');
			$doc = $list[$docId];
			$access_key = $doc['access_key'];
		}
		else
		{
			$access_key = $arguments['key'];
		}
		$w = iPaper::getOption('width');
		$h = iPaper::getOption('height');
		$m = iPaper::getOption('mode');
		if($w)
		{
			$width = $w;
		}
		if($h)
		{
			$height = $h;
		}
		$m = 'list';
		if($m)
		{
			$mode = $m;
		}
		if(isset($arguments['height']))
		{
			$height = $arguments['height'];
		}
		if(isset($arguments['width']))
		{
			$width = $arguments['width'];
		}
		if(isset($arguments['mode']))
		{
			$mode = $arguments['mode'];
		}
		$page = 1;
		if(isset($arguments['page']) && is_numeric($arguments['page']) && $arguments['page']>0)
		{
			$page = $arguments['page'];
		}

		$htmlId = 'ipaper'.rand();
		return '<div id="'.$htmlId.'"></div><script type="text/javascript">iPaper('.$docId.', \''.$access_key.'\', '.$height.', '.$width.', \''.$mode.'\', '.$page.', \''.$htmlId.'\');</script>';
	}

	function addHeader()
	{
		echo '<script type="text/javascript" src="http://www.scribd.com/javascripts/scribd_api.js"></script>';
		echo '<script type="text/javascript" src="'.get_option('siteurl').'/wp-content/plugins/'.iPaper::getFolder().'/ipaper.js"></script>';
	}

	function addAdminHeader()
	{
		if($_GET['page'] == iPaper::getFolder()."/ipaper.php")
		{
			iPaper::addHeader();
			echo '<script type="text/javascript" src="'.get_option('siteurl').'/wp-content/plugins/'.iPaper::getFolder().'/ipaperadmin.js"></script>';
		}
	}

	function adminMenu()
	{
		add_options_page('iPaper', 'iPaper', 'manage_options', str_replace("\\", "/", __FILE__), array('iPaper', 'getSettingsPage'));
		add_management_page('iPaper', 'iPaper', 'edit_posts', str_replace("\\", "/", __FILE__), array('iPaper', 'getListPage'));
	}

	/**
	 * @return Scribd
	 */
	function getScribdInstance()
	{
		if(iPaper::hasScribdLogin())
		{
			$instance = unserialize($_SESSION['scribdinstance']);
			if(!$instance instanceof Scribd)
			{
				$instance = new Scribd(iPaper::getOption('key'), iPaper::getOption('secret'));
				if(!$instance->login(iPaper::getOption('login'), iPaper::getOption('passwd')))
				{
					echo '<div class="error">'.__('Login error!', 'ipaper').'</div>';
				}
				$_SESSION['scribdinstance'] = serialize($instance);
			}
			return $instance;
		}
		else
		{
			echo '<div class="error">'.__('Login error!', 'ipaper').'</div>';
		}
	}

	function getOption($name)
	{
		$vars = get_option(iPaper::iPaperOptionName);
		return $vars[$name];
	}

	function setOption($name, $value)
	{
		$vars = get_option(iPaper::iPaperOptionName);
		$vars[$name] = $value;
		update_option(iPaper::iPaperOptionName, $vars);
	}

	function checkVersion()
	{
		$txt = wp_remote_fopen('http://www.telesphore.org/ipaper/ipaper-version.php?v='.iPaper::version.'&url='.get_bloginfo('url'));
		if($txt)
		{
			$data = explode('|', $txt);
			if(version_compare($data[0], iPaper::version, '>'))
			{
				echo '<td colspan="5" class="plugin-update">'.$data[1].'</td>';
			}
		}
	}

	function getMaxFileSize()
	{
		if(!ini_get('file_uploads')) return __('Upload impossible', 'ipaper');
		return ini_get('upload_max_filesize');
	}

	function getListPage()
	{
		if ($_POST['upload'])
		{
			$scribd = iPaper::getScribdInstance();
			if($scribd instanceof Scribd)
			{
				iPaper::doUpload($scribd);
				if($_POST['ipaper']['sync']==1)
				{
					iPaper::synchronize();
				}
			}
		}
		elseif($_POST['edit'])
		{
			$scribd = iPaper::getScribdInstance();
			if($scribd instanceof Scribd)
			{
				if($scribd->changeSettings($_POST['ipaper']['doc_id'], $_POST['ipaper']['title'], $_POST['ipaper']['desc'], $_POST['ipaper']['access'], $_POST['ipaper']['license'], $_POST['ipaper']['ads'], $_POST['ipaper']['tags'], $_POST['ipaper']['disable_upload_link'], $_POST['ipaper']['disable_print'], $_POST['ipaper']['disable_select_text'], $_POST['ipaper']['disable_about_dialog'], $_POST['ipaper']['disable_info_dialog'], $_POST['ipaper']['disable_view_mode_change'], $_POST['ipaper']['disable_related_docs'], $_POST['ipaper']['paiddoc'], $_POST['ipaper']['page_restriction_type'], $_POST['ipaper'][$_POST['ipaper']['page_restriction_type']], $_POST['ipaper']['allow_search_targeting'], $_POST['ipaper']['obfuscate_numbers'], $_POST['ipaper']['allow_search_indexing'], $_POST['ipaper']['price'], $_POST['ipaper']['list_price']))
				{
					iPaper::doUpload($scribd);
					$_GET['do'] = '';
					if($_POST['ipaper']['sync']==1)
					{
						iPaper::synchronize();
					}
				}
				else
				{
					echo '<div class="error">'.__('Error: Document not updated!', 'ipaper').'</div>';
					$_GET['do'] = 'edit';
					$_GET['doc_id'] = $_POST['ipaper']['doc_id'];
				}
			}
		}
		elseif($_GET['do']=='delete')
		{
			$scribd = iPaper::getScribdInstance();
			if($scribd instanceof Scribd)
			{
				if($scribd->delete($_GET['doc_id']))
				{
					iPaper::synchronize();
				}
				else
				{
					echo '<div class="error">'.__('Error: Document not deleted!', 'ipaper').'</div>';
				}
			}
		}
		elseif($_GET['do']=='sync')
		{
			iPaper::synchronize();
		}
		iPaper::displayDocs();
	}

	function getSettingsPage()
	{
		if($_POST['login'])
		{
			$scribd = new Scribd($_POST['ipaper']['key'], $_POST['ipaper']['secret']);
			if($scribd->login($_POST['ipaper']['login'], $_POST['ipaper']['passwd']))
			{
				iPaper::setOption('login', $_POST['ipaper']['login']);
				iPaper::setOption('passwd', $_POST['ipaper']['passwd']);
				iPaper::setOption('key', $_POST['ipaper']['key']);
				iPaper::setOption('secret', $_POST['ipaper']['secret']);
			}
			else
			{
				echo '<div class="error">'.__('Login error!', 'ipaper').'</div>';
			}
		}
		elseif($_POST['options'])
		{
			if($_POST['ipaper']['width'] && is_numeric($_POST['ipaper']['width'])
			&& $_POST['ipaper']['height'] && is_numeric($_POST['ipaper']['height']))
			{
				iPaper::setOption('width', $_POST['ipaper']['width']);
				iPaper::setOption('height', $_POST['ipaper']['height']);
			}
			else
			{
				echo '<div class="error">'.__('Error: Values must be numeric!', 'ipaper').'</div>';
			}
			if($_POST['ipaper']['mode'])
			{
				iPaper::setOption('mode', $_POST['ipaper']['mode']);
			}
		}
		iPaper::display();
	}

	function doUpload($scribd)
	{
		if($_FILES['ipaper']['tmp_name']['file'] && $_FILES['ipaper']['error']['file']==0)
		{
			$type = strtolower(substr($_FILES['ipaper']['name']['file'], strrpos($_FILES['ipaper']['name']['file'], '.')+1));
			$tmpPath = dirname(__FILE__)."/tmp";
			$file = $_FILES['ipaper']['tmp_name']['file'];
			if(is_writable(dirname(__FILE__)))
			{
				if(!is_dir($tmpPath))
				{
					mkdir($tmpPath);
				}
				if(move_uploaded_file($_FILES['ipaper']['tmp_name']['file'], $tmpPath."/".$_FILES['ipaper']['name']['file']))
				{
					$file = $tmpPath."/".$_FILES['ipaper']['name']['file'];
				}
				else
				{
					$file = $_FILES['ipaper']['tmp_name']['file'];
				}
			}
			if(!$scribd->upload($file, $type, $_POST['ipaper']['access'], $_POST['ipaper']['doc_id']))
			{
				echo '<div class="error">'.__('Error: Upload problem!', 'ipaper').'</div>';
			}
			@unlink($file);
		}
		elseif($_POST['ipaper']['url'])
		{
			$type = substr($_POST['ipaper']['url'], strrpos($_POST['ipaper']['url'], '.')+1);
			if(!$scribd->uploadFromUrl($_POST['ipaper']['url'], $type, $_POST['ipaper']['access'], $_POST['ipaper']['doc_id']))
			{
				echo '<div class="error">'.__('Error: Upload problem!', 'ipaper').'</div>';
			}
		}
	}

	function synchronize()
	{
		$scribd = iPaper::getScribdInstance();
		if($scribd instanceof Scribd)
		{
			$list = $scribd->getList();
			if(is_array($list))
			{
				$docs = array();
				foreach ($list as $doc)
				{
					$id = $doc['doc_id'];
					$doc['stats'] = $scribd->getStats($id);
					$doc['dlurl'] = $scribd->getDownloadUrl($id);
					$docs[$id] = $doc;
				}
				iPaper::setOption('docs', $docs);
			}
		}
	}

	function displayDocs()
	{
		?>
		<div class="wrap">
			<h2>iPaper</h2>
			<div id="poststuff" class="metabox-holder has-right-sidebar">
				<div class="inner-sidebar">
					<?php iPaper::displaySidebar(); ?>
				</div>
				<div id="post-body">
					<div id="post-body-content" class="has-sidebar-content">
						<?php
						if(iPaper::hasScribdLogin())
						{
							if($_GET['do']=="edit" && $_GET['doc_id'])
							{
								iPaper::displayEdit();
							}
							elseif($_GET['do']=='view' && $_GET['doc_id'])
							{
								iPaper::displayView();
							}
							else
							{
								iPaper::displayList();
								iPaper::displayUpload();
							}
							iPaper::displayMiniHelp();
						}
						else
						{
							iPaper::displaySetConfig();
						}
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	function display()
	{
		?>
		<div class="wrap">
			<h2><?php _e('iPaper Settings', 'ipaper') ?></h2>
			<?php if(iPaper::hasScribdLogin()) { ?>
			<strong><a href="<?php bloginfo('url') ?>/wp-admin/tools.php?page=<?php echo iPaper::getFolder() ?>/ipaper.php"><?php _e('List of documents can be find on iPaper page', 'ipaper') ?></a></strong>
			<?php } ?>
			<div id="poststuff" class="metabox-holder">
				<div class="inner-sidebar">
					<?php iPaper::displaySidebar(); ?>
				</div>
				<div id="post-body" class="has-sidebar">
					<div id="post-body-content" class="has-sidebar-content">
						<?php iPaper::displayLogin(); ?>
						<?php iPaper::displayOptions(); ?>
						<?php iPaper::displayHelp(); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	function hasScribdLogin()
	{
		return iPaper::getOption('login') && iPaper::getOption('passwd') &&  iPaper::getOption('key') &&  iPaper::getOption('secret');
	}

	function displaySidebar()
	{
		?>
		<div class="stuffbox">
			<h3><?php _e('How to', 'ipaper') ?></strong></h3>
			<div class="inside"><p>
				<?php _e('You must have a <a href="http://www.scribd.com/">Scribd</a> account to use this plugin.', 'ipaper') ?><br/>
				<?php _e('Once you are logged in you can synchronize your documents and insert them into posts.', 'ipaper') ?><br/>
				<?php _e('Learn more about all features of this plugin in <a href="#help">help</a>', 'ipaper') ?>
				</p>
				<p><strong><?php _e('Thanks for your support!', 'ipaper') ?></strong></p> <?php iPaper::getDonateButton(); ?>
				<p><?php _e('A huge thanks to the people who have donated to support', 'ipaper') ?> <a href="http://www.telesphore.org/plugins/ipaper">iPaper</a> <?php _e('development, I will keep on working to enhance it!', 'ipaper') ?></p>
			</div>
		</div>
		<?php
	}

	function displaySetConfig()
	{
		?>
		<div class="stuffbox">
			<h3><?php _e('Plugin not configured', 'ipaper') ?></h3>
			<div class="inside">
				<p><?php _e("Your administrator has not finished to configure iPaper, you can't use it for now.", 'ipaper') ?></p>
				<p><?php _e('Go to', 'ipaper') ?> <a href="<?php echo get_bloginfo('url').'/wp-admin/options-general.php?page='.iPaper::getFolder() ?>/ipaper.php"><?php _e('settings page', 'ipaper') ?></a></p>
			</div>
		</div>
		<?php
	}

	function displayList()
	{
		$list = iPaper::getOption('docs');
		$status = array('DONE'=>__('Published', 'ipaper'), 'PROCESSING'=>__('Converting...', 'ipaper'), 'ERROR'=>__('Error', 'ipaper'), 'DISPLAYABLE'=>__('Displayable', 'ipaper'));
		?>
		<div class="stuffbox">
			<h3><a style="float:right" href="#upload"><?php _e('Upload a new document', 'ipaper') ?></a><?php _e('Documents list', 'ipaper') ?> <small>(<?php echo sprintf(__('%s documents,', 'ipaper'), count($list)) ?> <a href="<?php echo iPaper::getPluginUrl().'do=sync' ?>"><?php _e('Synchronize', 'ipaper') ?></a>)</small></h3>
			<div class="inside">
				<?php if(count($list)) { ?>
				<table id="ipaper-listtable" width="100%" cellspacing="0" cellpadding="5">
					<thead>
						<tr>
							<td width="30%"><strong><?php _e('Title', 'ipaper') ?></strong></td>
							<td width="30%"><strong><?php _e('Description', 'ipaper') ?></strong></td>
							<td width="10%"><strong><?php _e('Pages', 'ipaper') ?></strong></td>
							<td width="10%"><strong><?php _e('Read', 'ipaper') ?></strong></td>
							<td width="10%"><strong><?php _e('Status', 'ipaper') ?></strong></td>
							<td width="10%"><strong><?php _e('Actions', 'ipaper') ?></strong></td>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ($list as $doc)
						{
							$doc = array_map('trim', $doc);
							$doc = array_map('stripslashes', $doc);
						?>
						<tr class="ipaper-greyline" id="doc<?php echo $doc['doc_id']; ?>">
							<td valign="top"><?php echo $doc['title']; ?></td>
							<td valign="top"><?php if(strlen($doc['description'])>200) { echo substr($doc['description'], 0, 200)."..."; } else { echo $doc['description']; } ?></td>
							<td valign="top"><?php echo $doc['page_count']; ?></td>
							<td valign="top"><?php echo $doc['stats']; ?></td>
							<td valign="top"><?php echo $status[$doc['conversion_status']]; ?></td>
							<td valign="top"><a class="ipaper-view" href="<?php echo iPaper::getPluginUrl()."do=view&doc_id=".$doc['doc_id']; ?>"><?php _e('View', 'ipaper') ?></a> <a href="<?php echo iPaper::getPluginUrl()."do=edit&doc_id=".$doc['doc_id']; ?>" class="ipaper-edit"><?php _e('Edit', 'ipaper') ?></a> <a href="<?php echo iPaper::getPluginUrl()."do=delete&doc_id=".$doc['doc_id']; ?>" title="<?php _e('Are you sure to delete this document?', 'ipaper') ?>" class="ipaper-delete"><?php _e('Delete', 'ipaper') ?></a></td>
						</tr>
						<?php }?>
					</tbody>
				</table>
				<?php
				} else echo __('No document, try to', 'ipaper').' <a href="'.get_admin_page_parent().'?page='.iPaper::getFolder().'/ipaper.php&do=sync">'.__('synchronize', 'ipaper').'</a>'; ?>
			</div>
		</div>
		<?php
	}

	function displayEdit()
	{
		$scribd = iPaper::getScribdInstance();
		if($scribd instanceof Scribd)
		{
			$infos = $scribd->getSettings($_GET['doc_id']);
			$infos = array_map('trim', $infos);
			$infos = array_map('stripslashes', $infos);
		?>
		<div class="stuffbox">
			<h3><?php _e('Edit document', 'ipaper') ?> (<a href="<?php echo iPaper::getPluginUrl(); ?>"><?php _e('Cancel and back to list', 'ipaper') ?></a> <?php _e('or', 'ipaper') ?> <a href="<?php echo iPaper::getPluginUrl()."do=view&doc_id=".$_GET['doc_id']; ?>"><?php _e('view document', 'ipaper') ?></a>)</h3>
			<div class="inside">
				<form method="post" action="" class="ipaper-form" enctype="multipart/form-data">
					<input type="hidden" name="ipaper[doc_id]" value="<?php echo $_GET['doc_id']; ?>"/>
					<ul>
						<li>
							<label for="title"><?php _e('Title:', 'ipaper') ?></label>
							<input type="text" size="50" name="ipaper[title]" id="title" value="<?php echo $infos['title']; ?>"/>
						</li>
						<li>
							<label for="description"><?php _e('Description:', 'ipaper') ?></label>
							<textarea name="ipaper[desc]" rows="5" cols="40" id="description"><?php echo $infos['description']; ?></textarea>
						</li>
						<li>
							<label for="access"><?php _e('Access:', 'ipaper') ?></label>
							<select name="ipaper[access]" id="access">
								<option value="public" <?php if($infos['access']=='public') { echo 'selected="selected"'; } ?>><?php _e('Public', 'ipaper') ?></option>
								<option value="private" <?php if($infos['access']=='private') { echo 'selected="selected"'; } ?>><?php _e('Private', 'ipaper') ?></option>
							</select>
						</li>
						<li>
							<label for="tags"><?php _e('Tags:', 'ipaper') ?></label>
							<input type="text" size="50" id="tags" name="ipaper[tags]" value="<?php echo $infos['tags']; ?>"/>
						</li>
						<li>
							<label for="ads"><?php _e('Show ads:', 'ipaper') ?></label>
							<select name="ipaper[ads]" id="ads">
								<option value="default" <?php if($infos['show_ads']=='default') { echo 'selected="selected"'; } ?>><?php _e('Account default', 'ipaper') ?></option>
								<option value="true" <?php if($infos['show_ads']=='true') { echo 'selected="selected"'; } ?>><?php _e('Yes', 'ipaper') ?></option>
								<option value="false" <?php if($infos['show_ads']=='false') { echo 'selected="selected"'; } ?>><?php _e('No', 'ipaper') ?></option>
							</select>
						</li>
						<li>
							<label for="license"><?php _e('License:', 'ipaper') ?></label>
							<select name="ipaper[license]" id="license">
								<option value="by" <?php if($infos['license']=='by') { echo 'selected="selected"'; } ?>><?php _e('Attribution alone', 'ipaper') ?></option>
								<option value="by-nc" <?php if($infos['license']=='by-nc') { echo 'selected="selected"'; } ?>><?php _e('Attribution + Noncommercial', 'ipaper') ?></option>
								<option value="by-nc-nd" <?php if($infos['license']=='by-nc-nd') { echo 'selected="selected"'; } ?>><?php _e('Attribution + NoDerivs', 'ipaper') ?></option>
								<option value="by-nc-sa" <?php if($infos['license']=='by-nc-sa') { echo 'selected="selected"'; } ?>><?php _e('Attribution + ShareAlike', 'ipaper') ?></option>
								<option value="by-nd" <?php if($infos['license']=='by-nd') { echo 'selected="selected"'; } ?>><?php _e('Attribution + Noncommercial + NoDerivs', 'ipaper') ?></option>
								<option value="by-sa" <?php if($infos['license']=='by-sa') { echo 'selected="selected"'; } ?>><?php _e('Attribution + Noncommercial + ShareAlike', 'ipaper') ?></option>
								<option value="c" <?php if($infos['license']=='c') { echo 'selected="selected"'; } ?>><?php _e('Copyright', 'ipaper') ?></option>
								<option value="pd" <?php if($infos['license']=='pd') { echo 'selected="selected"'; } ?>><?php _e('Public domain', 'ipaper') ?></option>
							</select>
						</li>
						<li>
							<strong><?php _e("Next 7 fields values can't be retrieved, then options won't be checked here but your choices will be saved at Scribd.", 'ipaper') ?></strong>
							<br/><strong><?php _e("Don't forget to set these options each time you update your document!", 'ipaper') ?></strong>
							<br/><?php _e("These options are put in cache by Scribd, wait some minutes to see changes.", 'ipaper') ?>
						</li>
						<li>
							<label for="disable_upload_link"><?php _e('Disable upload link:', 'ipaper') ?></label>
							<input type="checkbox" name="ipaper[disable_upload_link]" value="1" id="disable_upload_link"/>
						</li>
						<li>
							<label for="disable_print"><?php _e('Disable print:', 'ipaper') ?></label>
							<input type="checkbox" name="ipaper[disable_print]" value="1" id="disable_print"/>
						</li>
						<li>
							<label for="disable_select_text"><?php _e('Disable select text:', 'ipaper') ?></label>
							<input type="checkbox" name="ipaper[disable_select_text]" value="1" id="disable_select_text"/>
						</li>
						<li>
							<label for="disable_about_dialog"><?php _e('Disable about dialog:', 'ipaper') ?></label>
							<input type="checkbox" name="ipaper[disable_about_dialog]" value="1" id="disable_about_dialog"/>
						</li>
						<li>
							<label for="disable_info_dialog"><?php _e('Disable info dialog:', 'ipaper') ?></label>
							<input type="checkbox" name="ipaper[disable_info_dialog]" value="1" id="disable_info_dialog"/>
						</li>
						<li>
							<label for="disable_view_mode_change"><?php _e('Disable view mode change:', 'ipaper') ?></label>
							<input type="checkbox" name="ipaper[disable_view_mode_change]" value="1" id="disable_view_mode_change"/>
						</li>
						<li>
							<label for="disable_related_docs"><?php _e('Disable related documents:', 'ipaper') ?></label>
							<input type="checkbox" name="ipaper[disable_related_docs]" value="1" id="disable_related_docs"/>
						</li>
						<li>
							<br/>
							<label for="paiddoc"><?php _e("It's a paid document:", 'ipaper') ?></label>
							<input type="checkbox" name="ipaper[paiddoc]" value="1" id="paiddoc"/>
						</li>
						<li class="hidden">
							<label for="page_restriction_type"><?php _e('Type of page restriction:', 'ipaper') ?></label>
							<select name="ipaper[page_restriction_type]" id="page_restriction_type">
								<option value="automatic"<?php if($infos['page_restriction_type']=='automatic') { echo 'selected="selected"'; } ?>><?php _e('By default', 'ipaper') ?></option>
								<option value="max_pages"<?php if($infos['page_restriction_type']=='max_pages') { echo 'selected="selected"'; } ?>><?php _e('Maximum pages', 'ipaper') ?></option>
								<option value="max_percentage"<?php if($infos['page_restriction_type']=='max_percentage') { echo 'selected="selected"'; } ?>><?php _e('Maximum percentage', 'ipaper') ?></option>
								<option value="page_range"<?php if($infos['page_restriction_type']=='page_range') { echo 'selected="selected"'; } ?>><?php _e('Page range', 'ipaper') ?></option>
							</select>
							<div class="hidden" id="restriction-max_pages">
							<label for="page_restriction_value-1"><?php _e('Number:', 'ipaper') ?></label>
							<input type="text" name="ipaper[max_pages]" id="page_restriction_value-1" value="<?php echo $infos['max_pages']; ?>"/>
							</div>
							<div class="hidden" id="restriction-max_percentage">
							<label for="page_restriction_value-2"><?php _e('Percent:', 'ipaper') ?></label>
							<input type="text" name="ipaper[max_percentage]" id="page_restriction_value-2" value="<?php echo $infos['max_percentage']; ?>"/>
							</div>
							<div class="hidden" id="restriction-page_range">
							<label for="page_restriction_value-3"><?php _e('Range:', 'ipaper') ?></label>
							<input type="text" name="ipaper[page_range]" id="page_restriction_value-3" value="<?php echo $infos['page_range']; ?>"/>
							</div>
						</li>
						<li class="hidden">
							<label for="allow_search_targeting"><?php _e('Allow Scribd to vary the preview pages:', 'ipaper') ?></label>
							<select name="ipaper[allow_search_targeting]" id="allow_search_targeting">
								<option value="true" <?php if($infos['allow_search_targeting']=='true') { echo 'selected="selected"'; } ?>><?php _e('Yes', 'ipaper') ?></option>
								<option value="false" <?php if($infos['allow_search_targeting']=='false') { echo 'selected="selected"'; } ?>><?php _e('No', 'ipaper') ?></option>
							</select>
						</li>
						<li class="hidden">
							<label for="obfuscate_numbers"><?php _e('Obfuscate all numbers in document:', 'ipaper') ?></label>
							<select name="ipaper[obfuscate_numbers]" id="obfuscate_numbers">
								<option value="true" <?php if($infos['obfuscate_numbers']=='true') { echo 'selected="selected"'; } ?>><?php _e('Yes', 'ipaper') ?></option>
								<option value="false" <?php if($infos['obfuscate_numbers']=='false') { echo 'selected="selected"'; } ?>><?php _e('No', 'ipaper') ?></option>
							</select>
						</li>
						<li class="hidden">
							<label for="allow_search_indexing"><?php _e('Allow search engine to index the document:', 'ipaper') ?></label>
							<select name="ipaper[allow_search_indexing]" id="allow_search_indexing">
								<option value="true" <?php if($infos['allow_search_indexing']=='true') { echo 'selected="selected"'; } ?>><?php _e('Yes', 'ipaper') ?></option>
								<option value="false" <?php if($infos['allow_search_indexing']=='false') { echo 'selected="selected"'; } ?>><?php _e('No', 'ipaper') ?></option>
							</select>
						</li>
						<li class="hidden">
							<label for="price"><?php _e('Price:', 'ipaper') ?></label>
							<input type="text" size="5" id="price" name="ipaper[price]" value="<?php echo $infos['price']; ?>"/>
						</li>
						<li class="hidden">
							<label for="list_price"><?php _e('Price shown:', 'ipaper') ?></label>
							<input type="text" size="5" id="list_price" name="ipaper[list_price]" value="<?php echo $infos['list_price']; ?>"/>
						</li>
					</ul>
					<style>
					.hidden { display: none; }
					</style>
					<script type="text/javascript">
					jQuery(document).ready(function(){
						jQuery('#paiddoc').click(function(){
							if(jQuery(this).is(':checked')) { jQuery("li.hidden").show(); }
							else { jQuery("li.hidden").hide(); }
						});
						jQuery("#page_restriction_type").change(function(){
							jQuery("div.hidden").hide();
							if(jQuery(this)[0].value=='max_pages') { jQuery("#restriction-max_pages").css('display', 'inline'); }
							if(jQuery(this)[0].value=='max_percentage') { jQuery("#restriction-max_percentage").css('display', 'inline'); }
							if(jQuery(this)[0].value=='page_range') { jQuery("#restriction-page_range").css('display', 'inline'); }
						}).change();
					});
					</script>
					<br/><fieldset>
					<legend><strong><?php _e('Update file', 'ipaper') ?></strong></legend>
					<ul>
						<li>
							<label for="file"><?php _e('Upload a new file:', 'ipaper') ?></label>
							<input type="file" name="ipaper[file]" id="file"/> <?php echo sprintf(__('Maximum filesize: %s,', 'ipaper'), iPaper::getMaxFileSize()) ?> <?php _e('for bigger upload use <a href="http://www.scribd.com/upload">Scribd interface</a>. Learn why in <a href="#help">help</a>', 'ipaper') ?>
						</li>
						<li>
							<label for="url"><?php _e('Upload a new file from URL:', 'ipaper') ?></label>
							<input type="text" size="50" name="ipaper[url]" id="url" value="<?php $_POST['ipaper']['url'] ?>"/>
						</li>
					</ul>
					</fieldset>
					<ul>
						<li>
							<label for="sync"><?php _e('Synchronize list after save:', 'ipaper') ?></label>
							<input type="checkbox" name="ipaper[sync]" id="sync" value="1" <?php if($_POST['ipaper']['sync']==1) echo 'checked="checked"'; ?>/>
						</li>
					</ul>
					<input type="submit" class="button-primary" name="edit" value="<?php _e('Save', 'ipaper') ?>"/>
				</form>
			</div>
		</div>
		<?php
		}
	}

	function displayView()
	{
		$list = iPaper::getOption('docs');
		$doc = $list[$_GET['doc_id']];
		$key = $doc['access_key'];
		$title = $doc['title'];
		?>
		<div class="stuffbox">
			<h3><?php _e('View document', 'ipaper') ?> (<a href="<?php echo iPaper::getPluginUrl(); ?>"><?php _e('Back to list', 'ipaper') ?></a> <?php _e('or', 'ipaper') ?> <a href="<?php echo iPaper::getPluginUrl()."do=edit&doc_id=".$_GET['doc_id']; ?>"><?php _e('edit document', 'ipaper') ?></a>)</h3>
			<div class="inside">
				<p><strong><?php echo $title; ?></strong></p>
				<div id="<?php echo $_GET['doc_id'] ?>"></div>
				<script type="text/javascript">iPaper('<?php echo $_GET['doc_id'] ?>', '<?php echo $key ?>', '<?php echo iPaper::getOption('height') ?>', '<?php echo iPaper::getOption('width') ?>', '<?php echo iPaper::getOption('mode') ?>', '1', '<?php echo $_GET['doc_id'] ?>');</script>
			</div>
		</div>
		<?php
	}

	function displayLogin()
	{
		$login = iPaper::getOption('login');
		$passwd = iPaper::getOption('passwd');
		$key = iPaper::getOption('key');
		$secret = iPaper::getOption('secret');
		if($_POST['ipaper']['login']) $login = $_POST['ipaper']['login'];
		if($_POST['ipaper']['passwd']) $passwd = $_POST['ipaper']['passwd'];
		if($_POST['ipaper']['key']) $key = $_POST['ipaper']['key'];
		if($_POST['ipaper']['secret']) $secret = $_POST['ipaper']['secret'];
		?>
		<a name="login"></a>
		<div class="stuffbox">
			<h3><?php _e('Enter your Scribd account informations', 'ipaper') ?></h3>
			<div class="inside">
				<form method="post" action="" class="ipaper-form">
					<ul>
						<li>
							<label for="login"><?php _e('Login:', 'ipaper') ?></label>
							<input type="text" name="ipaper[login]" id="login" value="<?php echo $login; ?>"/>
						</li>
						<li>
							<label for="passwd"><?php _e('Password:', 'ipaper') ?></label>
							<input type="password" name="ipaper[passwd]" id="passwd" value="<?php echo $passwd; ?>"/>
						</li>
						<li>
							<label for="key"><?php _e('API Key:', 'ipaper') ?></label>
							<input type="text" size="40" name="ipaper[key]" id="key" value="<?php echo $key; ?>"/> <a href="#help"><?php _e('Need help?', 'ipaper') ?></a>
						</li>
						<li>
							<label for="secret"><?php _e('API Secret:', 'ipaper') ?></label>
							<input type="text" size="40" name="ipaper[secret]" id="secret" value="<?php echo $secret; ?>"/>
						</li>
					</ul>
					<input type="submit" class="button-primary" name="login" value="<?php _e('Save', 'ipaper') ?>"/>
				</form>
			</div>
		</div>
		<?php
	}

	function displayUpload()
	{
		?>
		<a name="upload"></a>
		<div class="stuffbox">
			<h3><?php _e('Upload a new document', 'ipaper') ?></h3>
			<div class="inside">
				<p><?php _e('You can upload a file or upload from url', 'ipaper') ?></p>
				<form method="post" action="" class="ipaper-form" enctype="multipart/form-data">
					<ul>
						<li>
							<label for="file"><?php _e('File:', 'ipaper') ?></label>
							<input type="file" name="ipaper[file]" id="file"/> <?php echo sprintf(__('Maximum filesize: %s,', 'ipaper'), iPaper::getMaxFileSize()) ?> <?php _e('for bigger upload use <a href="http://www.scribd.com/upload">Scribd interface</a>. Learn why in <a href="#help">help</a>', 'ipaper') ?>
						</li>
						<li>
							<label for="url"><?php _e('URL:', 'ipaper') ?></label>
							<input type="text" size="50" name="ipaper[url]" id="url" value="<?php $_POST['ipaper']['url'] ?>"/>
						</li>
						<li>
							<label for="access"><?php _e('Access:', 'ipaper') ?></label>
							<select name="ipaper[access]" id="access">
								<option value="public" <?php if($_POST['ipaper']['acces']=='public') echo 'selected="selected"'; ?>><?php _e('Public', 'ipaper') ?></option>
								<option value="private" <?php if($_POST['ipaper']['acces']=='private') echo 'selected="selected"'; ?>><?php _e('Private', 'ipaper') ?></option>
							</select>
						</li>
						<li>
							<label for="sync"><?php _e('Synchronize list after upload:', 'ipaper') ?></label>
							<input type="checkbox" name="ipaper[sync]" id="sync" value="1" <?php if($_POST['ipaper']['sync']==1) echo 'checked="checked"'; ?>/>
						</li>
					</ul>
					<input type="submit" class="button-primary" name="upload" value="<?php _e('Upload', 'ipaper') ?>"/>
				</form>
			</div>
		</div>
		<?php
	}

	function displayOptions()
	{
		$width = iPaper::getOption('width');
		$height = iPaper::getOption('height');
		$mode = iPaper::getOption('mode');
		?>
		<div class="stuffbox">
			<h3><?php _e('Default options', 'ipaper') ?></h3>
			<div class="inside">
				<form method="post" action="" class="ipaper-form">
					<ul>
						<li>
							<label for="width"><?php _e('Width:', 'ipaper') ?></label>
							<input type="text" size="5" name="ipaper[width]" id="width" value="<?php echo $width; ?>"/>px
						</li>
						<li>
							<label for="height"><?php _e('Height:', 'ipaper') ?></label>
							<input type="text" size="5" name="ipaper[height]" id="height" value="<?php echo $height; ?>"/>px
						</li>
						<li>
							<label for="mode"><?php _e('Mode:', 'ipaper') ?></label>
							<select name="ipaper[mode]" id="mode">
								<option value="list" <?php if ($mode == 'list') echo 'selected'; ?>><?php _e('List', 'ipaper') ?></option>
								<option value="book" <?php if ($mode == 'book') echo 'selected'; ?>><?php _e('Book', 'ipaper') ?></option>
								<option value="slide" <?php if ($mode == 'slide') echo 'selected'; ?>><?php _e('Slideshow', 'ipaper') ?></option>
								<option value="tile" <?php if ($mode == 'tile') echo 'selected'; ?>><?php _e('Tile', 'ipaper') ?></option>
						</li>
					</ul>
					<input type="submit" class="button-primary" name="options" value="<?php _e('Save', 'ipaper') ?>"/>
				</form>
			</div>
		</div>
		<?php
	}

	function displayHelp()
	{
		?>
		<a name="help"></a>
		<div class="stuffbox">
			<h3><?php _e('Help', 'ipaper') ?></h3>
			<div class="inside">
			<h4><?php _e('Use the plugin step by step', 'ipaper') ?></h4>
			<ol style="font-weight: bold;">
				<li><span style="font-weight: normal"><?php _e('Create your <a href="http://www.scribd.com">Scribd</a> account and signup for an <a href="http://www.scribd.com/publisher/signup_api_details">API account</a>', 'ipaper') ?></span></li>
				<li><span style="font-weight: normal"><?php _e('Set your login, password, API key and API secret into', 'ipaper'); ?> <a href="#login"?><?php _e('account informations', 'ipaper') ?></a> <?php _e('(You can find all these datas in your <a href="http://www.scribd.com/people/account">Scribd profile</a>)', 'ipaper'); ?></span></li>
				<li><span style="font-weight: normal"><?php echo sprintf(__("Upload your documents directly from iPaper plugin. (Your are limited to %s for each file. It's your server limit. When you upload a document from iPaper plugin, file is uploaded to your server, then transfered to Scribd server into your account. If you want to upload bigger documents you have to do it using <a href=\"http://www.scribd.com/upload\">Scribd interface</a>)", "ipaper"), iPaper::getMaxFileSize()) ?></span></li>
				<li><span style="font-weight: normal"><?php _e('Synchronize your Scribd account with iPaper plugin', 'ipaper') ?></span></li>
				<li><span style="font-weight: normal"><?php _e('Insert your documents in your posts in one click', 'ipaper') ?></span></li>
			</ol>
			<h4><?php _e('Plugins features', 'ipaper') ?></h4>
			<p><?php _e('- Upload files from your harddrive or from an url', 'ipaper') ?></p>
			<p><?php _e('- Edit your documents properties: title, description, tags, access, license, ...', 'ipaper') ?></p>
			<p><?php _e('- Update each document file', 'ipaper') ?></p>
			<p><?php _e('- Preview your documents', 'ipaper') ?></p>
			<p><?php _e('- Delete documents', 'ipaper') ?></p>
			<p><?php _e('- Define global height and width used for all documents, but if you want particular height and width for a document, you can override when inserting in a post', 'ipaper') ?></p>
			<p><?php _e('- Display public documents by giving ID and key', 'ipaper') ?></p>
			<p><strong><?php _e('If you want to support iPaper, you can donate, for this version or for future updates, thank you!', 'ipaper') ?></strong></p>
			<?php iPaper::getDonateButton(); ?>
			</div>
		</div>
		<?php
	}

	function displayMiniHelp()
	{
		?>
		<a name="help"></a>
		<div class="stuffbox">
			<h3><?php _e('Help', 'ipaper') ?></h3>
			<div class="inside">
			<h4><?php _e('Use the plugin step by step', 'ipaper') ?></h4>
			<ol style="font-weight: bold;">
				<li><span style="font-weight: normal"><?php echo sprintf(__("Upload your documents directly from iPaper plugin. (Your are limited to %s for each file. It's your server limit. When you upload a document from iPaper plugin, file is uploaded to your server, then transfered to Scribd server into your account. If you want to upload bigger documents you have to do it using <a href=\"http://www.scribd.com/upload\">Scribd interface</a>)", "ipaper"), iPaper::getMaxFileSize()) ?></span></li>
				<li><span style="font-weight: normal"><?php _e('Synchronize your Scribd account with iPaper plugin', 'ipaper') ?></span></li>
				<li><span style="font-weight: normal"><?php _e('Insert your documents in your posts in one click', 'ipaper') ?></span></li>
			</ol>
			<h4><?php _e('Plugins features', 'ipaper') ?></h4>
			<p><?php _e('- Upload files from your harddrive or from an url', 'ipaper') ?></p>
			<p><?php _e('- Edit your documents properties: title, description, tags, access, license, ...', 'ipaper') ?></p>
			<p><?php _e('- Update each document file', 'ipaper') ?></p>
			<p><?php _e('- Preview your documents', 'ipaper') ?></p>
			<p><?php _e('- Delete documents', 'ipaper') ?></p>
			<p><?php _e('- Display public documents by giving ID and key', 'ipaper') ?></p>
			<p><strong><?php _e('If you want to support iPaper, you can donate, for this version or for future updates, thank you!', 'ipaper') ?></strong></p>
			<?php iPaper::getDonateButton(); ?>
			</div>
		</div>
		<?php
	}

	function tinyMceButton()
	{
		load_plugin_textdomain('ipaper', PLUGINDIR."/".iPaper::getFolder()."/lang");
		add_filter("mce_external_plugins", array('iPaper', 'addTinymcePlugin'), 5);
		add_filter('mce_buttons', array('iPaper', 'registerButton'), 5);
	}

	function registerButton($buttons)
	{
		array_push($buttons, "ipaper");
		return $buttons;
	}

	function addTinymcePlugin($plugin_array)
	{
		$plugin_array['ipaper'] = get_option('siteurl').'/wp-content/plugins/'.iPaper::getFolder().'/editor_plugin.js';
		return $plugin_array;
	}

	function changeMceVersion($v)
	{
		return ++$v;
	}

	function getDonateButton()
	{
		?>
		<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
		<input type="hidden" name="cmd" value="_s-xclick"/>
		<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----"/>
		<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt=""/>
		<img alt="" border="0" src="https://www.paypal.com/fr_FR/i/scr/pixel.gif" width="1" height="1"/>
		</form>
		<?php
	}
}
if(!version_compare(PHP_VERSION, '5.0.0', '<'))
{
	register_activation_hook(__FILE__, array('ipaper', 'setDefaults'));
	add_filter('the_content', array('iPaper', 'parse'));
	add_action('wp_head', array('iPaper', 'addHeader'));
	add_action('admin_head', array('iPaper', 'addAdminHeader'));
	add_action('admin_menu', array('iPaper', 'adminMenu'));
	add_filter('init', array('iPaper', 'tinyMceButton'));
	add_filter('tiny_mce_before_init', array('iPaper', 'changeMceVersion'));
	add_action('after_plugin_row_'.iPaper::getFolder().'/ipaper.php', array('iPaper', 'checkVersion'));
}
?>