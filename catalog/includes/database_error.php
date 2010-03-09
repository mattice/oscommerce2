<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
  

  IMPORTANT NOTES ABOUT THIS FILE:

  This file is called whenever database problems occur, both for connection and sql errors.
  It can report these errors silently by email while displaying a customer friendly page.
  It supports debug override to show full error information for trusted ip addresses.
  The exact behaviour is customizable through the settings in 
  
      Admin -> Configuration Settings -> Database Error Mode
  
  When editing the functionality of this file be aware the database dependent data is most likely unavailable.
  Any database error will stop further PHP processing, so in the best scenario only partial data is available.
  Defines, classes, functions or even variables created by osCommerce might not in scope on this specific page.
  
  Another important thing to realize is the HTML output of this file might end up anywhere on a page that calls
  the database and encounters an error. This means it is likely to break a pagelayout if you make it a 'full blown' 
  html page. Keep it small. Or build an onLoad CSS/JavaScript modal-type 'popup' that is cross-browser compatible.
   
  Also note this file makes use of the superglobal $_SERVER instead of $HTTP_SERVER_VARS
   
*/

// local development support
 if (file_exists(dirname(__FILE__) . '/local/configure.php')) {
  include_once(dirname(__FILE__) . '/local/configure.php'); 
 }

// check how far down application_top.php we have come
  $included = preg_replace("/\/.*\//", "", get_included_files());
  
  if (!in_array('header.php', $included)) {
    $html_output = true;

 // certain functionality is unavailable so we provide it
   function tep_validate_email($email) {
    $email = trim($email);

    if ( strlen($email) > 255 ) {
      $valid_address = false;
    } elseif ( function_exists('filter_var') && defined('FILTER_VALIDATE_EMAIL') ) {
     $valid_address = (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    } else {
      if ( substr_count( $email, '@' ) > 1 ) {
        $valid_address = false;
      }

      if ( preg_match("/[a-z0-9!#$%&'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?/i", $email) ) {
        $valid_address = true;
      } else {
        $valid_address = false;
      }
    }

    if ($valid_address && ENTRY_EMAIL_ADDRESS_CHECK == 'true') {
      $domain = explode('@', $email);

      if ( !checkdnsrr($domain[1], "MX") && !checkdnsrr($domain[1], "A") ) {
        $valid_address = false;
      }
    }

    return $valid_address;
  } 	
 	
  function tep_validate_ip_address($ip_address) {
    if (function_exists('filter_var') && defined('FILTER_VALIDATE_IP')) {
      return filter_var($ip_address, FILTER_VALIDATE_IP, array('flags' => FILTER_FLAG_IPV4));
    }

    if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $ip_address)) {
      $parts = explode('.', $ip_address);

      foreach ($parts as $ip_parts) {
        if ( (intval($ip_parts) > 255) || (intval($ip_parts) < 0) ) {
          return false; // number is not within 0-255
        }
      }

      return true;
    }

    return false;
  }
 
   if (!function_exists('tep_get_ip_address')) {
   	
    function tep_get_ip_address() {
  
      $ip_address = null;
      $ip_addresses = array();
  
      if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach ( array_reverse(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) as $x_ip ) {
          $x_ip = trim($x_ip);
  
          if (tep_validate_ip_address($x_ip)) {
            $ip_addresses[] = $x_ip;
          }
        }
      }
  
      if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip_addresses[] = $_SERVER['HTTP_CLIENT_IP'];
      }
  
      if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && !empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
        $ip_addresses[] = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
      }
  
      if (isset($_SERVER['HTTP_PROXY_USER']) && !empty($_SERVER['HTTP_PROXY_USER'])) {
        $ip_addresses[] = $_SERVER['HTTP_PROXY_USER'];
      }
  
      $ip_addresses[] = $_SERVER['REMOTE_ADDR'];
  
      foreach ( $ip_addresses as $ip ) {
        if (!empty($ip) && tep_validate_ip_address($ip)) {
          $ip_address = $ip;
          break;
        }
      }
  
      return $ip_address;
    }
   } 
  }  
 
 // wrapper for debug_backtrace()
 function tep_debug_backtrace($prefix = false, $postfix = false, $show_args = true) {    
  $output = '';
      if ($prefix) $output = $prefix; 
        $output .= "\n" . ' -------------------' . "\n";
        $output .= '  Debug Backtrace:'. "\n";
        $output .= ' -------------------' . "\n";
        $i = -1;
        foreach(debug_backtrace() as $trace) {
         $i++;
          if ($i == 0) { continue; } // do not include myself
           $output .= '  ' . '[' . $i . '] ';
            if(isset($trace['file'])) {
             $output .= basename($trace['file']) . ':' . $trace['line']; 
            }else{
             $output .= '[PHP callback]';
            }
            
            $output .= ' -- ';

            if(isset($trace['class'])) $output .= $trace['class'] . $trace['type'];

            $output .= $trace['function'];

            if(isset($trace['args']) && sizeof($trace['args']) > 0) {
            	$output .= '(' . ( ($show_args) ? implode(', ', $trace['args']) : '...') . ')';
            }else{
            	$output .= '()';
            }

            $output .= "\n";
        }
     if ($postfix) $output .= $postfix;
   return $output;
 }
  
// load Database Error Mode configuration from cache
 $cache_file = 'includes/work/cfg_parameters.cache';
  
  if (file_exists($cache_file)) {
    $dbem = unserialize(join('', file($cache_file)));
   }else{
    $dbem = array('DB_ERROR_MODE' => 'friendly');
  }
  
  switch ($dbem['DB_ERROR_MODE']) {
  	case 'friendly':
  		$mail_report = false;
  		$show_debug = false;
  	break;
  	
  	case 'friendly_with_silent_reporting':
  		$mail_report = true;
  		$show_debug = false;

  	  // parse the email recipients
  		$recipients = array();
  		$raw_recipients = explode('|', $dbem['DB_ERROR_EMAIL_ADDRESS']);

  		if (!empty($raw_recipients[0])) {
  		 foreach ($raw_recipients as $email) {
  		   $email = trim($email);
  		     if ( tep_validate_email($email) ) { 
  		 	  $recipients[] = $email;
  		     }
  		 }  		 
  		  if (sizeof($recipients) < 1) {
  		   	$mail_report = false; // admin input is botched, no valid address(es)
  		  }
  		   		 	 
  		}else{
  	      $mail_report = false;
  		} 

  	break;
  	
  	case 'debug':
  		$mail_report = false;
  		$show_debug = true;
  	break;
  	default:
  	    $mail_report = false;
  	    $show_debug = false;
  }
  
  if (!empty($dbem['DB_ERROR_DEFAULT_DEBUG_IPS'])) {
    
  // parse the ip addresses that receive debug mode by default
  	 $default_debug = array();
  	 $raw_debug = explode('|', $dbem['DB_ERROR_DEFAULT_DEBUG_IPS']);

  	if (!empty($raw_debug[0])) {
  	   foreach ($raw_debug as $ip) {
  		 $default_debug[] = trim($ip);
  		}  		 
  		 if (sizeof($default_debug) < 1) {
  		    $auto_debug = false;
  		 }else{
  		 	$remote_ip = tep_get_ip_address();
  		 	if (in_array($remote_ip, $default_debug)) {
  		 	  $auto_debug = true;
  		 	  $show_debug = true;
  		 	}
  		 }
  	   }
  }
   
 	
    $report_data = ' Time:' . "\t" . date("d.m.Y H:i:s") . "\n\n" .
                   ' MySQL Error:' . "\t" . $error . "\n" .
  	               ' MySQL Error Nr:' . "\t" . $errno . "\n" .
  	               ' MySQL Query:' . "\t" . $query . "\n\n" .
  	               ' Remote IP:' . "\t" . $remote_ip . "\n" .
  	               ' User-Agent:' . "\t" . $_SERVER['HTTP_USER_AGENT'] . "\n\n" .
  	               ' Domain:' . "\t" . $_SERVER['HTTP_HOST'] . "\n" .
  	               ' Server IP:' . "\t" . $_SERVER['SERVER_ADDR'] . "\n" .
  	               ' Server Port:' . "\t" . $_SERVER['SERVER_PORT'] . "\n" .
  	               ' URL:' . "\t" . $_SERVER['REQUEST_URI'] . "\n" .
  	               ' File:' . "\t" . $_SERVER['SCRIPT_NAME'] . "\n" .
                   ' Referer:' . "\t" . $_SERVER['HTTP_REFERER'] . "\n" .
                   tep_debug_backtrace();
    
   // set the reporting limit from cache or force default
  	$reporting_limit = !empty($dbem['DB_ERROR_EMAIL_REPORTING_LIMIT']) ? $dbem['DB_ERROR_EMAIL_REPORTING_LIMIT'] : '20';
  	
   // report database errors on file level (otherwise the hash will be different each time)
  	$error_cache_file = 'includes/work/err_' . md5($_SERVER['SCRIPT_NAME']) . '.cache';
  	 
  	   if (file_exists($error_cache_file)) {
           $timediff = (time() - filemtime($error_cache_file));
            if ($timediff > $reporting_limit) {
            	
            // reporting limit has passed for this specific error, rewrite and mark mailout 
              if ($f = fopen($error_cache_file, 'w+')) {
           	     fwrite ($f, $report_data, strlen($report_data));
                 fclose($f);
              }

              $mail_out = true;
            }
  	    }else{	    	

  	   // initial write of the error  	 
         if ($f = fopen($error_cache_file, 'w+')) {
           fwrite ($f, $report_data, strlen($report_data));
           fclose($f);
         }
         
         $mail_out = true;
       }
       
         // process mail
           if ( ($mail_out === true) && ($dbem['DB_ERROR_MODE'] != 'debug') ) {
            foreach ($recipients as $email_address) {
            	mail($email_address, '[DB ERROR] ' . $_SERVER['SCRIPT_NAME'], $report_data);
            }
           }
      
  if ($html_output == true) {
    
  // set the header response
   @header('HTTP/1.1 503 Service Unavailable');
?>        
<html>
 <head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>[503] Database error</title>
  <base href="<?php echo HTTP_SERVER . DIR_WS_HTTP_CATALOG; ?>">
  <link rel="stylesheet" type="text/css" href="stylesheet.css">
<?php 
  }
?>
 <style type="text/css" media="screen">
  .dem {
    border: 1px solid;
    padding: 5px 10px 5px 50px;
    width: 30%;
    margin-left: 20%;
    margin-right:25%;
    margin-top: 10%;
    border-color: #ff0000;
    border-width: 3px;
    background-color: #fff;
  }
  #demcontent pre,
  #demcontent div.code-box-n,
  #demcontent div.code-box {
    font-family: 'Lucida Console', 'Bitstream Vera Sans Mono', 'Courier New', Monaco, Courier, monospace;
    white-space: pre;
    width: 45em;
    margin: 1em 0;
    border: 1px dashed #aaa8a8;
    padding: 0.5em 0 0.3em 0.5em;
    font-size: 80%;
    color: #999;
    overflow: auto;
  }

  #demcontent div.code-box-n {
    width: 33em;
    padding-left: 0.3em;
    border: 1px solid;
    border-color: #666 #999;
    background-color: #fff;
  }

  #demcontent div.code-box-n a { font-weight: normal; }
  #demcontent div.code-box-n a:focus,
  #demcontent div.code-box-n a:hover {
    border-bottom: 1px solid #c00;
  }  
 </style>
 
<?php  if ($html_output == true) echo '</head>' . "\n" . '<body>'; ?>

<div id="demcontent">
 <div class="dem">
    <p class="demmain">
    We experienced a database error.<br />
    Please try reloading the page or<br />
    retry in a few minutes.<br /><br />
    We apologize for the inconvenience.
    </p>
 </div>
 
<?php 
      if ($auto_debug) echo '<br /><span class="smallText">DEM Auto Debug</span><br />'; 
      if ($show_debug === true) echo '<div class="code-box">' . $report_data . '</div>'; 
?> 
</div>

<?php  if ($html_output == true) echo '</body>'; ?>
