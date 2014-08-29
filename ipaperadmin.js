jQuery(document).ready( function() {
	jQuery('tr.ipaper-greyline').mouseover(function() {
		jQuery(this).css("background-color", "#F0F0F0");
	});
	jQuery('tr.ipaper-greyline').mouseout(function() {
		jQuery(this).css("background-color", "white");
	});
	jQuery('table#ipaper-listtable tr').each(function(){
		jQuery(this).find('td:not(:first)').css('border-left', '1px solid #D2D2D2').css('padding-left', '5px');
	});
	jQuery('a.ipaper-delete').click(function() {
		if(confirm(this.title+"\n("+jQuery(this).parents('tr').find('td:first').html()+")"))
		{
			return true;
		}
		return false;
	});
});