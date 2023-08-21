jQuery(document).ready(function() {
    check=0;
		
	function VerifyButton(param,req_data){
		    
        jQuery.ajax({
            url: ajax.ajax_url,
            type: "POST",
			dataType: 'json',
            data:{
                'action':param,
                'MID':req_data
            },
			success:function(response){
			switch(check){
					//Case for checking the response callingToFetchMessage
				case 0:
					if(response.success==true){
						check++;
						jQuery(".fetch-email-response").css({display:"block"}).html('Successfully Fetch!');
						jQuery(".sending-to-mailtrap-loading").css({display:"block"}).html('Sending to Mailtrap...');
						VerifyButton('callingToSendMessage',response.data);
						break;
					}
					else{
						check=0;
						jQuery(".fetch-email-response").css({display:"block"}).html('Error in Fetching!');
						break;
					}
					break;
					//Case for checking the response callingToSendMessage
				case 1:
					if(response.success==true){
						check++;
						jQuery(".sending-to-mailtrap").css({display:"block"}).html('Successfully Send!');
						jQuery(".markasread-loading").css({display:"block"}).html('Marking Email as read...');
						VerifyButton('callingToReadMessage',response.data);
						break;
					}
					else{
						check=0;
						jQuery(".sending-to-mailtrap").css({display:"block"}).html('Error in Sending!');
						break;
					}
					
					break;
					//Case for checking the response callingToReadMessage
				case 2:
					if(response.success==true){
						check++;
						jQuery(".markasread-response").css({display:"block"}).html('Successfully Mark!');
						break;
					}
					else{
						check=0;
						jQuery(".markasread-response").css({display:"block"}).html('Error in Marking!');
						break;
					}
					break;
			}
				
        	}
	
        });
	}
		
		jQuery( document ).on( 'click',"#post-email-verification", function(e){
			e.preventDefault();
       		e.stopPropagation();
			req_data=null;
			jQuery(".fetch-email-loading").css({display:"block"}).html('Fetching Email from Email Box...');
			VerifyButton('callingToFetchMessage',req_data);
		});
 
  })    