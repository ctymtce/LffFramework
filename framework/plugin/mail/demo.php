<?php
/**
 * author: cty@20120607
 * @package phpmailer
 * @version Id
*/


/*
*@paraArr = array(
* user => '',
* pswd => '',
* name => '',
* subject => '',
* body => '',
* to => '' require
*  )
*/
function sendMail($to, $SMTP=true, $paraArr=array())
{
    require dirname(__FILE__).'/class.phpmailer.php';
    extract($paraArr);
    //发送方
    $user = isset($user)?$user:'mail@mail.com';
    $pswd = isset($pswd)?$pswd:'ur password';
    $name = isset($name)?$name:$user;
    //接收方
    $subject = isset($subject)?$subject:'subject';
    $body    = isset($body)?$body:'';
    $to      = isset($to)?$to:$user;
    
    $mail = new PHPMailer(true); //New instance, with exceptions enabled
    
    $body             = preg_replace('/\\\\/','', $body); //Strip backslashes
    if($SMTP) {
        
        
        $mail->CharSet ="UTF-8";
        $mail->IsSMTP();                           // tell the class to use SMTP
        $mail->SMTPAuth   = true;                  // enable SMTP authentication
        $mail->Port       = 25;                    // set the SMTP server port
        $mail->Host       = "smtp.mail.com";        // SMTP server
        $mail->Username   = $user;                 // SMTP server username
        $mail->Password   = $pswd;                 // SMTP server password
        
        $mail->From       = $user;
        $mail->FromName   = $name;
        // $to = "mail@gmail.com";
        $mail->AddAddress($to);
        $mail->Subject  = $subject;
        // $mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
        $mail->WordWrap   = 80; // set word wrap
        $mail->MsgHTML($body);
        $mail->IsHTML(true); // send as HTML
    }else {
        $mail->IsSendmail(); // telling the class to use SendMail transport

        $mail->AddReplyTo("name@yourdomain.com","First");
        $mail->SetFrom('mail@mail.com', 'From');
        $mail->AddReplyTo("mail@mail.com", "Last");
        $address = $to;
        $mail->AddAddress($address, "John");
        $mail->Subject    = $subject;
        // $mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test

        $mail->MsgHTML($body);

        // $mail->AddAttachment("images/phpmailer.gif");      // attachment
        // $mail->AddAttachment("images/phpmailer_mini.gif"); // attachment
        $mail->IsHTML(true); // send as HTML
        
    
    
    }
    if(!$mail->Send()) {
      echo "Mailer Error: " . $mail->ErrorInfo;
    } else {
      echo "Message sent!";
    }
    // return $mail->Send();
}

sendMail('frmemail@sina.com', true, array('body'=>'this is a test'));
// sendMail('mail@mail.com', true, array('body'=>'this is a test'));
?>

