<?php

/**
 * dropbox_attachments.php 
 * 
 * Attach files from Dropbox
 *
 * @version 1.0
 * @author Hassansin <https://github.com/hassansin>
 * @license GNU GPLv3+
 * 
 * 
 */

class dropbox_attachments extends rcube_plugin
{
  
  public $task = 'mail';  

  function init()
  {   
    //rcube::write_log('dropbox_attachments', $_SESSION);    
  	
    $rcmail = rcube::get_instance();
  	
    $this->register_action('plugin.dropbox_attachments', array($this, 'save_data'));    

    if ($rcmail->task == 'mail' && $rcmail->action == 'compose') {
      $this->load_config();

      $rcmail->output->set_env('dropbox_appKey', $rcmail->config->get('dropbox_appKey', ''));
      $rcmail->output->set_env('dropbox_extensions', $rcmail->config->get('dropbox_extensions',[]));
      $rcmail->output->set_env('dropbox_multiselect', $rcmail->config->get('dropbox_multiselect',true));

      $this->include_script('https://www.dropbox.com/static/api/2/dropins.js');
      $this->include_script('attachments.min.js');      
    }   
    
  }

  //Ref: roundcube/program/steps/mail/attachments.inc
  function save_data(){
    $COMPOSE_ID = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GPC);
    $COMPOSE    = null;

    if ($COMPOSE_ID && $_SESSION['compose_data_' . $COMPOSE_ID]) {
        $SESSION_KEY = 'compose_data_' . $COMPOSE_ID;
        $COMPOSE =& $_SESSION[$SESSION_KEY];
    }

    if (!$COMPOSE) {
        die("Invalid session var!");
    }


    $uploadid = rcube_utils::get_input_value('_uploadid', rcube_utils::INPUT_POST);    
    $files = rcube_utils::get_input_value('files', rcube_utils::INPUT_POST);

    $RCMAIL  = rcmail::get_instance();
    $RCMAIL->output->reset(); 
    //rcube::write_log('dropbox_attachments', $files);

    if (is_array($files)) {
      $multiple = count($files) > 1;

      foreach ($files as $i => $file) {
        /*File checks*/
        $err = false;
        if($file['is_dir'] == 'true'){
          $err = "UPLOAD_ERR_DIRECTORY";
        }

        // Fetch file
        $filepath = $this->download_fopen($file['link']);
        //rcube::write_log('dropbox_attachments', $filepath);
        if(!$filepath)
          $err = "UPLOAD_ERR_FETCH";

        if (!$err) {
          $attachment = $this->move_file(array(
            'path' => $filepath,
            'size' => $file['bytes'],
            'name' => $file['name'],
            'mimetype' => rcube_mime::file_content_type($filepath, $file['name']),
            'group' => $COMPOSE_ID,
          ));
        }
        //rcube::write_log('dropbox_attachments', $attachment);

        if (!$err && $attachment['status'] && !$attachment['abort']) {
          $id = $attachment['id'];

          // store new attachment in session
          unset($attachment['status'], $attachment['abort']);
          $RCMAIL->session->append($SESSION_KEY.'.attachments', $id, $attachment);

          if (($icon = $COMPOSE['deleteicon']) && is_file($icon)) {
            $button = html::img(array(
              'src' => $icon,
              'alt' => $RCMAIL->gettext('delete')
            ));
          }
          else if ($COMPOSE['textbuttons']) {
            $button = rcube::Q($RCMAIL->gettext('delete'));
          }
          else {
            $button = '';
          }

          $content = html::a(array(
            'href'    => "#delete",
            'onclick' => sprintf("return %s.command('remove-attachment','rcmfile%s', this)", rcmail_output::JS_OBJECT_NAME, $id),
            'title'   => $RCMAIL->gettext('delete'),
            'class'   => 'delete',
          ), $button);

          $content .= rcube::Q($attachment['name']);

          $RCMAIL->output->command('add2attachment_list', "rcmfile$id", array(
            'html'      => $content,
            'name'      => $attachment['name'],
            'mimetype'  => $attachment['mimetype'],
            'classname' => rcube_utils::file2class($attachment['mimetype'], $attachment['name']),
            'complete'  => true), $uploadid);
        }
        else {  // upload failed
          if($err == "UPLOAD_ERR_DIRECTORY"){
            $msg = "Directory upload not allowed.";
          }
          else if($err == "UPLOAD_ERR_FETCH"){
            $msg = "Failed to download file from Dropbox";
          }
          else if ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) {
            $size = $RCMAIL->show_bytes(parse_bytes(ini_get('upload_max_filesize')));
            $msg  = $RCMAIL->gettext(array('name' => 'filesizeerror', 'vars' => array('size' => $size)));
          }
          else if ($attachment['error']) {
            $msg = $attachment['error'];
          }
          else {
            $msg = $RCMAIL->gettext('fileuploaderror');
          }

          if ($attachment['error'] || $err != UPLOAD_ERR_NO_FILE) {
            $RCMAIL->output->command('display_message', $msg, 'error');
            $RCMAIL->output->command('remove_from_attachment_list', $uploadid);
          }
        }
      }

    }

    $RCMAIL->output->command('auto_save_start', 'false');    
    $RCMAIL->output->send();
  }

  function download_fopen($url)
  { 

    $tmpdir =  ini_get('upload_tmp_dir')?  ini_get('upload_tmp_dir') : sys_get_temp_dir();
    $tempfile = tempnam($tmpdir, 'php');
    //set_time_limit(0);
    $input = fopen($url, "rb");
    $output = fopen($tempfile, "wb");

    if ($input === false || $output === false || $tempfile === false) {
      return false;
    }
 
    while (!feof($input)) {
      if(fwrite($output, fread($input, 1024)) === FALSE) {
        return false;
      }
    }
    
    fclose($input);
    fclose($output);
    return $tempfile;
  }

  //ref: filesystem_attachments/filesystem_attachments.php
  function move_file($args){
    $args['status'] = false;
    $group  = $args['group'];
    $rcmail = rcmail::get_instance();

    $temp_dir = $rcmail->config->get('temp_dir');
    $tmpfname = tempnam($temp_dir, 'rcmAttmnt');


    if ( rename($args['path'], $tmpfname) && file_exists($tmpfname)) {
      $args['id']     = $this->file_id();
      $args['path']   = $tmpfname;
      $args['status'] = true;
      @chmod($tmpfname, 0600);  // set correct permissions (#1488996)

      // Note the file for later cleanup
      $_SESSION['plugins']['filesystem_attachments'][$group][$args['id']] = $tmpfname;

    }
    return $args;

  }
  function file_id()
  {
    $userid = rcmail::get_instance()->user->ID;
    list($usec, $sec) = explode(' ', microtime());
    $id = preg_replace('/[^0-9]/', '', $userid . $sec . $usec);

    // make sure the ID is really unique (#1489546)
    while ($this->find_file_by_id($id)) {
      // increment last four characters
      $x  = substr($id, -4) + 1;
      $id = substr($id, 0, -4) . sprintf('%04d', ($x > 9999 ? $x - 9999 : $x));
    }

    return $id;
  }

  private function find_file_by_id($id)
  {
    foreach ((array) $_SESSION['plugins']['filesystem_attachments'] as $group => $files) {
      if (isset($files[$id])) {
        return true;
      }
    }
  }

}

?>
