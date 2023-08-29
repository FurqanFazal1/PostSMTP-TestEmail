<?php
/*
    Plugin Name: Post SMTP Test Email
    Description: Automate the testing of test email send by Post SMTP 
    Version: 1.0
    Author: Furqan
    Author URI: https://www.linkedin.com/in/furqan-fazal-12887b22b
*/

class PostSMTPTestMail{
  
   // do_action( 'post_smtp_test_email_section' ); on line 209 in file postSendTestEmailController
    
	public $client_id; 
    public $client_secret;
    public $redirect_uri;
    public $client;
    public $url;
	public $message_details=[];
	public $gmail;
	
    public function __construct(){
		require_once WP_PLUGIN_DIR.'/post-smtp/Postman/Postman-Mail/PostmanMailEngine.php';
        include(dirname(__FILE__) . "/libs/vendor/autoload.php");
		add_action('admin_enqueue_scripts', array($this,'Load_Scripts') );
        add_action( "admin_menu", array($this,"testmailsetup") );
        add_action( "post_smtp_test_email_section", array($this,"OurHTML"));
        add_action( 'wp_ajax_callingToFetchMessage', array($this,'callingToFetchMessage'));
		add_action( 'wp_ajax_callingToSendMessage', array($this,'callingToSendMessage'));
		add_action( 'wp_ajax_callingToReadMessage', array($this,'callingToReadMessage'));
		
		if(!get_option('AccessToken')){
			add_action('admin_notices', array($this,'sample_admin_notice__success'));
		}
		
		
    	//initilize credentials
        $this->client_id = "720395507644-h37glbogpr5ir4ujai43b2n4ut14qh1e.apps.googleusercontent.com";
        $this->client_secret = "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";
       	$this->redirect_uri= admin_url().'admin.php?page=postman%2Femail_test';
        $this->client = new PostSMTP\Vendor\Google\Client();

        //authenticate
        $this->client->setClientId($this->client_id);
        $this->client->setClientSecret($this->client_secret);    
        $this->client->setRedirectUri($this->redirect_uri);
		$this->client->addScope("https://mail.google.com/");
        $this->client->addScope("email");
		$this->client->setAccessType('offline');
		$this->client->setApprovalPrompt('force');
        $this->client->addScope("profile");
		$this->url=$this->client->createAuthUrl();
		$this->gmail=new PostSMTP\Vendor\Google\Service\Gmail($this->client);
        $this->verifyToken();
	}
	
	
	public function verifyToken(){
		
        $accessToken=get_option('AccessToken');
        $refreshToken=get_option('RefreshToken');
		
		if($accessToken){
			
			//if token expired get it from refresh token
			$this->client->setAccessToken($accessToken);
			
			if($this->client->isAccessTokenExpired()){
				
            	$getbyRefresh=$this->client->fetchAccessTokenWithRefreshToken($refreshToken);
				$this->client->setAccessToken($getbyRefresh);
				update_option( 'AccessToken', $this->client->getAccessToken());
                       
            }
		}
        //else createAuthUrl to authenticate user    
		else{
        
			if(isset($_GET['code'])){
				$token = $this->client->fetchAccessTokenWithAuthCode($_GET['code']);
				$this->client->setAccessToken($token);
				
				if (array_key_exists('error', $token)) {
					throw new Exception(join(', ', $token));
        		}
                   
				else{
                       
					update_option( 'AccessToken', $token);
					update_option('RefreshToken', $this->client->getRefreshToken());
				}
			}

		}
	}
	
	//Fetching Unread Messages from Gmail
	public function fetchUnreadMessages(){
	
			$search = "category:primary label:unread";
			$params = array(
				'maxResults' => 10,
				'q' => $search
			);
			
			$list=$this->gmail->users_messages->listUsersMessages('me',$params);
			$messageList=$list->getMessages();
			$mId = 0;
			
		
			foreach($messageList as $mlist){
				$optParamsGet2['format'] = 'full';
    			$single_message = $this->gmail->users_messages->get('me', $mlist->id, $optParamsGet2);
				$header_details = $single_message->getPayload()->getHeaders();
				$snippet = $single_message->getSnippet();
				$mId= $mlist->id;
				foreach($header_details as $data){
					
					 if ($data->getName() == 'Subject') {
            				$message_subject = $data->getValue();
        			} elseif ($data->getName() == 'Date') {
            				$message_date = $data->getValue();
            				$message_date = date('M jS Y h:i A', strtotime($message_date));
        			} elseif ($data->getName() == 'From') {
            				$message_sender = $data->getValue();
            				$message_sender = str_replace('"', '', $message_sender);
       				}
				
				}
				
				$this->message_details = array(
        			'messageId' => $mId,
        			'messageSnippet' => $snippet,
        			'messageSubject' => $message_subject,
        			'messageDate' => $message_date,
        			'messageSender' => $message_sender,
					'headerdetails' =>	$header_details
    			);
				break;
				}
			return $mId;
	}
		
		//Sending message to Mailtrap
		public function Send($message_details,$mId,$gmail){
			$to=get_option('admin_email');
			$subject=$message_details['messageSubject'];
			$body=$message_details['messageSnippet'];
			$date=$message_details['messageDate'];
			$header=$message_details['headerdetails'];
			
			//Create an instance of Postmanoptions from postsmtp to set message details 
			$options = PostmanOptions::getInstance();
			$message = new PostmanMessage();
			$message->setFrom($to);
			$message->setReplyTo($to);
			$message->setSubject($subject);
			$message->setBody($body);
			$message->setDate($date);
			
			// create the body parts (if they are both missing)
			if ( $message->isBodyPartsEmpty() ) {
				$message->createBodyParts();
			}
			$message->addHeaders( $to );
			$message->addTo( $to );
			$message->addCc( $to );
			$message->addBcc( $to);

			// get the transport and create the transportConfig and engine from post smtp
			$transport = PostmanTransportRegistry::getInstance()->getTransport('smtp');
			$engine = $transport->createMailEngine();
			
			$count = 0;
			try{
				$engine->send($message);
				return true;
			}catch(Exception $e){
				var_dump("Exception",$e->getMessage());
			}
			
		}
	
		//Set email to Mark As Read
		public function SetToMarkAsRead($mId,$gmail){
		
		
			$mods= new PostSMTP\Vendor\Google\Service\Gmail\ModifyMessageRequest($this->client);
			$mods->setRemoveLabelIds(array("UNREAD"));
			$res=$gmail->users_messages->modify('me', $mId, $mods);
			return $res;
		}
		
	//Load scripts
	public function Load_Scripts(){

        wp_enqueue_script( 'java-script', plugins_url( '/gmail-api-plugin.js', __FILE__ ),array('jquery'),'1.0.0',true);
        wp_localize_script( 'java-script', 'ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
			'val'=>100
        )); 
		
    	}
	
	//Calling Function fetchUnreadMessages and send response to ajax
	public function callingToFetchMessage(){
		if(!$this->client->isAccessTokenExpired()){
			
 				$this->message_id=$this->fetchUnreadMessages();
					if($this->message_id){
						wp_send_json_success(array(
						'mid'=>$this->message_id,
						'mdetails'=>$this->message_details
						)  ,200 );
					}
					else{
						wp_send_json_error( $this->message_id, 200 );
					}
		}
		else{
			wp_send_json_error( "No Authenticate", 200 );
			}
		}
	
	//Calling Function Send and send response to ajax
	public function callingToSendMessage(){
 			$whatever =  $_POST['MID'];
			$this->resp=$this->Send($whatever['mdetails'],$whatever['mid'],$this->gmail);
			
			if($this->resp){
				wp_send_json_success( array(
					'Res'=>$this->resp,
					'mid'=>$whatever['mid']
				), 200 );
			}
			else{
				wp_send_json_error( $this->resp, 200 );
			}
		
	}
	
	//Calling Function SetToMarkAsRead and send response to ajax
	public function callingToReadMessage(){
			
			$whatever =  $_POST['MID'];
			$mark=$this->SetToMarkAsRead($whatever['mid'],$this->gmail);
			if($mark){
				wp_send_json_success( "Okay", 200 );
			}
			else{
				wp_send_json_error( "No Okay", 200 );
			}
		}
		

	//Setup 
    public function testmailsetup(){
        add_options_page( "Test Email Sending", "Test Email", 'manage_options', 'test-email-setup', array($this,'handleOutput'));
    }
	
	//Notice for Authentication of user
	public function sample_admin_notice__success(){
   
	?>
		<div class="notice notice-warning is-dismissible" style="padding:10px">
		Click Here For Authentication: <a href=<?php echo $this->url; ?>>Authenticate</a> 
		</div>
	<?php

	}
   
    public function OurHTML(){
        
        ?>
        
        <a href="#" id="post-email-verification" class="button button-primary" >Verify</a>
	 	<div class="fetch-email-loading" style="display:none"></div>
		<div class="fetch-email-response" style="display:none"></div>
		<div class="sending-to-mailtrap-loading" style="display:none"></div>
		<div class="sending-to-mailtrap" style="display:none"></div>
		<div class="markasread-loading" style="display:none"></div>
		<div class="markasread-response" style="display:none"></div>
        <?php
    }

 

}

add_action( 'admin_init', function() {
	
	new PostSMTPTestMail();
	
}, 50 );

//Deleting options from the table on deactivation
function uninstall_deletion(){
		delete_option('AccessToken');
        delete_option('RefreshToken');
}
register_deactivation_hook(
			__FILE__,
'uninstall_deletion');

?>