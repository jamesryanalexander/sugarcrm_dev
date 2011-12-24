<?php
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');
/*********************************************************************************
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2011 SugarCRM Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 * 
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 * 
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 * 
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo. If the display of the logo is not reasonably feasible for
 * technical reasons, the Appropriate Legal Notices must display the words
 * "Powered by SugarCRM".
 ********************************************************************************/

/*********************************************************************************

 * Description:  TODO: To be written.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 ********************************************************************************/

    require_once('include/entryPoint.php');

    require_once('modules/Users/language/en_us.lang.php');
    global $app_strings;
    global $sugar_config;
    global $new_pwd;

  	$mod_strings=return_module_language('','Users');
  	$res=$GLOBALS['sugar_config']['passwordsetting'];
	$regexmail = "/^\w+(['\.\-\+]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,4})+\$/";

///////////////////////////////////////////////////
///////  Retrieve user
$username = '';
$useremail = '';
if(isset( $_POST['user_name'])){
        $username = $_POST['user_name'];
}else if(isset( $_POST['username'])){
        $username = $_POST['username'];
}

if(isset( $_POST['Users0emailAddress0'])){
        $useremail = $_POST['Users0emailAddress0'];
}else if(isset( $_POST['user_email'])){
        $useremail = $_POST['user_email'];
}

    $usr= new user();
    if(isset($username) && $username != '' && isset($useremail) && $useremail != '')
    {
        if ($username != '' && $useremail != ''){
            $usr_id=$usr->retrieve_user_id($username);
            $usr->retrieve($usr_id);
            if ($usr->email1 !=  $useremail){
                echo $mod_strings['ERR_PASSWORD_USERNAME_MISSMATCH'];
                return;
            }

    	    if ($usr->portal_only || $usr->is_group){
	            echo $mod_strings['LBL_PROVIDE_USERNAME_AND_EMAIL'];
	            return;
    	    }
    	}
    	else
    	{
    		echo  $mod_strings['LBL_PROVIDE_USERNAME_AND_EMAIL'];
    		return;
    	}
    }
    else{
        if (isset($_POST['userId']) && $_POST['userId'] != ''){
            $usr->retrieve($_POST['userId']);
        }
        else{
        	if(isset( $_POST['sugar_user_name']) && isset($_POST['sugar_user_name'] )){
				$usr_id=$usr->retrieve_user_id($_POST['sugar_user_name']);
	        	$usr->retrieve($usr_id);
			}
    		else{
    			echo  $mod_strings['ERR_USER_INFO_NOT_FOUND'];
            	return;
    		}
    	}
    }

///////
///////////////////////////////////////////////////

///////////////////////////////////////////////////
///////  Check email address

	if (!preg_match($regexmail, $usr->emailAddress->getPrimaryAddress($usr))){
		echo $mod_strings['ERR_EMAIL_INCORRECT'];
		return;
	}

///////
///////////////////////////////////////////////////


	// if i need to generate a password (not a link)
    if (!isset($_POST['link'])){
        $password = User::generatePassword();
    }

///////////////////////////////////////////////////
///////  Create URL

// if i need to generate a link
if (isset($_POST['link']) && $_POST['link'] == '1'){
	global $timedate;
	$guid=create_guid();
	$url=$GLOBALS['sugar_config']['site_url']."/index.php?entryPoint=Changenewpassword&guid=$guid";
	$time_now=TimeDate::getInstance()->nowDb();
	//$q2="UPDATE `users_password_link` SET `deleted` = '1' WHERE `username` = '".$username."'";
	//$usr->db->query($q2);
	$q = "INSERT INTO users_password_link (id, username, date_generated) VALUES('".$guid."','".$username."',' ".$time_now."' ) ";
	$usr->db->query($q);
}
///////
///////////////////////////////////////////////////

///////  Email creation
	global $current_user;
    if (isset($_POST['link']) && $_POST['link'] == '1')
    	$emailTemp_id = $res['lostpasswordtmpl'];
    else
    	$emailTemp_id = $res['generatepasswordtmpl'];

    $additionalData = array(
        'link' => isset($_POST['link']) && $_POST['link'] == '1',
        'password' => $password
    );
    if (isset($url))
    {
        $additionalData['url'] = $url;
    }
    $result = $usr->sendEmailForPassword($emailTemp_id, $additionalData);
    if ($result['status'] == false && $result['message'] != '')
    {
        echo $result['message'];
        $new_pwd = '4';
        return;
    }
    // Bug 36833 - Add replacing of special value $instance_url
    $htmlBody = str_replace('$config_site_url',$sugar_config['site_url'], $htmlBody);
    $body = str_replace('$config_site_url',$sugar_config['site_url'], $body);
    
    $htmlBody = str_replace('$contact_user_user_name', $usr->user_name, $htmlBody);
    $htmlBody = str_replace('$contact_user_pwd_last_changed', TimeDate::getInstance()->nowDb(), $htmlBody);
    $body = str_replace('$contact_user_user_name', $usr->user_name, $body);
    $body = str_replace('$contact_user_pwd_last_changed', TimeDate::getInstance()->nowDb(), $body);
    // Bug #36250 Replacement of all template variables.
    $macro_nv=array();
    $template_data =  $emailTemp->parse_email_template(
        array(
            'subject' => $emailTemp->subject,
            'body_html' => $htmlBody,
            'body' => $body
        ),
        $usr->module_dir,
        $usr,
        $macro_nv
    );
    $emailTemp->subject = $template_data['subject'];
    $emailTemp->body_html = $template_data['body_html'];
    $emailTemp->body = $template_data['body'];
    // Bug #36250 is ended
    require_once('include/SugarPHPMailer.php');

    if ($result['status'] == true)
    {
        echo '1';
    } else {
    	$new_pwd='4';
    	if ($current_user->is_admin){
    		$email_errors=$mod_strings['ERR_EMAIL_NOT_SENT_ADMIN'];
    		if ($mail->Mailer == 'smtp')
    			$email_errors.="\n-".$mod_strings['ERR_SMTP_URL_SMTP_PORT'];
    		if ($mail->SMTPAuth)
    		 	$email_errors.="\n-".$mod_strings['ERR_SMTP_USERNAME_SMTP_PASSWORD'];
    		$email_errors.="\n-".$mod_strings['ERR_RECIPIENT_EMAIL'];
    		$email_errors.="\n-".$mod_strings['ERR_SERVER_STATUS'];
    		echo $email_errors;
    	}
    	else
    		echo $mod_strings['LBL_EMAIL_NOT_SENT'];
    }
    return;

?>
