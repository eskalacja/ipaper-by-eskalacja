<?php
$wpconfig = realpath("../../../wp-config.php");
require_once($wpconfig);
header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>iPaper</title>
	<script language="javascript" type="text/javascript" src="<?php echo get_option('siteurl') ?>/wp-includes/js/tinymce/tiny_mce_popup.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo get_option('siteurl') ?>/wp-includes/js/tinymce/utils/mctabs.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo get_option('siteurl') ?>/wp-includes/js/tinymce/utils/form_utils.js"></script>
	<script language="javascript" type="text/javascript">
	function insertIpaper()
	{
		var tagtext;
		var media = document.getElementById('ipaper_panel');
		var media2 = document.getElementById('ipaper_panel2');

		// who is active ?
		if (media.className.indexOf('current') != -1) {
			var data = document.getElementById('ipapertag').value;
			if (data != '' )
			{
				width = document.getElementById('ipaperwidth').value;
				height = document.getElementById('ipaperheight').value;
				mode = document.getElementById('ipapermode').value;
				page = document.getElementById('ipaperpage').value;
				if(width != '') data += ' width='+width;
				if(height != '') data += ' height='+height;
				if(mode != 'list') data += ' mode='+mode;
				if(page != '1') data += ' page='+page;
				tagtext = "[ipaper " + data + "]";
			}
			else
			{
				tinyMCEPopup.close();
			}
		}
		else if(media2.className.indexOf('current') != -1) {
			var id = document.getElementById('ipaperid').value;
			var key = document.getElementById('ipaperkey').value;
			if (id != '' && key != '')
			{
				data = 'id='+id+' key='+key;
				width = document.getElementById('ipaperpwidth').value;
				height = document.getElementById('ipaperpheight').value;
				mode = document.getElementById('ipaperpmode').value;
				page = document.getElementById('ipaperppage').value;
				if(width != '') data += ' width='+width;
				if(height != '') data += ' height='+height;
				if(mode != 'list') data += ' mode='+mode;
				if(page != '1') data += ' page='+page;
				tagtext = "[ipaper " + data + "]";
			}
			else
			{
				tinyMCEPopup.close();
			}
		}
		if(window.tinyMCE)
		{
			window.tinyMCE.execInstanceCommand('content', 'mceInsertContent', false, tagtext);
			//Peforms a clean up of the current editor HTML.
			//tinyMCEPopup.editor.execCommand('mceCleanup');
			//Repaints the editor. Sometimes the browser has graphic glitches.
			tinyMCEPopup.editor.execCommand('mceRepaint');
			tinyMCEPopup.close();
		}
		return;
	}
	</script>
	<base target="_self" />
</head>
<body id="link" onload="document.body.style.display='';document.getElementById('ipapertag').focus();" style="display: none">
	<form name="ipaper" action="#">
	<div class="tabs">
		<ul>
			<li id="ipaper_tab" class="current"><span><a href="javascript:mcTabs.displayTab('ipaper_tab','ipaper_panel');" onmousedown="return false;"><?php _e('Insert my iPaper document', 'ipaper') ?></a></span></li>
			<li id="ipaper_tab2"><span><a href="javascript:mcTabs.displayTab('ipaper_tab2','ipaper_panel2');" onmousedown="return false;"><?php _e('Insert public iPaper document', 'ipaper') ?></a></span></li>
		</ul>
	</div>

	<div class="panel_wrapper">
		<div id="ipaper_panel" class="panel current">
			<table border="0" cellpadding="4" cellspacing="0">
      	<tr>
        	<td nowrap="nowrap"><label for="ipapertag"><?php _e('Select a document:', 'ipaper') ?> </label></td>
       	</tr>
       	<tr>
          <td>
          	<select id="ipapertag" name="ipapertag" style="width: 400px">
							<?php
								$docs = iPaper::getOption('docs');
								if(count($docs))
								{
									foreach($docs as $doc)
									{
										echo '<option value="id='.$doc['doc_id'].'">'.$doc['title'].'</option>';
									}
								}
							?>
          	</select>
          </td>
        </tr>
     	</table>
     	<table border="0" cellpadding="3" cellspacing="0">
        <tr>
        	<td colspan="4"><?php _e('Width and height are taken from plugin settings, but you can override it for each document.', 'ipaper') ?></td>
        </tr>
        <tr>
        	<td nowrap="nowrap" align="right"><label for="ipaperwidth"><?php _e('Width:', 'ipaper') ?> </label></td>
        	<td><input type="text" name="ipaperwidth" id="ipaperwidth" size="5"/><?php _e('px', 'ipaper') ?></td>
        	<td nowrap="nowrap" align="right"><label for="ipaperheight"><?php _e('Height:', 'ipaper') ?> </label></td>
        	<td><input type="text" name="ipaperheight" id="ipaperheight" size="5"/><?php _e('px', 'ipaper') ?></td>
        </tr>
        <tr>
        	<td nowrap="nowrap" align="right"><label for="ipaperpage"><?php _e('Page:', 'ipaper') ?> </label></td>
        	<td><input type="text" name="ipaperpage" id="ipaperpage" size="5" value="1"/></td>
        	<td nowrap="nowrap" align="right"><label for="ipapermode"><?php _e('Mode:', 'ipaper') ?> </label></td>
        	<td>
        		<select name="ipapermode" id="ipapermode">
							<option value="list"><?php _e('List', 'ipaper') ?></option>
							<option value="book"><?php _e('Book', 'ipaper') ?></option>
							<option value="slide"><?php _e('Slideshow', 'ipaper') ?></option>
							<option value="tile"><?php _e('Tile', 'ipaper') ?></option>
						</select>
					</td>
        </tr>
      </table>
		</div>
		<div id="ipaper_panel2" class="panel">
     	<table border="0" cellpadding="3" cellspacing="0">
     		<tr>
     			<td nowrap="nowrap" align="right"><label for="ipaperid"><?php _e('Document ID:', 'ipaper') ?> </label></td>
     			<td colspan="3"><input type="text" name="ipaperid" id="ipaperid" size="45"/></td>
     		</tr>
     		<tr>
     			<td nowrap="nowrap" align="right"><label for="ipaperkey"><?php _e('Document Key:', 'ipaper') ?> </label></td>
     			<td colspan="3"><input type="text" name="ipaperkey" id="ipaperkey" size="45"/></td>
     		</tr>
        <tr>
        	<td colspan="4"><?php _e('Width and height are taken from plugin settings, but you can override it for each document.', 'ipaper') ?></td>
        </tr>
        <tr>
        	<td nowrap="nowrap" align="right"><label for="ipaperpwidth"><?php _e('Width:', 'ipaper') ?> </label></td>
        	<td><input type="text" name="ipaperpwidth" id="ipaperpwidth" size="5"/><?php _e('px', 'ipaper') ?></td>
        	<td nowrap="nowrap" align="right"><label for="ipaperpheight"><?php _e('Height:', 'ipaper') ?> </label></td>
        	<td><input type="text" name="ipaperpheight" id="ipaperpheight" size="5"/><?php _e('px', 'ipaper') ?></td>
        </tr>
        <tr>
        	<td nowrap="nowrap" align="right"><label for="ipaperppage"><?php _e('Page:', 'ipaper') ?> </label></td>
        	<td><input type="text" name="ipaperppage" id="ipaperppage" size="5" value="1"/></td>
        	<td nowrap="nowrap" align="right"><label for="ipaperpmode"><?php _e('Mode:', 'ipaper') ?> </label></td>
        	<td>
        		<select name="ipaperpmode" id="ipaperpmode">
							<option value="list"><?php _e('List', 'ipaper') ?></option>
							<option value="book"><?php _e('Book', 'ipaper') ?></option>
							<option value="slide"><?php _e('Slideshow', 'ipaper') ?></option>
							<option value="tile"><?php _e('Tile', 'ipaper') ?></option>
						</select>
					</td>
        </tr>
      </table>
		</div>
	</div>


	<div class="mceActionPanel">
		<div style="float: left">
			<input type="button" id="cancel" name="cancel" value="<?php _e('Cancel', 'ipaper') ?>" onclick="tinyMCEPopup.close();" />
		</div>

		<div style="float: right">
			<input type="submit" id="insert" name="insert" value="<?php _e('Insert', 'ipaper') ?>" onclick="insertIpaper();" />
		</div>
	</div>
</form>
</body>
</html>