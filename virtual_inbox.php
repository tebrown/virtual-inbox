<?php

/**
 * virtual_inbox (virtual_inbox)
 *
 * Based on Mark As Junk sample plugin.
 *
 * @version 1.0 - 2013-10-26
 * @author Travis Brown
 * @website http://github.com/tebrown/virtual-inbox
 */
 
/**
 *
 * Usage: http://github.com/tebrown/virtual-inbox
 *
 **/
  
class virtual_inbox extends rcube_plugin
{
  public $task = 'mail|settings';
  
  private $done = false;
  
  /* unified plugin properties */
  static private $plugin = 'virtual_inbox';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://github.com/tebrown/virtual_iinbox" target="_new">Documentation</a>';
  static private $download = 'http://github.com/tebrown/virtual_inbox';
  static private $version = '1.0';
  static private $date = '10-25-2013';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '0.9',
    'PHP' => '5.2.1',
  );
  static private $prefs = array('vinbox_mbox');
  static private $config_dist = 'config.inc.php.dist';

  function init()
  {
    $rcmail = rcmail::get_instance();
    if(!in_array('global_config', $rcmail->config->get('plugins'))){
      $this->load_config();
    }

    $this->add_texts('localization/');
    $this->register_action('plugin.virtual_inbox', array($this, 'request_action'));

    if ($rcmail->task == 'mail' && ($rcmail->action == '' || $rcmail->action == 'show')
        && ($vinbox_folder = $rcmail->config->get('vinbox_mbox'))) 
    {
        
        $skin_path = $this->local_skin_path();
        $this->add_hook('render_mailboxlist', array($this, 'render_mailboxlist'));

        // set env variable for client
        $rcmail->output->set_env('vinbox_folder', $vinbox_folder);
        $rcmail->output->set_env('vinbox_folder_icon', $this->url($skin_path.'/foldericon.png'));
          
        $this->include_stylesheet($skin_path . '/virtual_inbox.css');


    }
    else if ($rcmail->task == 'settings') {
      $dont_override = $rcmail->config->get('dont_override', array());
      if (!in_array('vinbox_mbox', $dont_override)) {
        $this->add_hook('preferences_sections_list', array($this, 'vinboxsection'));
        $this->add_hook('preferences_list', array($this, 'prefs_table'));
        $this->add_hook('preferences_save', array($this, 'save_prefs'));
      }
    }
  }
  
  static public function about($keys = false)
  {
    $requirements = self::$requirements;
    foreach(array('required_', 'recommended_') as $prefix){
      if(is_array($requirements[$prefix.'plugins'])){
        foreach($requirements[$prefix.'plugins'] as $plugin => $method){
          if(class_exists($plugin) && method_exists($plugin, 'about')){
            /* PHP 5.2.x workaround for $plugin::about() */
            $class = new $plugin(false);
            $requirements[$prefix.'plugins'][$plugin] = array(
              'method' => $method,
              'plugin' => $class->about($keys),
            );
          }
          else{
             $requirements[$prefix.'plugins'][$plugin] = array(
               'method' => $method,
               'plugin' => $plugin,
             );
          }
        }
      }
    }
    $rcmail_config = array();
    if(is_string(self::$config_dist)){
      if(is_file($file = INSTALL_PATH . 'plugins/' . self::$plugin . '/' . self::$config_dist))
        include $file;
      else
        write_log('errors', self::$plugin . ': ' . self::$config_dist . ' is missing!');
    }
    $ret = array(
      'plugin' => self::$plugin,
      'version' => self::$version,
      'date' => self::$date,
      'author' => self::$author,
      'comments' => self::$authors_comments,
      'licence' => self::$licence,
      'download' => self::$download,
      'requirements' => $requirements,
    );
    if(is_array(self::$prefs))
      $ret['config'] = array_merge($rcmail_config, array_flip(self::$prefs));
    else
      $ret['config'] = $rcmail_config;
    if(is_array($keys)){
      $return = array('plugin' => self::$plugin);
      foreach($keys as $key){
        $return[$key] = $ret[$key];
      }
      return $return;
    }
    else{
      return $ret;
    }
  }
  
  function vinboxsection($args)
  {
    $skin = rcmail::get_instance()->config->get('skin');
    if($skin != 'larry'){
      $this->add_texts('localization');  
      $args['list']['folderslink']['id'] = 'folderslink';
      $args['list']['folderslink']['section'] = $this->gettext('vinbox.folders');
    }
    return $args;
  }

  function render_mailboxlist($p)
  {

    if($this->done){
      return $p;
    }
    
    $this->done = true;
    
    $this->include_script('virtual_inbox.js');

    $rcmail = rcmail::get_instance();
    if ($rcmail->task == 'mail' && ($rcmail->action == '' || $rcmail->action == 'show') && ($vinbox_folder = $rcmail->config->get('vinbox_mbox', false))) {   

      // add virtual inbox folder to the list of default mailboxes
      if (($default_folders = $rcmail->config->get('default_folders')) && !in_array($vinbox_folder, $default_folders)) {
        $default_folders[] = $vinbox_folder;
        $rcmail->config->set('default_folders', $default_folders);
      }
      
    }

    // set localized name for the configured vinbox folder  
    if ($vinbox_folder) {  
        //print_r($p['list']);
        //exit();
      if (isset($p['list'][$vinbox_folder]))  
      {
        $p['list'][$vinbox_folder]['name'] = $this->gettext('virtual_inbox.vinbox');  
      }
      else // search in subfolders  
      {
        $this->_mod_folder_name($p['list'], $vinbox_folder, $this->gettext('virtual_inbox.vinbox'));  
      }
    }  
    return $p;
  }
  
  function _mod_folder_name(&$list, $folder, $new_name)  
  {  
    foreach ($list as $idx => $item) {  
      if ($item['id'] == $folder) {  
        $list[$idx]['name'] = $new_name;  
        return true;  
      } else if (!empty($item['folders']))  
        if ($this->_mod_folder_name($list[$idx]['folders'], $folder, $new_name))  
          return true;  
    }  
    return false;  
  }  

  function request_action()
  {
    $this->add_texts('localization');
    $uids = get_input_value('_uid', RCUBE_INPUT_POST);
    $mbox = get_input_value('_mbox', RCUBE_INPUT_POST);
    $rcmail = rcmail::get_instance();
    
    if (($vinbox_mbox = $rcmail->config->get('vinbox_mbox')) && $mbox != $vinbox_mbox) {
      $rcmail->output->command('move_messages', $vinbox_mbox);
    }
    
    $rcmail->output->send();
  }

  function prefs_table($args)
  {
    if ($args['section'] == 'folders') {
      $this->add_texts('localization');
      
      $rcmail = rcmail::get_instance();
      $select = rcmail_mailbox_select(array('noselection' => '---', 'realnames' => true, 'maxlength' => 30));
      
      $args['blocks']['main']['options']['vinbox_mbox']['title'] = Q($this->gettext('vinbox'));
      $args['blocks']['main']['options']['vinbox_mbox']['content'] = $select->show($rcmail->config->get('vinbox_mbox'), array('name' => "_vinbox_mbox"));
    }
    if ($args['section'] == 'folderslink') {
      $args['blocks']['main']['options']['folderslink']['title']    = $this->gettext('folders') . " ::: " . $_SESSION['username'];
      $args['blocks']['main']['options']['folderslink']['content']  = "<script type='text/javascript'>\n";
      $args['blocks']['main']['options']['folderslink']['content'] .= "  parent.location.href='./?_task=settings&_action=folders'\n";
      $args['blocks']['main']['options']['folderslink']['content'] .= "</script>\n";
    }
    return $args;
  }

  function save_prefs($args)
  {
    if ($args['section'] == 'folders') {  
      $args['prefs']['vinbox_mbox'] = get_input_value('_vinbox_mbox', RCUBE_INPUT_POST);  
      return $args;  
    }
  }

}
?>
