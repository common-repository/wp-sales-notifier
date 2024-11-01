/* Custom Share Buttons With Floting Sidebar admin js*/
jQuery(document).ready(function(){
	    jQuery(".wsn-tab").hide();
		jQuery("#div-wsn-general").show();
	    jQuery(".wsn-tab-links").click(function(){
		var divid=jQuery(this).attr("id");
		jQuery(".wsn-tab-links").removeClass("active");
		jQuery(".wsn-tab").hide();
		jQuery("#"+divid).addClass("active");
		jQuery("#div-"+divid).fadeIn();
		});
});
