(function() {
	// Load plugin specific language pack
	//tinymce.PluginManager.requireLangPack('ipaper');

	tinymce.create('tinymce.plugins.ipaper', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function(ed, url) {
			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');

			ed.addCommand('mceipaper', function() {
				ed.windowManager.open({
					file : url + '/window.php',
					width : 450 + ed.getLang('ipaper.delta_width', 0),
					height : 220 + ed.getLang('ipaper.delta_height', 0),
					inline : 1
				}, {
					plugin_url : url // Plugin absolute URL
				});
			});

			// Register example button
			ed.addButton('ipaper', {
				title : 'iPaper by Esklacja.com',
				cmd : 'mceipaper',
				image : url + '/ipaper.png',
                onPostRender: function() {
                    var ctrl = this;
                    ed.on('NodeChange', function(e) {
                        ctrl.active(e.element.nodeName == 'img');
                    });
                }
			});

		},

		/**
		 * Returns information about the plugin as a name/value array.
		 * The current keys are longname, author, authorurl, infourl and version.
		 *
		 * @return {Object} Name/value array containing information about the plugin.
		 */
		getInfo : function() {
			return {
					longname  : 'iPaper',
					author 	  : 'Telesphore',
					authorurl : 'http://www.telesphore.org',
					infourl   : 'http://www.telesphore.org',
					version   : "1.0"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('ipaper', tinymce.plugins.ipaper);
})();